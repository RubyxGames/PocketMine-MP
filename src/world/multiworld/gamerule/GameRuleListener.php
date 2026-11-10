<?php

declare(strict_types=1);

namespace pocketmine\world\multiworld\gamerule;

use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Living;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityExplodeEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\world\WorldLoadEvent;
use pocketmine\event\world\WorldUnloadEvent;
use pocketmine\player\Player;

/**
 * Enforces server-side MultiWorld gamerules.
 */
final class GameRuleListener implements Listener{

	/** @var array<int, KeepInventorySession> */
	private array $deathSessions = [];

	public function onWorldLoad(WorldLoadEvent $event) : void{
		WorldGameRules::for($event->getWorld());
	}

	public function onWorldUnload(WorldUnloadEvent $event) : void{
		WorldGameRules::forget($event->getWorld());
	}

	public function onQuit(PlayerQuitEvent $event) : void{
		unset($this->deathSessions[$event->getPlayer()->getId()]);
	}

	public function onDeath(PlayerDeathEvent $event) : void{
		$player = $event->getPlayer();
		if(WorldGameRules::for($player->getWorld())->getBool("keepInventory", false)){
			$this->deathSessions[$player->getId()] = new KeepInventorySession($player);
			$event->setDrops([]);
		}
	}

	public function onRespawn(PlayerRespawnEvent $event) : void{
		$id = $event->getPlayer()->getId();
		if(isset($this->deathSessions[$id])){
			$this->deathSessions[$id]->restore();
			unset($this->deathSessions[$id]);
		}
	}

	public function onBreak(BlockBreakEvent $event) : void{
		if(!WorldGameRules::for($event->getPlayer()->getWorld())->getBool("doTileDrops", true)){
			$event->setDrops([]);
		}
	}

	public function onRegenerate(EntityRegainHealthEvent $event) : void{
		$entity = $event->getEntity();
		if(!$entity instanceof Living){
			return;
		}
		if($entity->getEffects()->has(VanillaEffects::REGENERATION())){
			return;
		}
		if(!WorldGameRules::for($entity->getWorld())->getBool("naturalRegeneration", true)){
			$event->cancel();
		}
	}

	public function onDamage(EntityDamageEvent $event) : void{
		$entity = $event->getEntity();
		if(
			$entity instanceof Player &&
			$event instanceof EntityDamageByEntityEvent &&
			$event->getDamager() instanceof Player &&
			!WorldGameRules::for($entity->getWorld())->getBool("pvp", true)
		){
			$event->cancel();
		}
	}

	public function onExplode(EntityExplodeEvent $event) : void{
		if(!WorldGameRules::for($event->getEntity()->getWorld())->getBool("tntExplodes", true)){
			$event->cancel();
		}
	}
}
