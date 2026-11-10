<?php

namespace pocketmine\world\worldedit\thread\output;

use pocketmine\world\worldedit\thread\chunk\ChunkRequest;
use pocketmine\world\worldedit\thread\chunk\ChunkRequestExecutor;
use pocketmine\world\worldedit\utils\ExtendedBinaryStream;

class ChunkRequestData extends OutputData
{
	/**
	 * @param ChunkRequest $request
	 */
	public function __construct(private ChunkRequest $request) {}

	public function handle(): void
	{
		ChunkRequestExecutor::getInstance()->addRequest($this->request);
	}

	public function putData(ExtendedBinaryStream $stream): void
	{
		$this->request->putData($stream);
	}

	public function parseData(ExtendedBinaryStream $stream): void
	{
		$this->request = ChunkRequest::readFrom($stream);
	}
}