<?php

namespace pocketmine\world\worldedit\pattern\logic;

use pocketmine\world\worldedit\pattern\parser\WrongPatternUsageException;
use pocketmine\world\worldedit\pattern\Pattern;
use pocketmine\world\worldedit\pattern\type\EmptyPatternData;
use pocketmine\world\worldedit\selection\Selection;
use pocketmine\world\worldedit\world\ChunkController;

class NotPattern extends Pattern
{
	use EmptyPatternData;

	/**
	 * @param Pattern $piece
	 */
	public function __construct(Pattern $piece)
	{
		parent::__construct([$piece]);
		if (count($this->pieces) !== 1) {
			throw new WrongPatternUsageException("Not needs exactly one child pattern");
		}
	}

	/**
	 * @param int             $x
	 * @param int             $y
	 * @param int             $z
	 * @param ChunkController $iterator
	 * @param Selection       $current
	 * @return int
	 */
	public function getFor(int $x, int &$y, int $z, ChunkController $iterator, Selection $current): int
	{
		return $this->pieces[0]->getFor($x, $y, $z, $iterator, $current);
	}

	/**
	 * @param int             $x
	 * @param int             $y
	 * @param int             $z
	 * @param ChunkController $iterator
	 * @param Selection       $selection
	 * @return bool
	 */
	public function isValidAt(int $x, int $y, int $z, ChunkController $iterator, Selection $selection): bool
	{
		return !$this->pieces[0]->isValidAt($x, $y, $z, $iterator, $selection);
	}
}