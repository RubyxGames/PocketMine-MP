<?php

namespace pocketmine\world\worldedit\command\defaults\utility;

use pocketmine\world\worldedit\command\WorldEditCommand;
use pocketmine\world\worldedit\command\FlagArgumentParser;
use pocketmine\world\worldedit\command\flags\BlockCommandFlag;
use pocketmine\world\worldedit\command\flags\CommandFlag;
use pocketmine\world\worldedit\command\flags\CommandFlagCollection;
use pocketmine\world\worldedit\command\flags\FacingCommandFlag;
use pocketmine\world\worldedit\command\KnownPermissions;
use pocketmine\world\worldedit\math\BlockVector;
use pocketmine\world\worldedit\pattern\block\StaticBlock;
use pocketmine\world\worldedit\session\Session;
use pocketmine\world\worldedit\task\expanding\FillTask;
use pocketmine\world\worldedit\utils\VectorUtils;
use pocketmine\block\VanillaBlocks;

class FillCommand extends WorldEditCommand
{
	use FlagArgumentParser;

	public function __construct()
	{
		parent::__construct("/fill", [KnownPermissions::PERMISSION_EDIT, KnownPermissions::PERMISSION_GENERATE]);
		$this->flagOrder = ["block" => false, "direction" => false];
	}

	/**
	 * @param Session               $session
	 * @param CommandFlagCollection $flags
	 */
	public function process(Session $session, CommandFlagCollection $flags): void
	{
		$session->runSettingTask(new FillTask($session->asPlayer()->getWorld()->getFolderName(), BlockVector::fromVector($session->asPlayer()->getPosition()), $flags->getIntFlag("direction"), $flags->getStaticBlockFlag("block")));
	}

	/**
	 * @param Session $session
	 * @return CommandFlag[]
	 */
	public function getKnownFlags(Session $session): array
	{
		return [
			"block" => BlockCommandFlag::default(StaticBlock::from(VanillaBlocks::WATER()), "block", [], "b"),
			"direction" => FacingCommandFlag::default(VectorUtils::getFacing($session->asPlayer()->getLocation()), "direction", ["dir"], "d")
		];
	}
}