<?php

namespace pocketmine\world\worldedit\command\defaults\selection;

use pocketmine\world\worldedit\command\WorldEditCommand;
use pocketmine\world\worldedit\command\FlagArgumentParser;
use pocketmine\world\worldedit\command\flags\CommandFlagCollection;
use pocketmine\world\worldedit\command\KnownPermissions;
use pocketmine\world\worldedit\pattern\Pattern;
use pocketmine\world\worldedit\session\Session;
use pocketmine\world\worldedit\task\editing\SetTask;

abstract class AliasedPatternCommand extends WorldEditCommand
{
	use FlagArgumentParser;

	/**
	 * @param string   $name
	 * @param bool[]   $flagOrder
	 * @param string[] $aliases
	 */
	public function __construct(string $name, array $flagOrder = [], array $aliases = [])
	{
		parent::__construct($name, [KnownPermissions::PERMISSION_EDIT], $aliases);
		$this->flagOrder = $flagOrder;
	}

	/**
	 * @param Session               $session
	 * @param CommandFlagCollection $flags
	 */
	public function process(Session $session, CommandFlagCollection $flags): void
	{
		$session->runSettingTask(new SetTask($session->getSelection(), $this->parsePattern($session, $flags)));
	}

	/**
	 * @param Session               $session
	 * @param CommandFlagCollection $flags
	 * @return Pattern
	 */
	abstract public function parsePattern(Session $session, CommandFlagCollection $flags): Pattern;
}