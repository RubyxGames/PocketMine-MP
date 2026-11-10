<?php

namespace pocketmine\world\worldedit\command\flags;

use BadMethodCallException;
use pocketmine\world\worldedit\command\exception\InvalidUsageException;
use pocketmine\world\worldedit\session\Session;
use pocketmine\world\worldedit\session\SessionManager;

/**
 * @extends ValuedCommandFlag<Session>
 */
class SessionCommandFlag extends ValuedCommandFlag
{
	/**
	 * @param Session         $session
	 * @param string          $argument
	 * @return SessionCommandFlag
	 */
	public function parseArgument(Session $session, string $argument): CommandFlag
	{
		try {
			$this->setArgument(SessionManager::get($argument, false));
		} catch (BadMethodCallException) {
			throw new InvalidUsageException();
		}
		return $this;
	}
}