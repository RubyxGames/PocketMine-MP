<?php

namespace pocketmine\world\worldedit\command\defaults\selection;

use pocketmine\world\worldedit\command\WorldEditCommand;
use pocketmine\world\worldedit\command\FlagArgumentParser;
use pocketmine\world\worldedit\command\flags\CommandFlag;
use pocketmine\world\worldedit\command\flags\CommandFlagCollection;
use pocketmine\world\worldedit\command\flags\PatternCommandFlag;
use pocketmine\world\worldedit\command\KnownPermissions;
use pocketmine\world\worldedit\session\Session;
use pocketmine\world\worldedit\task\editing\SetTask;

class SetCommand extends WorldEditCommand
{
	use FlagArgumentParser;

	public function __construct()
	{
		parent::__construct("/set", [KnownPermissions::PERMISSION_EDIT]);
		$this->flagOrder = ["pattern" => true];
	}

	/**
	 * @param Session               $session
	 * @param CommandFlagCollection $flags
	 */
	public function process(Session $session, CommandFlagCollection $flags): void
	{
		$session->runSettingTask(new SetTask($session->getSelection(), $flags->getPatternFlag("pattern")));
	}

	/**
	 * @param Session $session
	 * @return CommandFlag[]
	 */
	public function getKnownFlags(Session $session): array
	{
		return [
			"pattern" => new PatternCommandFlag("pattern", [], "p")
		];
	}
}