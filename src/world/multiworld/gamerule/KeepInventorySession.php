<?php

declare(strict_types=1);

namespace pocketmine\world\multiworld\gamerule;

use pocketmine\item\Item;
use pocketmine\player\Player;

/**
 * Snapshot of a player's inventory/XP for keepInventory gamerule.
 */
final class KeepInventorySession{

	/** @var Item[] */
	private array $inventory;
	/** @var Item[] */
	private array $armor;
	/** @var Item[] */
	private array $cursor;
	private int $xpLevel;
	private float $xpProgress;

	public function __construct(
		private Player $player
	){
		$this->inventory = $player->getInventory()->getContents();
		$this->armor = $player->getArmorInventory()->getContents();
		$this->cursor = $player->getCursorInventory()->getContents();
		$this->xpLevel = $player->getXpManager()->getXpLevel();
		$this->xpProgress = $player->getXpManager()->getXpProgress();
	}

	public function restore() : void{
		if(!$this->player->isOnline()){
			return;
		}
		$this->player->getInventory()->setContents($this->inventory);
		$this->player->getArmorInventory()->setContents($this->armor);
		$this->player->getCursorInventory()->setContents($this->cursor);
		$this->player->getXpManager()->setXpLevel($this->xpLevel);
		$this->player->getXpManager()->setXpProgress($this->xpProgress);
	}
}
