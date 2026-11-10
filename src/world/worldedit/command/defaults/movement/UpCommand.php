<?php

namespace pocketmine\world\worldedit\command\defaults\movement;

use pocketmine\world\worldedit\command\WorldEditCommand;
use pocketmine\world\worldedit\command\FlagArgumentParser;
use pocketmine\world\worldedit\command\flags\CommandFlagCollection;
use pocketmine\world\worldedit\command\flags\IntegerCommandFlag;
use pocketmine\world\worldedit\command\KnownPermissions;
use pocketmine\world\worldedit\session\Session;
use pocketmine\block\Air;
use pocketmine\block\VanillaBlocks;
use pocketmine\world\World;

class UpCommand extends WorldEditCommand
{
	use FlagArgumentParser;

	public function __construct()
	{
		parent::__construct("/up", [KnownPermissions::PERMISSION_UTIL, KnownPermissions::PERMISSION_EDIT]);
		$this->flagOrder = ["amount" => false];
	}

	/**
	 * @param Session               $session
	 * @param CommandFlagCollection $flags
	 */
	public function process(Session $session, CommandFlagCollection $flags): void
	{
		$player = $session->asPlayer();
		$pos = $player->getPosition()->up($flags->getIntFlag("amount"));
		$pos->y = min(max($pos->y, World::Y_MIN + 1), World::Y_MAX);
		$player->teleport($pos);
		if ($player->getWorld()->getBlock($pos->floor()->down()) instanceof Air) {
			$player->getWorld()->setBlock($pos->floor()->down(), VanillaBlocks::GLASS());
		}
	}

	public function getKnownFlags(Session $session): array
	{
		return [
			"amount" => IntegerCommandFlag::default(1, "amount", ["count"], "a")
		];
	}
}