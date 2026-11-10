<?php

namespace pocketmine\world\worldedit\command\defaults\clipboard;

use pocketmine\world\worldedit\command\WorldEditCommand;
use pocketmine\world\worldedit\command\FlagArgumentParser;
use pocketmine\world\worldedit\command\flags\CommandFlag;
use pocketmine\world\worldedit\command\flags\CommandFlagCollection;
use pocketmine\world\worldedit\command\flags\SingularCommandFlag;
use pocketmine\world\worldedit\command\flags\VectorCommandFlag;
use pocketmine\world\worldedit\command\KnownPermissions;
use pocketmine\world\worldedit\math\OffGridBlockVector;
use pocketmine\world\worldedit\result\CuttingTaskResult;
use pocketmine\world\worldedit\result\EditTaskResult;
use pocketmine\world\worldedit\session\Session;
use pocketmine\world\worldedit\task\editing\CopyTask;
use pocketmine\world\worldedit\task\editing\CutTask;
use pocketmine\world\worldedit\utils\MixedUtils;
use RuntimeException;

class CopyCommand extends WorldEditCommand
{
	use FlagArgumentParser;

	public function __construct()
	{
		parent::__construct("/copy", [KnownPermissions::PERMISSION_CLIPBOARD]);
	}

	/**
	 * @param Session               $session
	 * @param CommandFlagCollection $flags
	 */
	public function process(Session $session, CommandFlagCollection $flags): void
	{
		if ($flags->hasFlag("remove")) {
			if (!$this->testPermission($session->asPlayer(), KnownPermissions::PERMISSION_EDIT)) {
				return;
			}
			$session->runTask(new CutTask($session->getSelection(), $flags->getVectorFlag("relative")))->then(function (EditTaskResult $result) use ($session) {
				if (!$result instanceof CuttingTaskResult) {
					throw new RuntimeException("Expected CuttingTaskResult");
				}
				$session->sendMessage("blocks-cut", ["{time}" => $result->getFormattedTime(), "{changed}" => MixedUtils::humanReadable($result->getAffected())]);
				$session->addToHistory($result->getSelection());
				$session->setClipboard($result->getClipboard());
			});
		} else {
			$session->runTask(new CopyTask($session->getSelection(), $flags->getVectorFlag("relative")))->then(function (EditTaskResult $result) use ($session) {
				$session->sendMessage("blocks-copied", ["{time}" => $result->getFormattedTime(), "{changed}" => MixedUtils::humanReadable($result->getAffected())]);
				$session->setClipboard($result->getSelection());
			});
		}
	}

	/**
	 * @param Session $session
	 * @return CommandFlag[]
	 */
	public function getKnownFlags(Session $session): array
	{
		return [
			"center" => VectorCommandFlag::with($session->getSelection()->getBottomCenter()->offGrid(), "relative", [], "c"),
			"position" => VectorCommandFlag::default(OffGridBlockVector::fromVector($session->asPlayer()->getPosition()), "relative", [], "p"),
			"remove" => new SingularCommandFlag("remove", [], "r")
		];
	}
}