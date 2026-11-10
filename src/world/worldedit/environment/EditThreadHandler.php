<?php

namespace pocketmine\world\worldedit\environment;

use pocketmine\world\worldedit\task\CancelException;
use pocketmine\world\worldedit\task\editing\ChunkedTask;
use pocketmine\world\worldedit\task\editing\EditTaskHandler;
use pocketmine\world\worldedit\task\editing\GroupedChunkHandler;
use pocketmine\world\worldedit\thread\chunk\ChunkHandler;
use pocketmine\world\worldedit\thread\chunk\ChunkRequest;
use pocketmine\world\worldedit\thread\chunk\ChunkRequestManager;
use pocketmine\world\worldedit\thread\EditThread;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\worldedit\thread\output\ResultingChunkData;
use pocketmine\world\worldedit\thread\output\ResultingEntityData;
use pocketmine\world\worldedit\world\blockupdate\InjectingData;
use pocketmine\world\worldedit\world\blockupdate\InjectingSubChunkController;
use pocketmine\world\worldedit\world\ChunkController;
use pocketmine\world\worldedit\world\ChunkInformation;
use pocketmine\world\worldedit\world\HeightMapCache;
use pocketmine\world\worldedit\world\ReferencedChunkManager;

/**
 * The edit thread processes the tasks asynchronously
 */
class EditThreadHandler extends ThreadEnvironmentHandler
{
	public function submitResultingChunks(ChunkController $controller): void
	{
		if (!$controller instanceof InjectingSubChunkController) {
			$injections = [];
		} else {
			$injections = array_map(static function (InjectingData $injection) {
				return $injection->toProtocol();
			}, $controller->getInjections());
		}
		$data = new ResultingChunkData($controller->getManager()->getWorldName(), $controller->getManager()->getModifiedChunks(), $injections);
		if ($data->checkSend()) {
			EditThread::getInstance()->sendOutput($data);
		}
	}

	/**
	 * @param string        $world
	 * @param CompoundTag[] $entities
	 * @phpstan-param list<CompoundTag> $entities
	 */
	public function submitResultingEntities(string $world, array $entities): void
	{
		$data = new ResultingEntityData($world, $entities);
		if ($data->checkSend()) {
			EditThread::getInstance()->sendOutput($data);
		}
	}

	/**
	 * @param string           $world
	 * @param int              $index
	 * @param ChunkInformation $chunk
	 * @param string[]         $injections
	 */
	public function submitSingleChunk(string $world, int $index, ChunkInformation $chunk, array $injections): void
	{
		EditThread::getInstance()->sendOutput(new ResultingChunkData($world, [$index => $chunk], $injections));
	}

	public function initChunkHandler(ChunkHandler $handler): void
	{
		ChunkRequestManager::setHandler($handler);
	}

	public function processChunkRequest(ChunkRequest $chunk, ChunkHandler $handler): void
	{
		ChunkRequestManager::addRequest($chunk);
	}

	public function finalizeChunkStep(): void
	{
		ChunkRequestManager::markAsDone();
	}

	public function postProgress(float $progress): void
	{
		EditThread::getInstance()->getStats()->updateProgress($progress);
	}

	public function getChunkController(ReferencedChunkManager $manager): ChunkController
	{
		return new ChunkController($manager);
	}

	/**
	 * @param ChunkedTask         $task
	 * @param GroupedChunkHandler $chunkHandler
	 * @param EditTaskHandler     $editHandler
	 * @param int[]               $chunks
	 * @throws CancelException
	 */
	public function executeChunkedTask(ChunkedTask $task, GroupedChunkHandler $chunkHandler, EditTaskHandler $editHandler, array $chunks): void
	{
		$constructors = iterator_to_array($task->prepareConstructors($editHandler), false);
		$left = $total = $chunkHandler->requestAll($chunks, $constructors);
		while (true) {
			EditThread::getInstance()->checkExecution();
			if (($key = $chunkHandler->getNextChunk()) !== null) {
				$left--;

				foreach ($chunkHandler->getData() as $k => $information) {
					$editHandler->setChunk($k, $information);
				}

				HeightMapCache::prepare();

				foreach ($constructors as $constructor) {
					$constructor->moveTo($key);
				}

				EditThread::getInstance()->debug("Chunk " . $key . " was edited successful, " . $left . " chunks left");
				$this->postProgress(($total - $left) / $total);

				$editHandler->finish();
			}
			if ($left <= 0) {
				break;
			}
			if ($chunkHandler->getNextChunk() === null) {
				EditThread::getInstance()->waitForData();
			} else {
				EditThread::getInstance()->parseInput();
			}
		}
	}
}