<?php

namespace pocketmine\world\worldedit\pattern\logic\selection;

use pocketmine\world\worldedit\pattern\parser\ParseError;
use pocketmine\world\worldedit\pattern\Pattern;
use pocketmine\world\worldedit\pattern\type\EmptyPatternData;
use pocketmine\world\worldedit\selection\Cube;
use pocketmine\world\worldedit\selection\Cylinder;
use pocketmine\world\worldedit\selection\Selection;
use pocketmine\world\worldedit\selection\SelectionContext;
use pocketmine\world\worldedit\selection\Sphere;
use pocketmine\world\worldedit\world\ChunkController;

class CenterPattern extends Pattern
{
	use EmptyPatternData;

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
		if ($selection instanceof Cube) {
			$min = $selection->getPos1();
			$max = $selection->getPos2();

			$xPos = ($min->x + $max->x) / 2;
			$yPos = ($min->y + $max->y) / 2;
			$zPos = ($min->z + $max->z) / 2;

			return floor($xPos) <= $x && $x <= ceil($xPos) && floor($yPos) <= $y && $y <= ceil($yPos) && floor($zPos) <= $z && $z <= ceil($zPos);
		}
		if ($selection instanceof Cylinder || $selection instanceof Sphere) {
			return $x === $selection->getPoint()->x && $y === $selection->getPoint()->y && $z === $selection->getPoint()->z;
		}
		throw new ParseError("Center pattern does not support selection of type " . $selection::class);
	}

	/**
	 * @param SelectionContext $context
	 */
	public function applySelectionContext(SelectionContext $context): void
	{
		$context->includeCenter();
	}
}