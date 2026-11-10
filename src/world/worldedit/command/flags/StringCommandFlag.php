<?php

namespace pocketmine\world\worldedit\command\flags;

use pocketmine\world\worldedit\session\Session;

/**
 * @extends ValuedCommandFlag<string>
 */
class StringCommandFlag extends ValuedCommandFlag
{
	/**
	 * @param Session         $session
	 * @param string          $argument
	 * @return StringCommandFlag
	 */
	public function parseArgument(Session $session, string $argument): self
	{
		$this->setArgument($argument);
		return $this;
	}
}