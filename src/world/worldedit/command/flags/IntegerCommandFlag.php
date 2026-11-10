<?php

namespace pocketmine\world\worldedit\command\flags;

use pocketmine\world\worldedit\command\exception\InvalidUsageException;
use pocketmine\world\worldedit\session\Session;

/**
 * @extends ValuedCommandFlag<int>
 */
class IntegerCommandFlag extends ValuedCommandFlag
{
	/**
	 * @param Session         $session
	 * @param string          $argument
	 * @return IntegerCommandFlag
	 */
	public function parseArgument(Session $session, string $argument): self
	{
		if (!is_numeric($argument)) {
			throw new InvalidUsageException();
		}
		$this->setArgument((int) $argument);
		return $this;
	}
}