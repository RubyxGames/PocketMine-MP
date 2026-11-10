<?php

namespace pocketmine\world\worldedit\pattern;

use pocketmine\world\worldedit\pattern\block\BlockType;
use pocketmine\world\worldedit\pattern\type\EmptyPatternData;

final class PatternWrapper extends Pattern
{
	use EmptyPatternData;

	/**
	 * @param Pattern[] $pieces
	 * @return Pattern
	 */
	public static function wrap(array $pieces): Pattern
	{
		if (count($pieces) === 1 && ($pieces[0] instanceof self || $pieces[0] instanceof PatternConstruct || $pieces[0] instanceof BlockType) && $pieces[0]->getWeight() === 100) {
			return $pieces[0]; //no need to wrap single patterns
		}

		return new self($pieces);
	}
}