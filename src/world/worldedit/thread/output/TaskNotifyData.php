<?php

namespace pocketmine\world\worldedit\thread\output;

use pocketmine\world\worldedit\handler\EditHandler;
use pocketmine\world\worldedit\utils\ExtendedBinaryStream;

class TaskNotifyData extends OutputData
{
	public function __construct(private int $value) {}

	public function handle(): void
	{
		EditHandler::notify($this);
	}

	public function putData(ExtendedBinaryStream $stream): void
	{
		$stream->putInt($this->value);
	}

	public function parseData(ExtendedBinaryStream $stream): void
	{
		$this->value = $stream->getInt();
	}

	/**
	 * @return int
	 */
	public function getPayload(): int
	{
		return $this->value;
	}
}