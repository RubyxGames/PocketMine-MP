<?php

namespace pocketmine\world\worldedit\pattern;

use Exception;
use pocketmine\world\worldedit\pattern\type\EmptyPatternData;
use pocketmine\world\worldedit\selection\Selection;
use pocketmine\world\worldedit\world\ChunkController;
use pocketmine\utils\AssumptionFailedError;

final class PatternConstruct extends Pattern
{
	use EmptyPatternData;

	/**
	 * @param Pattern[] $pieces
	 * @return Pattern
	 */
	public static function wrap(array $pieces): Pattern
	{
		if (count($pieces) === 1) {
			return $pieces[0]; //no need to wrap single patterns
		}

		return new self($pieces);
	}

	public function getFor(int $x, int &$y, int $z, ChunkController $iterator, Selection $current): int
	{
		foreach ($this->pieces as $piece) {
			if ($piece->isValidAt($x, $y, $z, $iterator, $current) && ($piece->getWeight() === 100 || random_int(1, 100) <= $piece->getWeight())) {
				return $piece->getFor($x, $y, $z, $iterator, $current);
			}
		}
		return -1;
	}
}