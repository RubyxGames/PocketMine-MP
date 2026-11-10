<?php

namespace pocketmine\world\worldedit\thread\input\task;

use pocketmine\world\worldedit\selection\identifier\StoredSelectionIdentifier;
use pocketmine\world\worldedit\thread\input\InputData;
use pocketmine\world\worldedit\thread\modules\StorageModule;
use pocketmine\world\worldedit\utils\ExtendedBinaryStream;

class CleanStorageTask extends InputData
{
	/**
	 * @var StoredSelectionIdentifier[]
	 */
	private array $storageIds = [];

	/**
	 * @param StoredSelectionIdentifier[] $storageIds
	 */
	public static function from(array $storageIds): void
	{
		$data = new self();
		$data->storageIds = $storageIds;
		$data->send();
	}

	public function handle(): void
	{
		foreach ($this->storageIds as $id) {
			StorageModule::cleanStored($id);
		}
	}

	public function putData(ExtendedBinaryStream $stream): void
	{
		$stream->putInt(count($this->storageIds));
		foreach ($this->storageIds as $id) {
			$stream->putString($id->fastSerialize());
		}
	}

	public function parseData(ExtendedBinaryStream $stream): void
	{
		$count = $stream->getInt();
		for ($i = 0; $i < $count; $i++) {
			$this->storageIds[] = StoredSelectionIdentifier::fastDeserialize($stream->getString());
		}
	}
}