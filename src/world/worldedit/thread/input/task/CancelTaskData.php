<?php

namespace pocketmine\world\worldedit\thread\input\task;

use pocketmine\world\worldedit\session\SessionIdentifier;
use pocketmine\world\worldedit\thread\input\InputData;
use pocketmine\world\worldedit\thread\ThreadData;
use pocketmine\world\worldedit\utils\ExtendedBinaryStream;

class CancelTaskData extends InputData
{
	private SessionIdentifier $identifier;

	public static function from(SessionIdentifier $identifier): void
	{
		$data = new self();
		$data->identifier = $identifier;
		$data->send();
	}

	public function handle(): void
	{
		ThreadData::requirePause($this->identifier);
	}

	public function putData(ExtendedBinaryStream $stream): void
	{
		$stream->putString($this->identifier->fastSerialize());
	}

	public function parseData(ExtendedBinaryStream $stream): void
	{
		$this->identifier = SessionIdentifier::fastDeserialize($stream->getString());
	}
}