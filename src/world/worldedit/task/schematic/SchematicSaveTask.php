<?php

namespace pocketmine\world\worldedit\task\schematic;

use pocketmine\world\worldedit\result\SelectionManipulationResult;
use pocketmine\world\worldedit\schematic\SchematicFileAdapter;
use pocketmine\world\worldedit\selection\identifier\StoredSelectionIdentifier;
use pocketmine\world\worldedit\task\EditThreadExclusive;
use pocketmine\world\worldedit\task\ExecutableTask;
use pocketmine\world\worldedit\thread\modules\StorageModule;
use pocketmine\world\worldedit\utils\ExtendedBinaryStream;

/**
 * @extends ExecutableTask<SelectionManipulationResult>
 */
class SchematicSaveTask extends ExecutableTask
{
	use EditThreadExclusive;

	/**
	 * @param StoredSelectionIdentifier $saveId
	 * @param string                    $schematicPath
	 */
	public function __construct(private StoredSelectionIdentifier $saveId, private string $schematicPath)
	{
		parent::__construct();
	}

	/**
	 * @return string
	 */
	public function getTaskName(): string
	{
		return "schematic_save";
	}

	protected function executeInternal(): SelectionManipulationResult
	{
		$selection = StorageModule::mustGetDynamic($this->saveId);
		SchematicFileAdapter::createFromSelection($this->schematicPath, $selection);
		return new SelectionManipulationResult($selection->getIterator()->getReadBlockCount(), StoredSelectionIdentifier::invalid());
	}

	public function attemptRecovery(): SelectionManipulationResult
	{
		return new SelectionManipulationResult(0, StoredSelectionIdentifier::invalid());
	}

	public function putData(ExtendedBinaryStream $stream): void
	{
		$stream->putString($this->schematicPath);
		$stream->putString($this->saveId->fastSerialize());
	}

	public function parseData(ExtendedBinaryStream $stream): void
	{
		$this->schematicPath = $stream->getString();
		$this->saveId = StoredSelectionIdentifier::fastDeserialize($stream->getString());
	}
}