<?php

namespace pocketmine\world\worldedit\command\defaults\clipboard;

use pocketmine\world\worldedit\command\WorldEditCommand;
use pocketmine\world\worldedit\command\FlagArgumentParser;
use pocketmine\world\worldedit\command\flags\CommandFlag;
use pocketmine\world\worldedit\command\flags\CommandFlagCollection;
use pocketmine\world\worldedit\command\flags\FacingCommandFlag;
use pocketmine\world\worldedit\command\KnownPermissions;
use pocketmine\world\worldedit\result\SelectionManipulationResult;
use pocketmine\world\worldedit\session\Session;
use pocketmine\world\worldedit\task\DynamicStoredFlipTask;
use pocketmine\world\worldedit\utils\MixedUtils;
use pocketmine\world\worldedit\utils\VectorUtils;
use pocketmine\math\Facing;

class FlipCommand extends WorldEditCommand
{
	use FlagArgumentParser;

	public function __construct()
	{
		parent::__construct("/flip", [KnownPermissions::PERMISSION_CLIPBOARD]);
		$this->flagOrder = ["direction" => false];
	}

	/**
	 * @param Session               $session
	 * @param CommandFlagCollection $flags
	 */
	public function process(Session $session, CommandFlagCollection $flags): void
	{
		$session->runTask(new DynamicStoredFlipTask($session->getClipboard(), Facing::axis($flags->getIntFlag("direction"))))->then(function (SelectionManipulationResult $result) use ($session) {
			$session->sendMessage("blocks-rotated", ["{time}" => $result->getFormattedTime(), "{changed}" => MixedUtils::humanReadable($result->getChanged())]);
		});
	}

	/**
	 * @param Session $session
	 * @return CommandFlag[]
	 */
	public function getKnownFlags(Session $session): array
	{
		return [
			"direction" => FacingCommandFlag::default(VectorUtils::getFacing($session->asPlayer()->getLocation()), "direction", ["dir"], "d")
		];
	}
}