<?php

namespace pocketmine\world\worldedit\task\editing;

use Generator;
use pocketmine\world\worldedit\pattern\functional\GravityPattern;
use pocketmine\world\worldedit\pattern\Pattern;
use pocketmine\world\worldedit\pattern\PatternWrapper;
use pocketmine\world\worldedit\selection\BinaryBlockListStream;
use pocketmine\world\worldedit\selection\BlockListSelection;
use pocketmine\world\worldedit\selection\constructor\ShapeConstructor;
use pocketmine\world\worldedit\selection\LinearSelection;
use pocketmine\world\worldedit\selection\Selection;
use pocketmine\world\worldedit\selection\SelectionContext;
use pocketmine\world\worldedit\selection\VerticalStaticBlockListSelection;
use pocketmine\world\worldedit\task\editing\cubic\CubicStaticUndo;
use pocketmine\world\worldedit\utils\ExtendedBinaryStream;
use pocketmine\world\worldedit\world\HeightMapCache;
use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;

class SetTask extends SelectionEditTask
{
	use CubicStaticUndo {
		CubicStaticUndo::createUndoBlockList as private getDefaultBlockList;
	}

	protected Pattern $pattern;

	/**
	 * @param Selection             $selection
	 * @param Pattern               $pattern
	 * @param SelectionContext|null $context
	 */
	public function __construct(Selection $selection, Pattern $pattern, ?SelectionContext $context = null)
	{
		$pattern = PatternWrapper::wrap([$pattern]);
		$this->pattern = $pattern;
		if ($context === null) {
			$context = $pattern->getSelectionContext();
		}
		parent::__construct($selection, $context);
	}

	public function calculateEffectiveComplexity(): int
	{
		//TODO: Add complexity of patterns
		return $this->getSelection()->getSize()->volume() * 2;
	}

	/**
	 * @return string
	 */
	public function getTaskName(): string
	{
		return "set";
	}

	/**
	 * @param EditTaskHandler $handler
	 * @return Generator<ShapeConstructor>
	 */
	public function prepareConstructors(EditTaskHandler $handler): Generator
	{
		$selection = $this->getSelection();
		$pattern = $this->pattern;
		$updateHeightMap = $pattern->contains(GravityPattern::class);
		yield from $selection->asShapeConstructors(function (int $x, int $y, int $z) use ($updateHeightMap, $pattern, $selection, $handler): void {
			$block = $pattern->getFor($x, $y, $z, $handler->getOrigin(), $selection);
			if ($block !== -1) {
				$handler->changeBlock($x, $y, $z, $block);
				if ($updateHeightMap) {
					HeightMapCache::setBlockAt($x, $y, $z, $block >> Block::INTERNAL_STATE_DATA_BITS === BlockTypeIds::AIR);
				}
			}
		}, $this->context);
	}

	/**
	 * @return BlockListSelection
	 */
	public function createUndoBlockList(): BlockListSelection
	{
		if ($this->getSelection() instanceof LinearSelection) {
			return new BinaryBlockListStream($this->getTargetWorld());
		}
		if ($this->pattern->contains(GravityPattern::class)) {
			return new VerticalStaticBlockListSelection($this->getTargetWorld(), $this->getSelection()->getPos1(), $this->getSelection()->getPos2());
		}
		return $this->getDefaultBlockList();
	}

	public function putData(ExtendedBinaryStream $stream): void
	{
		parent::putData($stream);
		$stream->putString($this->pattern->fastSerialize());
	}

	public function parseData(ExtendedBinaryStream $stream): void
	{
		parent::parseData($stream);
		$this->pattern = Pattern::fastDeserialize($stream->getString());
	}
}