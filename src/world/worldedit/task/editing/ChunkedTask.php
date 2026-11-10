<?php

namespace pocketmine\world\worldedit\task\editing;

use Generator;
use pocketmine\world\worldedit\selection\constructor\ShapeConstructor;

interface ChunkedTask
{
	/**
	 * @param EditTaskHandler $handler
	 * @return Generator<ShapeConstructor>
	 */
	public function prepareConstructors(EditTaskHandler $handler): Generator;
}