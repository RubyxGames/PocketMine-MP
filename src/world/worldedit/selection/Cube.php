<?php

namespace pocketmine\world\worldedit\selection;

use Closure;
use Generator;
use pocketmine\world\worldedit\selection\constructor\CubicConstructor;
use pocketmine\world\worldedit\selection\constructor\ShapeConstructor;
use pocketmine\world\worldedit\selection\cubic\CubicChunkLoader;
use pocketmine\math\Facing;

class Cube extends Selection
{
	use CubicChunkLoader;

	/**
	 * @param Closure          $closure
	 * @param SelectionContext $context
	 * @return Generator<ShapeConstructor>
	 */
	public function asShapeConstructors(Closure $closure, SelectionContext $context): Generator
	{
		if ($context->isEmpty()) {
			return;
		}

		if ($context->isFull()) {
			yield new CubicConstructor($closure, $this->getPos1(), $this->getPos2());
			return;
		}

		if ($context->includesFilling()) {
			//This can also make the selection larger (1x1 -> -3x-3), so we are not allowed to actually check for the smaller/larger position
			yield new CubicConstructor($closure, $this->getPos1()->add(1, 1, 1), $this->getPos2()->add(-1, -1, -1));
		}

		if ($context->includesAllSides()) {
			yield from CubicConstructor::forSides($this->getPos1(), $this->getPos2(), Facing::ALL, (int) $context->getSideThickness(), $closure);
		} elseif ($context->includesWalls()) {
			yield from CubicConstructor::forSides($this->getPos1(), $this->getPos2(), Facing::HORIZONTAL, (int) $context->getSideThickness(), $closure);
		}

		if ($context->includesCenter()) {
			yield new CubicConstructor($closure, $this->getFloorCenter(), $this->getCeilCenter());
		}
	}
}