<?php

namespace pocketmine\world\worldedit\selection\constructor;

use Closure;
use pocketmine\world\worldedit\math\BlockOffsetVector;
use pocketmine\world\worldedit\math\BlockVector;
use pocketmine\world\worldedit\utils\VectorUtils;
use pocketmine\world\World;

/**
 * The probably most pointless class in the world
 */
class SingleBlockConstructor extends ShapeConstructor
{
	/**
	 * @param Closure     $closure
	 * @param BlockVector $position
	 */
	public function __construct(Closure $closure, private BlockVector $position)
	{
		parent::__construct($closure);
	}

	public function getBlockCount(): int
	{
		return 1;
	}

	public function moveTo(int $chunk): void
	{
		if ($this->position->isInChunk($chunk)) {
			$closure = $this->closure;
			$closure($this->position->x, $this->position->y, $this->position->z);
		}
	}

	public function needsChunk(int $chunk): bool
	{
		return $this->position->isInChunk($chunk);
	}


	public function offset(BlockOffsetVector $offset): self
	{
		return new self($this->closure, $this->position->offset($offset));
	}
}