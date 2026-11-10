<?php

namespace pocketmine\world\worldedit\command\exception;

use pocketmine\world\worldedit\command\WorldEditCommand;
use pocketmine\world\worldedit\pattern\parser\ParseError;
use pocketmine\world\worldedit\session\Session;

class PatternParseException extends CommandException
{
	public function __construct(ParseError $error)
	{
		parent::__construct($error->getMessage());
	}

	public function sendWarning(Session $session, WorldEditCommand $command): void
	{
		$session->sendMessage("pattern-invalid", ["{message}" => $this->getMessage()]);
	}
}