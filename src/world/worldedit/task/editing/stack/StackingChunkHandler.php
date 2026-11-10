<?php

namespace pocketmine\world\worldedit\task\editing\stack;

use pocketmine\world\worldedit\selection\Selection;
use pocketmine\world\worldedit\task\editing\GroupedChunkHandler;
use pocketmine\Server;
use pocketmine\world\World;

abstract class StackingChunkHandler extends GroupedChunkHandler
{
	/**
	 * @param Selection $selection
	 * @return bool
	 */
	public function checkLoaded(Selection $selection): bool
	{
		$world = Server::getInstance()->getWorldManager()->getWorldByName($this->world);
		if ($world === null) {
			return false;
		}
		foreach ($selection->getNeededChunks() as $chunk) {
			World::getXZ($chunk, $x, $z);
			if (!$world->isChunkLoaded($x, $z)) {
				return false;
			}
		}
		$min = $selection->getPos1()->toChunk();
		$max = $selection->getPos2()->toChunk();
		for ($x = $min->x; $x <= $max->x; $x++) {
			for ($z = $min->z; $z <= $max->z; $z++) {
				if (!$world->isChunkLoaded($x, $z)) {
					return false;
				}
			}
		}
		return true;
	}
}