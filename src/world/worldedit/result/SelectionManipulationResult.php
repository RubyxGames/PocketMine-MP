<?php

namespace pocketmine\world\worldedit\result;

use pocketmine\world\worldedit\selection\identifier\BlockListSelectionIdentifier;
use pocketmine\world\worldedit\selection\identifier\SelectionSerializer;
use pocketmine\world\worldedit\utils\ExtendedBinaryStream;

class SelectionManipulationResult extends TaskResult
{
	/**
	 * @param int                          $changed
	 * @param BlockListSelectionIdentifier $result
	 */
	public function __construct(private int $changed, private BlockListSelectionIdentifier $result) { }

	public function putData(ExtendedBinaryStream $stream): void
	{
		$stream->putInt($this->changed);
		$stream->putString(SelectionSerializer::fastSerialize($this->result));
	}

	public function parseData(ExtendedBinaryStream $stream): void
	{
		$this->changed = $stream->getInt();
		$this->result = SelectionSerializer::mustGetBlockList($stream->getString());
	}

	public function storeSelections(): void
	{
		$this->result = $this->result->toIdentifier();
	}

	/**
	 * @return int
	 */
	public function getChanged(): int
	{
		return $this->changed;
	}

	/**
	 * @return BlockListSelectionIdentifier
	 */
	public function getSelection(): BlockListSelectionIdentifier
	{
		return $this->result;
	}
}