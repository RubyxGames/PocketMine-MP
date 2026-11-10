<?php

namespace pocketmine\world\worldedit\command\flags;

use pocketmine\world\worldedit\command\exception\PatternParseException;
use pocketmine\world\worldedit\pattern\block\BlockType;
use pocketmine\world\worldedit\pattern\parser\ParseError;
use pocketmine\world\worldedit\pattern\parser\PatternParser;
use pocketmine\world\worldedit\session\Session;
use pocketmine\data\bedrock\block\convert\UnsupportedBlockStateException;

//TODO: Turn this into masks, as soon as they are implemented

/**
 * @extends ValuedCommandFlag<BlockType>
 */
class BlockCommandFlag extends ValuedCommandFlag
{
	/**
	 * @param Session         $session
	 * @param string          $argument
	 * @return BlockCommandFlag
	 */
	public function parseArgument(Session $session, string $argument): CommandFlag
	{
		try {
			$this->setArgument(PatternParser::getBlockType($argument, true));
			return $this;
		} catch (ParseError|UnsupportedBlockStateException $exception) {
			throw new PatternParseException(new ParseError($exception->getMessage()));
		}
	}
}