<?php

namespace pocketmine\world\worldedit\command\flags;

use pocketmine\world\worldedit\command\exception\PatternParseException;
use pocketmine\world\worldedit\pattern\parser\ParseError;
use pocketmine\world\worldedit\pattern\parser\PatternParser;
use pocketmine\world\worldedit\pattern\Pattern;
use pocketmine\world\worldedit\session\Session;

/**
 * @extends ValuedCommandFlag<string>
 */
class StringyPatternCommandFlag extends ValuedCommandFlag
{
	/**
	 * @param Session         $session
	 * @param string          $argument
	 * @return StringyPatternCommandFlag
	 */
	public function parseArgument(Session $session, string $argument): CommandFlag
	{
		try {
			PatternParser::validateInput($argument, $session->asPlayer());
			$this->setArgument($argument);
			return $this;
		} catch (ParseError $exception) {
			throw new PatternParseException($exception);
		}
	}
}