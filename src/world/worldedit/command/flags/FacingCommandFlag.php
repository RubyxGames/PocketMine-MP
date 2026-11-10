<?php

namespace pocketmine\world\worldedit\command\flags;

use pocketmine\world\worldedit\session\Session;
use pocketmine\world\worldedit\utils\ArgumentParser;

class FacingCommandFlag extends IntegerCommandFlag
{
	/**
	 * @param Session         $session
	 * @param string          $argument
	 * @return FacingCommandFlag
	 */
	public function parseArgument(Session $session, string $argument): self
	{
		$this->setArgument(ArgumentParser::parseFacing($session, $argument));
		return $this;
	}
}