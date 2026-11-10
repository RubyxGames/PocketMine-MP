<?php

namespace pocketmine\world\worldedit\command\exception;

use pocketmine\world\worldedit\command\WorldEditCommand;
use pocketmine\world\worldedit\session\Session;
use pocketmine\lang\Translatable;
use UnexpectedValueException;

class InvalidUsageException extends CommandException
{
	public function sendWarning(Session $session, WorldEditCommand $command): void
	{
		if ($command->getUsage() instanceof Translatable) {
			throw new UnexpectedValueException("WorldEdit commands should not use translatable usages");
		}
		$session->sendMessage("wrong-usage", ["{usage}" => $command->getUsage()]);
	}
}