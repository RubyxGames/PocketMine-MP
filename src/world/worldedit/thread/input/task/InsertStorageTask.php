<?php

namespace pocketmine\world\worldedit\thread\input\task;

use pocketmine\world\worldedit\selection\BlockListSelection;
use pocketmine\world\worldedit\selection\identifier\StoredSelectionIdentifier;
use pocketmine\world\worldedit\thread\input\InputData;
use pocketmine\world\worldedit\thread\modules\StorageModule;
use pocketmine\world\worldedit\utils\ExtendedBinaryStream;

class InsertStorageTask extends InputData
{
	private StoredSelectionIdentifier $storageId;
	private BlockListSelection $selection;

	/**
	 * @param StoredSelectionIdentifier $storageId
	 * @param BlockListSelection        $selection
	 */
	public static function from(StoredSelectionIdentifier $storageId, BlockListSelection $selection): void
	{
		$data = new self();
		$data->storageId = $storageId;
		$data->selection = $selection;
		$data->send();
	}

	public function handle(): void
	{
		StorageModule::forceStore($this->storageId, $this->selection);
	}

	public function putData(ExtendedBinaryStream $stream): void
	{
		$stream->putString($this->storageId->fastSerialize());
		$stream->putString($this->selection->fastSerialize());
	}

	public function parseData(ExtendedBinaryStream $stream): void
	{
		$this->storageId = StoredSelectionIdentifier::fastDeserialize($stream->getString());
		$this->selection = BlockListSelection::fastDeserialize($stream->getString());
	}
}