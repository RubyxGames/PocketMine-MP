<?php

namespace pocketmine\world\worldedit\command;

use Generator;
use pocketmine\world\worldedit\command\flags\CommandFlag;
use pocketmine\world\worldedit\command\flags\CommandFlagCollection;
use pocketmine\world\worldedit\session\Session;

class FlagRemapAlias extends WorldEditCommand
{
	public function __construct(private WorldEditCommand $parent, private CommandFlag $flag, string $name, array $aliases = [])
	{
		parent::__construct($name, $parent->getPermissions(), $aliases);
	}

	public function process(Session $session, CommandFlagCollection $flags): void
	{
		if ($flags->hasFlag($this->flag->getName())) {
			$flags->removeFlag($this->flag->getName());
		}
		$flags->addFlag($this->flag);
		$this->parent->process($session, $flags);
	}

	/**
	 * @param Session $session
	 * @return CommandFlag[]
	 */
	public function getKnownFlags(Session $session): array
	{
		return $this->parent->getKnownFlags($session);
	}

	/**
	 * @param CommandFlagCollection $flags
	 * @param Session               $session
	 * @param string[]              $args
	 * @return Generator<CommandFlag>
	 */
	public function parseArguments(CommandFlagCollection $flags, Session $session, array $args): Generator
	{
		yield from $this->parent->parseArguments($flags, $session, $args);
	}
}