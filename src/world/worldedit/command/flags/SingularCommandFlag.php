<?php

namespace pocketmine\world\worldedit\command\flags;

use InvalidArgumentException;
use pocketmine\world\worldedit\session\Session;
use pocketmine\world\worldedit\utils\ArgumentParser;

class SingularCommandFlag extends CommandFlag
{
	public function needsArgument(): bool
	{
		return false;
	}

	public function parseArgument(Session $session, string $argument): self
	{
		throw new InvalidArgumentException("This flag does not need an argument");
	}

	public function fits(string $argument): bool
	{
		return in_array(strtolower($argument), ["true", "t", "yes", "y", "1", "+"], true);
	}
}