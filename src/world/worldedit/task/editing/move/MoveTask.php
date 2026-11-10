<?php

namespace pocketmine\world\worldedit\task\editing\move;

use Generator;
use pocketmine\world\worldedit\math\BlockOffsetVector;
use pocketmine\world\worldedit\math\BlockVector;
use pocketmine\world\worldedit\selection\BlockListSelection;
use pocketmine\world\worldedit\selection\constructor\ShapeConstructor;
use pocketmine\world\worldedit\selection\Selection;
use pocketmine\world\worldedit\selection\StaticBlockListSelection;
use pocketmine\world\worldedit\task\editing\EditTaskHandler;
use pocketmine\world\worldedit\task\editing\GroupedChunkHandler;
use pocketmine\world\worldedit\task\editing\SelectionEditTask;
use pocketmine\world\worldedit\utils\ExtendedBinaryStream;
use pocketmine\block\VanillaBlocks;
use pocketmine\world\World;

class MoveTask extends SelectionEditTask
{
	/**
	 * @param Selection         $selection
	 * @param BlockOffsetVector $direction
	 */
	public function __construct(Selection $selection, private BlockOffsetVector $direction)
	{
		parent::__construct($selection);
	}

	public function calculateEffectiveComplexity(): int
	{
		return $this->getSelection()->getSize()->volume() * 2;
	}

	/**
	 * @return string
	 */
	public function getTaskName(): string
	{
		return "move";
	}

	/**
	 * @param EditTaskHandler $handler
	 * @return Generator<ShapeConstructor>
	 */
	public function prepareConstructors(EditTaskHandler $handler): Generator
	{
		$dx = $this->direction->x;
		$dy = $this->direction->y;
		$dz = $this->direction->z;
		//TODO: change order of iteration to optimize performance
		$air = VanillaBlocks::AIR()->getStateId();
		yield from $this->getSelection()->asShapeConstructors(function (int $x, int $y, int $z) use ($handler, $air): void {
			$handler->changeBlock($x, $y, $z, $air); //Make sure we don't overwrite anything
		}, $this->context);
		yield from $this->getSelection()->asShapeConstructors(function (int $x, int $y, int $z) use ($handler, $dx, $dy, $dz): void {
			$handler->copyBlock($x + $dx, $y + $dy, $z + $dz, $x, $y, $z, false);
		}, $this->context);
	}

	protected function sortChunks(array $chunks): array
	{
		if ($this->direction->x === 0 && $this->direction->z !== 0) {
			$z = array_map(static function (int $c): int {
				World::getXZ($c, $x, $z);
				return $z;
			}, $chunks);
			array_multisort($z, $this->direction->z > 0 ? SORT_DESC : SORT_ASC, $chunks);
			return $chunks;
		}
		$x = array_map(static function (int $c): int {
			World::getXZ($c, $x, $z);
			return $x;
		}, $chunks);
		$z = array_map(static function (int $c): int {
			World::getXZ($c, $x, $z);
			return $z;
		}, $chunks);
		array_multisort($x, $this->direction->x > 0 ? SORT_DESC : SORT_ASC, $z, $this->direction->z > 0 ? SORT_DESC : SORT_ASC, $chunks);
		return $chunks;
	}

	public function getChunkHandler(): GroupedChunkHandler
	{
		return new MovingChunkHandler($this->getTargetWorld(), $this->getSelection(), $this->direction);
	}

	/**
	 * @return BlockListSelection
	 */
	public function createUndoBlockList(): BlockListSelection
	{
		return new StaticBlockListSelection($this->getTargetWorld(), BlockVector::minComponents($this->getSelection()->getPos1(), $this->getSelection()->getPos1()->offset($this->direction)), BlockVector::maxComponents($this->getSelection()->getPos2(), $this->getSelection()->getPos2()->offset($this->direction)));
	}

	public function putData(ExtendedBinaryStream $stream): void
	{
		parent::putData($stream);
		$stream->putBlockVector($this->direction);
	}

	public function parseData(ExtendedBinaryStream $stream): void
	{
		parent::parseData($stream);
		$this->direction = $stream->getOffsetVector();
	}
}