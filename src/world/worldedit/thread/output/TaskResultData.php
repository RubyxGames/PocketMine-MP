<?php

namespace pocketmine\world\worldedit\thread\output;

use pocketmine\world\worldedit\handler\EditHandler;
use pocketmine\world\worldedit\utils\ExtendedBinaryStream;

class TaskResultData extends OutputData
{
	public function __construct(int $taskId, private string $payload)
	{
		$this->setTaskId($taskId);
	}

	public function handle(): void
	{
		EditHandler::callback($this->getTaskId(), $this->payload);
	}

	public function putData(ExtendedBinaryStream $stream): void
	{
		$stream->putString($this->payload);
	}

	public function parseData(ExtendedBinaryStream $stream): void
	{
		$this->payload = $stream->getString();
	}
}