<?php

namespace pocketmine\world\worldedit\task\schematic;

use pocketmine\world\worldedit\result\EditTaskResult;
use pocketmine\world\worldedit\schematic\SchematicFileAdapter;
use pocketmine\world\worldedit\selection\DynamicBlockListSelection;
use pocketmine\world\worldedit\selection\identifier\StoredSelectionIdentifier;
use pocketmine\world\worldedit\task\EditThreadExclusive;
use pocketmine\world\worldedit\task\ExecutableTask;
use pocketmine\world\worldedit\utils\ExtendedBinaryStream;

/**
 * @extends ExecutableTask<EditTaskResult>
 */
class SchematicLoadTask extends ExecutableTask
{
	use EditThreadExclusive;

	/**
	 * @param string $schematicPath
	 */
	public function __construct(private string $schematicPath)
	{
		parent::__construct();
	}

	/**
	 * @return string
	 */
	public function getTaskName(): string
	{
		return "schematic_load";
	}

	protected function executeInternal(): EditTaskResult
	{
		$selection = DynamicBlockListSelection::empty();
		SchematicFileAdapter::readIntoSelection($this->schematicPath, $selection);
		return new EditTaskResult($selection->getIterator()->getWrittenBlockCount(), $selection);
	}

	public function attemptRecovery(): EditTaskResult
	{
		return new EditTaskResult(0, StoredSelectionIdentifier::invalid());
	}

	public function putData(ExtendedBinaryStream $stream): void
	{
		$stream->putString($this->schematicPath);
	}

	public function parseData(ExtendedBinaryStream $stream): void
	{
		$this->schematicPath = $stream->getString();
	}
}