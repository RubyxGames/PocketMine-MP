<?php

declare(strict_types=1);

namespace pocketmine\sessionrecord;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\EventPriority;
use pocketmine\event\inventory\InventoryCloseEvent;
use pocketmine\event\inventory\InventoryOpenEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\HandlerListManager;
use pocketmine\event\RegisteredListener;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\player\Player;
use pocketmine\plugin\CorePluginHost;
use pocketmine\Server;
use Symfony\Component\Filesystem\Path;
use function array_keys;
use function count;
use function date;
use function hash;
use function max;
use function microtime;
use function preg_replace;
use function spl_object_id;

/**
 * Starts/stops JSONL session recordings for players.
 */
final class SessionRecordingManager{

	/** @var ActiveSessionRecording[] */
	private array $active = [];

	private bool $enabled;
	private bool $autoRecordAll;
	private string $directory;
	private int $moveSampleTicks;
	private bool $recordPacketIds;
	private int $maxFileBytes;

	/** @var RegisteredListener[] */
	private array $registeredListeners = [];

	/** @var array<int, float> last ping log time by player object id */
	private array $lastPingLogAt = [];

	public function __construct(
		private Server $server,
		private CorePluginHost $corePluginHost
	){
		$cfg = $server->getConfigGroup();
		$this->enabled = $cfg->getPropertyBool("session-recording.enabled", false);
		$this->autoRecordAll = $cfg->getPropertyBool("session-recording.auto-record-all", false);
		$relative = $cfg->getPropertyString("session-recording.directory", "session_recordings");
		$this->directory = Path::join($server->getDataPath(), $relative);
		$this->moveSampleTicks = max(0, $cfg->getPropertyInt("session-recording.move-sample-ticks", 5));
		$this->recordPacketIds = $cfg->getPropertyBool("session-recording.record-packet-ids", false);
		$maxMb = max(1, $cfg->getPropertyInt("session-recording.max-file-mb", 64));
		$this->maxFileBytes = $maxMb * 1024 * 1024;

		if($this->enabled){
			$this->registerListeners();
		}
	}

	public function isEnabled() : bool{
		return $this->enabled;
	}

	public function isRecording(Player $player) : bool{
		return isset($this->active[spl_object_id($player)]);
	}

	/**
	 * @return string[] player names currently recording
	 */
	public function getRecordingPlayerNames() : array{
		$names = [];
		foreach($this->active as $rec){
			$names[] = $rec->getPlayer()->getName();
		}
		return $names;
	}

	public function start(Player $player, string $reason = "manual") : bool{
		if(!$this->enabled){
			return false;
		}
		$id = spl_object_id($player);
		if(isset($this->active[$id])){
			return false;
		}

		$safeName = preg_replace('/[^a-zA-Z0-9_\-.]/', "_", $player->getName()) ?? "player";
		$xuid = $player->getXuid();
		// Per-player folder; filename is timestamp (+ xuid) only — nick lives in the path.
		$file = Path::join(
			$this->directory,
			$safeName,
			date("Y-m-d_His") . ($xuid !== "" ? "_$xuid" : "") . ".jsonl"
		);

		try{
			$sink = new JsonlSessionSink($file, $this->maxFileBytes);
		}catch(\RuntimeException $e){
			$this->server->getLogger()->error("Session recording failed to start for {$player->getName()}: " . $e->getMessage());
			return false;
		}

		$rec = new ActiveSessionRecording($player, $sink, $this->moveSampleTicks);
		$session = $player->getNetworkSession();
		$rec->record("session_start", [
			"reason" => $reason,
			"player" => $player->getName(),
			"xuid" => $xuid,
			"uuid" => $player->getUniqueId()->toString(),
			"protocol" => $session->getProtocolId(),
			"ip_hash" => hash("sha256", $session->getIp() . "|" . $player->getUniqueId()->toString()),
			"file" => $file,
		]);
		$this->active[$id] = $rec;
		$this->server->getLogger()->info("Session recording started for {$player->getName()} -> $file");
		return true;
	}

	public function stop(Player $player, string $reason = "manual") : bool{
		$id = spl_object_id($player);
		$rec = $this->active[$id] ?? null;
		if($rec === null){
			return false;
		}
		unset($this->active[$id], $this->lastPingLogAt[$id]);
		$ping = $player->isConnected() ? $player->getNetworkSession()->getPing() : null;
		$rec->record("session_end", [
			"reason" => $reason,
			"ping_ms" => $ping,
		]);
		$path = $rec->getSink()->getPath();
		$rec->getSink()->close();
		$this->server->getLogger()->info("Session recording stopped for {$player->getName()} ($reason) -> $path");
		return true;
	}

	public function notePing(Player $player, int $pingMs) : void{
		$id = spl_object_id($player);
		$rec = $this->active[$id] ?? null;
		if($rec === null){
			return;
		}
		$now = microtime(true);
		$last = $this->lastPingLogAt[$id] ?? 0.0;
		if(($now - $last) < 1.0){
			return;
		}
		$this->lastPingLogAt[$id] = $now;
		$rec->record("ping", ["ms" => $pingMs]);
	}

	public function tick(int $tickCounter) : void{
		if(!$this->enabled || count($this->active) === 0){
			return;
		}
		foreach($this->active as $rec){
			$rec->sampleMoveIfDue($tickCounter);
		}
	}

