<?php

namespace pocketmine\world\worldedit\command\defaults\selection;

use pocketmine\world\worldedit\command\flags\CommandFlagCollection;
use pocketmine\world\worldedit\command\KnownPermissions;
use pocketmine\world\worldedit\convert\BlockTagManager;
use pocketmine\world\worldedit\pattern\block\StaticBlock;
use pocketmine\world\worldedit\pattern\logic\relation\BlockPattern;
use pocketmine\world\worldedit\selection\Selection;
use pocketmine\world\worldedit\session\Session;
use pocketmine\world\worldedit\task\editing\SetTask;
use pocketmine\block\VanillaBlocks;

class ExtinguishCommand extends SphericalSelectionCommand
{
	public function __construct()
	{
		parent::__construct("/extinguish", [KnownPermissions::PERMISSION_EDIT], ["/ext"]);
	}

	/**
	 * @param Session               $session
	 * @param Selection             $selection
	 * @param CommandFlagCollection $flags
	 */
	public function processSelection(Session $session, Selection $selection, CommandFlagCollection $flags): void
	{
		$session->runSettingTask(new SetTask($selection, new BlockPattern(BlockTagManager::getTag("fire", true), [StaticBlock::from(VanillaBlocks::AIR())])));
	}
}