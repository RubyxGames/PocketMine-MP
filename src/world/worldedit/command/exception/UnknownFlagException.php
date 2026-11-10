<?php

namespace pocketmine\world\worldedit\command\exception;

use pocketmine\world\worldedit\command\WorldEditCommand;
use pocketmine\world\worldedit\session\Session;
use pocketmine\lang\Translatable;
use UnexpectedValueException;

class UnknownFlagException extends CommandException
{
	public function __construct(string $flag)
	{
		parent::__construct($flag);
	}

	public function sendWarning(Session $session, WorldEditCommand $command): void
	{
		if ($command->getUsage() instanceof Translatable) {
			throw new UnexpectedValueException("WorldEdit commands should not use translatable usages");
		}
		$session->sendMessage("unknown-flag", ["{flag}" => $this->getMessage(), "{usage}" => $command->getUsage()]);
	}
}