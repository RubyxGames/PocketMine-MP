<?php

namespace pocketmine\world\worldedit\command\exception;

use InvalidArgumentException;
use pocketmine\world\worldedit\command\WorldEditCommand;
use pocketmine\world\worldedit\session\Session;

/**
 * Exceptions thrown if commands could not be executed correctly
 */
abstract class CommandException extends InvalidArgumentException
{
	public function sendWarning(Session $session, WorldEditCommand $command): void { }
}