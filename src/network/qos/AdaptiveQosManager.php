<?php

declare(strict_types=1);

namespace pocketmine\network\qos;

use pocketmine\player\Player;
use pocketmine\Server;
use function max;
use function min;
use function spl_object_id;

/**
 * Lowers per-player view distance when EWMA ping is high, with hysteresis to avoid flapping.
 */
final class AdaptiveQosManager{

	/** @var PlayerConnectionQos[] */
	private array $byPlayer = [];

	private bool $enabled;
	private float $pingSoftMs;
	private float $pingHardMs;
	private float $recoverMs;
	private int $softViewDistance;
	private int $hardViewDistance;
	private int $minViewDistance;
	private int $checkIntervalTicks;

	public function __construct(
		private Server $server
	){
		$cfg = $server->getConfigGroup();
		$this->enabled = $cfg->getPropertyBool("network.adaptive-qos.enabled", false);
		$this->pingSoftMs = (float) $cfg->getPropertyInt("network.adaptive-qos.ping-soft-ms", 150);
		$this->pingHardMs = (float) $cfg->getPropertyInt("network.adaptive-qos.ping-hard-ms", 300);
		$this->recoverMs = (float) $cfg->getPropertyInt("network.adaptive-qos.recover-ms", 100);
		$this->softViewDistance = max(2, $cfg->getPropertyInt("network.adaptive-qos.soft-view-distance", 6));
		$this->hardViewDistance = max(2, $cfg->getPropertyInt("network.adaptive-qos.hard-view-distance", 4));
		$this->minViewDistance = max(2, $cfg->getPropertyInt("network.adaptive-qos.min-view-distance", 3));
		$this->checkIntervalTicks = max(1, $cfg->getPropertyInt("network.adaptive-qos.check-interval-ticks", 40));

		if($this->hardViewDistance > $this->softViewDistance){
			$this->hardViewDistance = $this->softViewDistance;
		}
		if($this->minViewDistance > $this->hardViewDistance){
			$this->minViewDistance = $this->hardViewDistance;
		}
		if($this->pingHardMs < $this->pingSoftMs){
			$this->pingHardMs = $this->pingSoftMs;
		}
		if($this->recoverMs > $this->pingSoftMs){
			$this->recoverMs = $this->pingSoftMs;
		}
	}

	public function isEnabled() : bool{
		return $this->enabled;
	}

	public function getCheckIntervalTicks() : int{
		return $this->checkIntervalTicks;
	}

	public function onPlayerQuit(Player $player) : void{
		unset($this->byPlayer[spl_object_id($player)]);
	}

	public function notePing(Player $player, int $pingMs) : void{
		if(!$this->enabled){
			return;
		}
		$this->state($player)->notePing($pingMs);
	}

	/**
	 * Called from Player::setViewDistance for client/plugin requests.
	 * Stores desired distance and returns the QoS-limited effective value.
	 */
	public function filterClientViewDistance(Player $player, int $allowedDistance) : int{
		if(!$this->enabled){
			return $allowedDistance;
		}
		$state = $this->state($player);
		$state->setDesiredViewDistance($allowedDistance);
		return $this->computeEffective($state, $allowedDistance);
	}

	public function tick(int $tickCounter) : void{
		if(!$this->enabled || ($tickCounter % $this->checkIntervalTicks) !== 0){
			return;
		}
		foreach($this->server->getOnlinePlayers() as $player){
			if(!$player->isConnected() || $player->getViewDistance() < 0){
				continue;
			}
			$state = $this->state($player);
			$desired = $state->getDesiredViewDistance();
			if($desired < 0){
				$desired = $player->getViewDistance();
				$state->setDesiredViewDistance($desired);
			}
			$effective = $this->computeEffective($state, $desired);
			if($effective !== $player->getViewDistance()){
				$oldTier = $state->getTier();
				$player->applyQosViewDistance($effective);
				$newTier = $state->getTier();
				if($oldTier !== $newTier){
					$player->getServer()->getLogger()->debug(
						"Adaptive QoS for {$player->getName()}: tier $oldTier->$newTier, ping≈" .
						(int) $state->getEwmaPingMs() . "ms, view $desired->$effective"
					);
				}
			}
		}
	}

	private function computeEffective(PlayerConnectionQos $state, int $desired) : int{
		$desired = max($this->minViewDistance, $desired);
		if(!$state->hasPing()){
			$state->setTier(PlayerConnectionQos::TIER_NONE);
			return $desired;
		}

		$ping = $state->getEwmaPingMs();
		$tier = $state->getTier();

		if($tier === PlayerConnectionQos::TIER_HARD){
			if($ping <= $this->pingSoftMs){
				$tier = PlayerConnectionQos::TIER_SOFT;
			}elseif($ping <= $this->recoverMs){
				$tier = PlayerConnectionQos::TIER_NONE;
			}
		}elseif($tier === PlayerConnectionQos::TIER_SOFT){
			if($ping >= $this->pingHardMs){
				$tier = PlayerConnectionQos::TIER_HARD;
			}elseif($ping <= $this->recoverMs){
				$tier = PlayerConnectionQos::TIER_NONE;
			}
		}else{
			if($ping >= $this->pingHardMs){
				$tier = PlayerConnectionQos::TIER_HARD;
			}elseif($ping >= $this->pingSoftMs){
				$tier = PlayerConnectionQos::TIER_SOFT;
			}
		}

		$state->setTier($tier);

		$cap = match($tier){
			PlayerConnectionQos::TIER_HARD => $this->hardViewDistance,
			PlayerConnectionQos::TIER_SOFT => $this->softViewDistance,
			default => $desired,
		};

		return max($this->minViewDistance, min($desired, $cap));
	}

	private function state(Player $player) : PlayerConnectionQos{
		$id = spl_object_id($player);
		return $this->byPlayer[$id] ??= new PlayerConnectionQos();
	}
}
