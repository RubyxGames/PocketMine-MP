<?php

namespace pocketmine\world\worldedit\task\editing;

use Generator;
use InvalidArgumentException;
use pocketmine\world\worldedit\selection\BinaryBlockListStream;
use pocketmine\world\worldedit\selection\BlockListSelection;
use pocketmine\world\worldedit\selection\constructor\RawShapeConstructor;
use pocketmine\world\worldedit\selection\constructor\ShapeConstructor;
use pocketmine\world\worldedit\selection\identifier\BlockListSelectionIdentifier;
use pocketmine\world\worldedit\selection\StaticBlockListSelection;
use pocketmine\world\worldedit\task\editing\cubic\CubicStaticUndo;
use pocketmine\world\worldedit\task\EditThreadExclusive;
use pocketmine\world\worldedit\utils\VectorUtils;
use pocketmine\world\World;

class StaticPasteTask extends SelectionEditTask
{
	use CubicStaticUndo {
		CubicStaticUndo::createUndoBlockList as private getDefaultBlockList;
	}
	use EditThreadExclusive;

	/**
	 * @param BlockListSelectionIdentifier $selection
	 */
	public function __construct(BlockListSelectionIdentifier $selection)
	{
		parent::__construct($selection);
	}

	/**
	 * @return string
	 */
	public function getTaskName(): string
	{
		return "static_paste";
	}

	/**
	 * @return StaticBlockListSelection|BinaryBlockListStream
	 */
	public function getSelection(): StaticBlockListSelection|BinaryBlockListStream
	{
		$sel = parent::getSelection();
		if (!$sel instanceof StaticBlockListSelection && !$sel instanceof BinaryBlockListStream) {
			throw new InvalidArgumentException("Selection must be a static block list");
		}
		return $sel;
	}

	/**
	 * @param EditTaskHandler $handler
	 * @return Generator<ShapeConstructor>
	 */
	public function prepareConstructors(EditTaskHandler $handler): Generator
	{
		$selection = $this->getSelection();
		if ($selection instanceof BinaryBlockListStream) {
			//WARNING: This isn't the default closure style
			yield from $selection->asShapeConstructors(function (int $x, int $y, int $z, int $block) use ($handler): void {
				$handler->changeBlock($x, $y, $z, $block);
			}, $this->context);
		} else {
			yield from $selection->asShapeConstructors(function (int $x, int $y, int $z) use ($handler, $selection): void {
				$block = $selection->getIterator()->getBlock($x, $y, $z);
				if ($block !== 0) {
					$handler->changeBlock($x, $y, $z, $block);
				}
			}, $this->context);
		}
		yield new RawShapeConstructor(function (int $chunk) use ($handler, $selection): void {
			$min = VectorUtils::getChunkPosition($chunk);
			$max = $min->add(15, World::Y_MAX - World::Y_MIN - 1, 15);
			foreach ($selection->getTiles($min, $max) as $tile) {
				$handler->addTile($tile);
			}
			foreach ($selection->getEntitiesInBounds($min, $max) as $entity) {
				$handler->addEntity(clone $entity);
			}
		}, false);
	}

	public function createUndoBlockList(): BlockListSelection
	{
		return $this->getSelection() instanceof BinaryBlockListStream ? new BinaryBlockListStream($this->getTargetWorld()) : $this->getDefaultBlockList();
	}
}