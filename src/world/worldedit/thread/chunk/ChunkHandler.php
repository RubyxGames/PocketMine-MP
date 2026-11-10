<?php

namespace pocketmine\world\worldedit\thread\chunk;

use pocketmine\world\worldedit\world\ChunkInformation;

interface ChunkHandler
{
	public function request(int $chunk): void;

	/**
	 * @param int              $chunk
	 * @param ChunkInformation $data
	 * @param int|null         $payload
	 */
	public function handleInput(int $chunk, ChunkInformation $data, ?int $payload): void;

	public function clear(): void;
}