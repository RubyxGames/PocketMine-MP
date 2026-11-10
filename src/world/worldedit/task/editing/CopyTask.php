<?php

namespace pocketmine\world\worldedit\task\editing;

use Generator;
use pocketmine\world\worldedit\math\OffGridBlockVector;
use pocketmine\world\worldedit\result\EditTaskResult;
use pocketmine\world\worldedit\selection\BlockListSelection;
use pocketmine\world\worldedit\selection\constructor\ShapeConstructor;
use pocketmine\world\worldedit\selection\DynamicBlockListSelection;
use pocketmine\world\worldedit\selection\NonSavingBlockListSelection;
use pocketmine\world\worldedit\selection\Selection;
use pocketmine\world\worldedit\selection\SelectionContext;
use pocketmine\world\worldedit\utils\ExtendedBinaryStream;
use pocketmine\world\worldedit\utils\TileUtils;

class CopyTask extends SelectionEditTask
{
	private DynamicBlockListSelection $result;

	/**
	 * @param Selection             $selection
	 * @param OffGridBlockVector    $position
	 * @param SelectionContext|null $context
	 */
	public function __construct(Selection $selection, private OffGridBlockVector $position, ?SelectionContext $context = null)
	{
		parent::__construct($selection, $context);
	}

	public function calculateEffectiveComplexity(): int
	{
		return (int) ceil($this->getSelection()->getSize()->volume() / 2);
	}

	protected function toTaskResult(): EditTaskResult
	{
		return new EditTaskResult($this->result->getBlockCount(), $this->result);
	}

	/**
	 * @return string
	 */
	public function getTaskName(): string
	{
		return "copy";
	}

	/**
	 * @return BlockListSelection
	 */
	public function createUndoBlockList(): BlockListSelection
	{
		return new NonSavingBlockListSelection();
	}

	/**
	 * @param EditTaskHandler $handler
	 * @return Generator<ShapeConstructor>
	 */
	public function prepareConstructors(EditTaskHandler $handler): Generator
	{
		$result = $this->result = DynamicBlockListSelection::fromWorldPositions($this->position, $this->getSelection()->getPos1(), $this->getSelection()->getPos2());
		$ox = $result->getWorldOffset()->x;
		$oy = $result->getWorldOffset()->y;
		$oz = $result->getWorldOffset()->z;
		yield from $this->getSelection()->asShapeConstructors(function (int $x, int $y, int $z) use ($ox, $oy, $oz, $handler, $result): void {
			$result->addBlock($x - $ox, $y - $oy, $z - $oz, $handler->getBlock($x, $y, $z));
			$result->addTile(TileUtils::offsetCompound($handler->getTile($x, $y, $z), -$ox, -$oy, -$oz));
		}, $this->context);
	}

	public function putData(ExtendedBinaryStream $stream): void
	{
		$stream->putBlockVector($this->position);
		parent::putData($stream);
	}

	public function parseData(ExtendedBinaryStream $stream): void
	{
		$this->position = $stream->getOffGridBlockVector();
		parent::parseData($stream);
	}
}