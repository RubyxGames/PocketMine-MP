<?php

namespace pocketmine\world\worldedit\result;

use pocketmine\world\worldedit\selection\identifier\BlockListSelectionIdentifier;
use pocketmine\world\worldedit\selection\identifier\SelectionSerializer;
use pocketmine\world\worldedit\utils\ExtendedBinaryStream;

class EditTaskResult extends TaskResult
{
	/**
	 * @param int                 $affected
	 * @param BlockListSelectionIdentifier $selection Might be invalid, can be history or clipboard depending on the task
	 */
	public function __construct(private int $affected, private BlockListSelectionIdentifier $selection) {}

	public function putData(ExtendedBinaryStream $stream): void
	{
		$stream->putInt($this->affected);
		$stream->putString(SelectionSerializer::fastSerialize($this->selection));
	}

	public function parseData(ExtendedBinaryStream $stream): void
	{
		$this->affected = $stream->getInt();
		$this->selection = SelectionSerializer::mustGetBlockList($stream->getString());
	}

	public function storeSelections(): void
	{
		$this->selection = $this->selection->toIdentifier();
	}

	/**
	 * @return int
	 */
	public function getAffected(): int
	{
		return $this->affected;
	}

	/**
	 * @return BlockListSelectionIdentifier
	 */
	public function getSelection(): BlockListSelectionIdentifier
	{
		return $this->selection;
	}
}