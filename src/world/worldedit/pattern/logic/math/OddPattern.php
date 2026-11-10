<?php

namespace pocketmine\world\worldedit\pattern\logic\math;

use pocketmine\world\worldedit\pattern\Pattern;
use pocketmine\world\worldedit\pattern\type\AxisPatternData;
use pocketmine\world\worldedit\selection\Selection;
use pocketmine\world\worldedit\world\ChunkController;

class OddPattern extends Pattern
{
	use AxisPatternData;

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
		if ($this->xAxis && abs($x) % 2 !== 1) {
			return false;
		}
		if ($this->yAxis && abs($y) % 2 !== 1) {
			return false;
		}
		if ($this->zAxis && abs($z) % 2 !== 1) {
			return false;
		}
		return true;
	}
}