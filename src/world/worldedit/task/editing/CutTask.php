<?php

namespace pocketmine\world\worldedit\task\editing;

use Generator;
use pocketmine\world\worldedit\math\OffGridBlockVector;
use pocketmine\world\worldedit\result\CuttingTaskResult;
use pocketmine\world\worldedit\selection\constructor\ShapeConstructor;
use pocketmine\world\worldedit\selection\DynamicBlockListSelection;
use pocketmine\world\worldedit\selection\Selection;
use pocketmine\world\worldedit\task\editing\cubic\CubicStaticUndo;
use pocketmine\world\worldedit\utils\ExtendedBinaryStream;
use pocketmine\world\worldedit\utils\TileUtils;
use pocketmine\block\VanillaBlocks;

class CutTask extends SelectionEditTask
{
	use CubicStaticUndo;

	private DynamicBlockListSelection $result;

	/**
	 * @param Selection          $selection
	 * @param OffGridBlockVector $position
	 */
	public function __construct(Selection $selection, private OffGridBlockVector $position)
	{
		parent::__construct($selection);
	}

	public function calculateEffectiveComplexity(): int
	{
		return $this->getSelection()->getSize()->volume();
	}

	/**
	 * @return string
	 */
	public function getTaskName(): string
	{
		return "cut";
	}

	protected function toTaskResult(): CuttingTaskResult
	{
		return new CuttingTaskResult($this->handler->getChangedBlockCount(), $this->undo, $this->result);
	}

	/**
	 * @param EditTaskHandler $handler
	 * @return Generator<ShapeConstructor>
	 */
	public function prepareConstructors(EditTaskHandler $handler): Generator
	{
		$result = $this->result = DynamicBlockListSelection::fromWorldPositions($this->position, $this->getSelection()->getPos1(), $this->getSelection()->getPos2());;
		$id = VanillaBlocks::AIR()->getStateId();
		$ox = $result->getWorldOffset()->x;
		$oy = $result->getWorldOffset()->y;
		$oz = $result->getWorldOffset()->z;

		yield from $this->getSelection()->asShapeConstructors(function (int $x, int $y, int $z) use ($id, $handler, $result, $ox, $oy, $oz): void {
			$result->addBlock($x - $ox, $y - $oy, $z - $oz, $handler->getBlock($x, $y, $z));
			$result->addTile(TileUtils::offsetCompound($handler->getTile($x, $y, $z), -$ox, -$oy, -$oz));
			$handler->changeBlock($x, $y, $z, $id);
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