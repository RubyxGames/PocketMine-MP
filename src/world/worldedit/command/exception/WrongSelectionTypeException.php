<?php

namespace pocketmine\world\worldedit\command\exception;

use pocketmine\world\worldedit\command\WorldEditCommand;
use pocketmine\world\worldedit\session\Session;

class WrongSelectionTypeException extends CommandException
{
	public function __construct(private string $given, private string $expected)
	{
		parent::__construct("Wrong selection type " . $given . " given, expected " . $expected);
	}

	public function sendWarning(Session $session, WorldEditCommand $command): void
	{
		$session->sendMessage("wrong-selection", ["{given}" => $this->given, "{expected}" => $this->expected]);
	}
}