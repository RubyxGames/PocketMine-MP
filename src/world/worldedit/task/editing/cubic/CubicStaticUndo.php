<?php

namespace pocketmine\world\worldedit\task\editing\cubic;

use pocketmine\world\worldedit\selection\BlockListSelection;
use pocketmine\world\worldedit\selection\Selection;
use pocketmine\world\worldedit\selection\StaticBlockListSelection;

trait CubicStaticUndo
{
	abstract public function getTargetWorld(): string;

	abstract public function getSelection(): Selection;

	/**
	 * @return StaticBlockListSelection
	 */
	public function createUndoBlockList(): BlockListSelection
	{
		return new StaticBlockListSelection($this->getTargetWorld(), $this->getSelection()->getPos1(), $this->getSelection()->getPos2());
	}
}