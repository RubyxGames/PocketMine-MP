<?php

namespace pocketmine\world\worldedit\environment;

use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\worldedit\task\editing\ChunkedTask;
use pocketmine\world\worldedit\task\editing\EditTaskHandler;
use pocketmine\world\worldedit\task\editing\GroupedChunkHandler;
use pocketmine\world\worldedit\thread\chunk\ChunkHandler;
use pocketmine\world\worldedit\thread\chunk\ChunkRequest;
use pocketmine\world\worldedit\world\ChunkController;
use pocketmine\world\worldedit\world\ChunkInformation;
use pocketmine\world\worldedit\world\ReferencedChunkManager;

abstract class ThreadEnvironmentHandler
{
	abstract public function submitResultingChunks(ChunkController $controller): void;

	/**
	 * @param string        $world
	 * @param CompoundTag[] $entities
	 * @phpstan-param list<CompoundTag> $entities
	 */
	abstract public function submitResultingEntities(string $world, array $entities): void;

	/**
	 * @param string           $world
	 * @param int              $index
	 * @param ChunkInformation $chunk
	 * @param string[]         $injections
	 */
	abstract public function submitSingleChunk(string $world, int $index, ChunkInformation $chunk, array $injections): void;

	abstract public function initChunkHandler(ChunkHandler $handler): void;

	abstract public function processChunkRequest(ChunkRequest $chunk, ChunkHandler $handler): void;

	abstract public function finalizeChunkStep(): void;

	abstract public function postProgress(float $progress): void;

	abstract public function getChunkController(ReferencedChunkManager $manager): ChunkController;

	/**
	 * @param ChunkedTask         $task
	 * @param GroupedChunkHandler $chunkHandler
	 * @param EditTaskHandler     $editHandler
	 * @param int[]               $chunks
	 */
	abstract public function executeChunkedTask(ChunkedTask $task, GroupedChunkHandler $chunkHandler, EditTaskHandler $editHandler, array $chunks): void;
}