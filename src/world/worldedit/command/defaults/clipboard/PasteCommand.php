<?php

namespace pocketmine\world\worldedit\command\defaults\clipboard;

use pocketmine\world\worldedit\command\WorldEditCommand;
use pocketmine\world\worldedit\command\FlagArgumentParser;
use pocketmine\world\worldedit\command\flags\CommandFlag;
use pocketmine\world\worldedit\command\flags\CommandFlagCollection;
use pocketmine\world\worldedit\command\flags\IntegerCommandFlag;
use pocketmine\world\worldedit\command\KnownPermissions;
use pocketmine\world\worldedit\math\OffGridBlockVector;
use pocketmine\world\worldedit\session\Session;
use pocketmine\world\worldedit\task\editing\DynamicPasteTask;

class PasteCommand extends WorldEditCommand
{
	use FlagArgumentParser;

	public function __construct()
	{
		parent::__construct("/paste", [KnownPermissions::PERMISSION_CLIPBOARD, KnownPermissions::PERMISSION_EDIT]);
	}

	/**
	 * @param Session               $session
	 * @param CommandFlagCollection $flags
	 */
	public function process(Session $session, CommandFlagCollection $flags): void
	{
		$session->runEditTask("blocks-pasted", new DynamicPasteTask($session->asPlayer()->getWorld()->getFolderName(), $session->getClipboard(), OffGridBlockVector::fromVector($session->asPlayer()->getPosition()), $flags->getIntFlag("mode")));
	}

	/**
	 * @param Session $session
	 * @return CommandFlag[]
	 */
	public function getKnownFlags(Session $session): array
	{
		return [
			"default" => IntegerCommandFlag::with(DynamicPasteTask::MODE_REPLACE_ALL, "mode", [], "d", true),
			"insert" => IntegerCommandFlag::with(DynamicPasteTask::MODE_REPLACE_AIR, "mode", [], "i"),
			"merge" => IntegerCommandFlag::with(DynamicPasteTask::MODE_ONLY_SOLID, "mode", ["solid"], "m"),
			"replace" => IntegerCommandFlag::with(DynamicPasteTask::MODE_REPLACE_SOLID, "mode", [], "r")
		];
	}
}