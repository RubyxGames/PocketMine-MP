<?php

namespace pocketmine\world\worldedit\command\exception;

use pocketmine\world\worldedit\command\WorldEditCommand;
use pocketmine\world\worldedit\session\Session;

class NoSelectionException extends CommandException
{
	public function sendWarning(Session $session, WorldEditCommand $command): void
	{
		$session->sendMessage("no-selection");
	}
}