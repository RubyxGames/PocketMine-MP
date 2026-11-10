<?php

namespace pocketmine\world\worldedit\selection\constructor;

use Closure;
use pocketmine\world\worldedit\math\BlockOffsetVector;
use pocketmine\utils\Utils;

abstract class ShapeConstructor
{
	public function __construct(protected Closure $closure)
	{
		Utils::validateCallableSignature(static function (int $x, int $y, int $z): void {}, $closure);
	}

	abstract public function getBlockCount(): int;

	abstract public function moveTo(int $chunk): void;

	/**
	 * Note: chunk is expected to be requested by appropriate tasks and withing boundaries of the shape
	 * @param int $chunk
	 * @return bool
	 */
	abstract public function needsChunk(int $chunk): bool;

	abstract public function offset(BlockOffsetVector $offset): self;
}