	public function shutdown() : void{
		foreach(array_keys($this->active) as $id){
			$rec = $this->active[$id];
			$this->stop($rec->getPlayer(), "shutdown");
		}
		$this->unregisterListeners();
	}

	private function registerListeners() : void{
		$pm = $this->server->getPluginManager();
		$this->registeredListeners[] = $pm->registerEvent(PlayerJoinEvent::class, function(PlayerJoinEvent $event) : void{
			if($this->autoRecordAll){
				$this->start($event->getPlayer(), "auto");
			}
		}, EventPriority::MONITOR, $this->corePluginHost, true);

		$this->registeredListeners[] = $pm->registerEvent(PlayerQuitEvent::class, function(PlayerQuitEvent $event) : void{
			$this->stop($event->getPlayer(), "quit");
		}, EventPriority::MONITOR, $this->corePluginHost, true);

		$this->registeredListeners[] = $pm->registerEvent(PlayerChatEvent::class, function(PlayerChatEvent $event) : void{
			$this->recordIfActive($event->getPlayer(), "chat", ["message" => $event->getMessage()]);
		}, EventPriority::MONITOR, $this->corePluginHost, true);

		$this->registeredListeners[] = $pm->registerEvent(PlayerInteractEvent::class, function(PlayerInteractEvent $event) : void{
			$block = $event->getBlock();
			$this->recordIfActive($event->getPlayer(), "interact", [
				"action" => $event->getAction(),
				"x" => $block->getPosition()->getFloorX(),
				"y" => $block->getPosition()->getFloorY(),
				"z" => $block->getPosition()->getFloorZ(),
				"item" => $event->getItem()->getName(),
			]);
		}, EventPriority::MONITOR, $this->corePluginHost, true);

		$this->registeredListeners[] = $pm->registerEvent(PlayerDropItemEvent::class, function(PlayerDropItemEvent $event) : void{
			$this->recordIfActive($event->getPlayer(), "drop", [
				"item" => $event->getItem()->getName(),
				"count" => $event->getItem()->getCount(),
			]);
		}, EventPriority::MONITOR, $this->corePluginHost, true);

		$this->registeredListeners[] = $pm->registerEvent(InventoryOpenEvent::class, function(InventoryOpenEvent $event) : void{
			$this->recordIfActive($event->getPlayer(), "inventory_open", [
				"inventory" => $event->getInventory()::class,
			]);
		}, EventPriority::MONITOR, $this->corePluginHost, true);

		$this->registeredListeners[] = $pm->registerEvent(InventoryCloseEvent::class, function(InventoryCloseEvent $event) : void{
			$this->recordIfActive($event->getPlayer(), "inventory_close", [
				"inventory" => $event->getInventory()::class,
			]);
		}, EventPriority::MONITOR, $this->corePluginHost, true);

		$this->registeredListeners[] = $pm->registerEvent(EntityDamageEvent::class, function(EntityDamageEvent $event) : void{
			$this->handleDamage($event);
		}, EventPriority::MONITOR, $this->corePluginHost, true);

		$this->registeredListeners[] = $pm->registerEvent(DataPacketReceiveEvent::class, function(DataPacketReceiveEvent $event) : void{
			if(!$this->recordPacketIds || count($this->active) === 0){
				return;
			}
			$player = $event->getOrigin()->getPlayer();
			if($player === null){
				return;
			}
			$rec = $this->active[spl_object_id($player)] ?? null;
			if($rec === null){
				return;
			}
			$packet = $event->getPacket();
			$rec->record("packet_in", [
				"name" => $packet->getName(),
				"pid" => $packet->pid(),
			]);
		}, EventPriority::MONITOR, $this->corePluginHost, true);
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function recordIfActive(Player $player, string $type, array $payload) : void{
		// Must use ?? null: `$arr[$missing]?->` still warns on undefined key (PHP 8.4 → fatal via ErrorToExceptionHandler).
		$rec = $this->active[spl_object_id($player)] ?? null;
		$rec?->record($type, $payload);
	}

	private function handleDamage(EntityDamageEvent $event) : void{
		$entity = $event->getEntity();
		if($entity instanceof Player){
			$payload = [
				"cause" => $event->getCause(),
				"damage" => $event->getFinalDamage(),
				"role" => "victim",
			];
			if($event instanceof EntityDamageByEntityEvent){
				$damager = $event->getDamager();
				$payload["attacker"] = $damager instanceof Player ? $damager->getName() : ($damager !== null ? $damager::class : null);
			}
			$this->recordIfActive($entity, "damage", $payload);
		}
		if($event instanceof EntityDamageByEntityEvent){
			$damager = $event->getDamager();
			if($damager instanceof Player){
				$victim = $event->getEntity();
				$this->recordIfActive($damager, "damage", [
					"cause" => $event->getCause(),
					"damage" => $event->getFinalDamage(),
					"role" => "attacker",
					"victim" => $victim instanceof Player ? $victim->getName() : $victim::class,
				]);
			}
		}
	}

	private function unregisterListeners() : void{
		HandlerListManager::global()->unregisterAll($this->corePluginHost);
		$this->registeredListeners = [];
	}
}
