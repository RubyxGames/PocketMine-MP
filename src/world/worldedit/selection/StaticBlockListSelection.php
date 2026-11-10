<?php

namespace pocketmine\world\worldedit\selection;

use Closure;
use Generator;
use pocketmine\world\worldedit\selection\constructor\CubicConstructor;
use pocketmine\world\worldedit\selection\constructor\ShapeConstructor;

class StaticBlockListSelection extends ChunkManagedBlockList
{
	/**
	 * @param Closure          $closure
	 * @param SelectionContext $context
	 * @return Generator<ShapeConstructor>
	 */
	public function asShapeConstructors(Closure $closure, SelectionContext $context): Generator
	{
		yield new CubicConstructor($closure, $this->getPos1(), $this->getPos2());
	}

	public function createSafeClone(): StaticBlockListSelection
	{
		$clone = new self($this->getWorldName(), $this->getPos1(), $this->getPos2());
		foreach ($this->getManager()->getChunks() as $hash => $chunk) {
			$clone->getManager()->setChunk($hash, $chunk);
		}
		foreach ($this->getTiles($this->getPos1(), $this->getPos2()) as $tile) {
			$clone->addTile($tile);
		}
		foreach ($this->getEntities() as $entity) {
			$clone->addEntity(clone $entity);
		}
		return $clone;
	}
}