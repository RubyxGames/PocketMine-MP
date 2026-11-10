<?php

namespace pocketmine\world\worldedit\pattern\logic\selection;

use pocketmine\world\worldedit\pattern\parser\ParseError;
use pocketmine\world\worldedit\pattern\Pattern;
use pocketmine\world\worldedit\selection\Cube;
use pocketmine\world\worldedit\selection\Cylinder;
use pocketmine\world\worldedit\selection\Selection;
use pocketmine\world\worldedit\selection\SelectionContext;
use pocketmine\world\worldedit\selection\Sphere;
use pocketmine\world\worldedit\utils\ExtendedBinaryStream;
use pocketmine\world\worldedit\world\ChunkController;

class WallPattern extends Pattern
{
	/**
	 * @param float     $thickness
	 * @param Pattern[] $pieces
	 */
	public function __construct(private float $thickness, array $pieces)
	{
		parent::__construct($pieces);
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
		if ($selection instanceof Cube) {
			$min = $selection->getPos1();
			$max = $selection->getPos2();

			return ($x - $min->x + 1) <= $this->thickness || ($max->x - $x + 1) <= $this->thickness || ($z - $min->z + 1) <= $this->thickness || ($max->z - $z + 1) <= $this->thickness;
		}
		if ($selection instanceof Cylinder || $selection instanceof Sphere) {
			return (($x - $selection->getPoint()->x) ** 2) + (($z - $selection->getPoint()->z) ** 2) > (($selection->getRadius() - $this->thickness) ** 2);
		}
		throw new ParseError("Walls pattern does not support selection of type " . $selection::class);
	}

	/**
	 * @param SelectionContext $context
	 */
	public function applySelectionContext(SelectionContext $context): void
	{
		$context->includeWalls($this->thickness);
	}

	/**
	 * @param ExtendedBinaryStream $stream
	 * @return void
	 */
	public function putData(ExtendedBinaryStream $stream): void
	{
		$stream->putFloat($this->thickness);
	}

	/**
	 * @param ExtendedBinaryStream $stream
	 * @return void
	 */
	public function parseData(ExtendedBinaryStream $stream): void
	{
		$this->thickness = $stream->getFloat();
	}
}