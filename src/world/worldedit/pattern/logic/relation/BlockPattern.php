<?php

namespace pocketmine\world\worldedit\pattern\logic\relation;

use pocketmine\world\worldedit\pattern\Pattern;
use pocketmine\world\worldedit\pattern\type\BlockPatternData;
use pocketmine\world\worldedit\selection\Selection;
use pocketmine\world\worldedit\world\ChunkController;

class BlockPattern extends Pattern
{
	use BlockPatternData;

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
		return $this->block->equals($iterator->getBlock($x, $y, $z));
	}
}