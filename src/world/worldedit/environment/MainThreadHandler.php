<?php

namespace pocketmine\world\worldedit\environment;

use pocketmine\world\worldedit\task\editing\ChunkedTask;
use pocketmine\world\worldedit\task\editing\EditTaskHandler;
use pocketmine\world\worldedit\task\editing\GroupedChunkHandler;
use pocketmine\world\worldedit\thread\chunk\ChunkHandler;
use pocketmine\world\worldedit\thread\chunk\ChunkRequest;
use pocketmine\world\worldedit\thread\EditThread;
use pocketmine\data\SavedDataLoadingException;
use pocketmine\entity\EntityFactory;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\worldedit\WorldEdit;
use pocketmine\world\worldedit\utils\EntityUtils;
use pocketmine\world\worldedit\utils\LoaderManager;
use pocketmine\world\worldedit\world\blockupdate\InjectingData;
use Throwable;
use pocketmine\world\worldedit\world\blockupdate\InjectingSubChunkController;
use pocketmine\world\worldedit\world\ChunkController;
use pocketmine\world\worldedit\world\ChunkInformation;
use pocketmine\world\worldedit\world\HeightMapCache;
use pocketmine\world\worldedit\world\ReferencedChunkManager;
use pocketmine\block\tile\Tile;
use pocketmine\Server;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\world\World;
use UnexpectedValueException;

/**
 * The main thread processes the tasks synchronously
 * Only very short tasks should be processed in the main thread
 */
class MainThreadHandler extends ThreadEnvironmentHandler
{
	/**
	 * @var ChunkRequest[]
	 */
	private array $chunkRequestQueue = [];
	private bool $isChunkPaused = false;

	public function submitResultingChunks(ChunkController $controller): void
	{
		if (!$controller instanceof InjectingSubChunkController) {
			$injections = [];
		} else {
			$injections = array_map(static function (InjectingData $injection) {
				return $injection->toProtocol();
			}, $controller->getInjections());
		}
		LoaderManager::setChunks($controller->getManager()->getWorld(), $controller->getManager()->getModifiedChunks(), $injections);
	}

	/**
	 * @param string        $world
	 * @param CompoundTag[] $entities
	 * @phpstan-param list<CompoundTag> $entities
	 */
	public function submitResultingEntities(string $world, array $entities): void
	{
		$w = Server::getInstance()->getWorldManager()->getWorldByName($world);
		if ($w === null) {
			return;
		}
		$factory = EntityFactory::getInstance();
		foreach ($entities as $nbt) {
			try {
				$pos = EntityUtils::readPos($nbt);
				if ($pos !== null) {
					$w->loadChunk(((int) $pos[0]) >> 4, ((int) $pos[2]) >> 4);
				}
				$factory->createFromData($w, $nbt);
			} catch (SavedDataLoadingException|Throwable $e) {
				WorldEdit::getInstance()->getLogger()->debug("Failed to spawn pasted entity: " . $e->getMessage());
			}
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
		$w = Server::getInstance()->getWorldManager()->getWorldByName($world);
		if ($w === null) {
			throw new UnexpectedValueException("World " . $world . " was deleted, unloaded or renamed");
		}
		LoaderManager::setChunks($w, [$index => $chunk], $injections);
	}

	public function initChunkHandler(ChunkHandler $handler): void {
		$this->chunkRequestQueue = [];
		$this->isChunkPaused = false;
	}

	public function processChunkRequest(ChunkRequest $chunk, ChunkHandler $handler): void
	{
		if ($this->isChunkPaused) {
			$this->chunkRequestQueue[] = $chunk;
		} else {
			$this->executeChunkRequest($chunk, $handler);
		}
	}

	public function executeChunkRequest(ChunkRequest $chunk, ChunkHandler $handler): void
	{
		World::getXZ($chunk->getChunk(), $x, $z);
		if ($chunk->getWorld()->isChunkLoaded($x, $z)) {
			World::getXZ($chunk->getChunk(), $x, $z);
			$c = $chunk->getWorld()->getChunk($x, $z);
			if (!$c instanceof Chunk) {
				throw new AssumptionFailedError();
			}
			$tiles = array_map(static function (Tile $tile) {
				return $tile->saveNBT();
			}, $c->getTiles());
			$c = clone $c;
			foreach ($c->getTiles() as $tile) {
				$c->removeTile($tile);
			}
			$handler->handleInput($chunk->getChunk(), new ChunkInformation($c, $tiles), $chunk->getPayload());
		} else {
			EditThread::getInstance()->getLogger()->error("Chunk " . $chunk->getChunk() . " was unloaded while processing");
		}
	}

	public function finalizeChunkStep(): void { }

	public function postProgress(float $progress): void { }

	public function getChunkController(ReferencedChunkManager $manager): ChunkController
	{
		return new InjectingSubChunkController($manager);
	}

	/**
	 * @param ChunkedTask         $task
	 * @param GroupedChunkHandler $chunkHandler
	 * @param EditTaskHandler     $editHandler
	 * @param int[]               $chunks
	 */
	public function executeChunkedTask(ChunkedTask $task, GroupedChunkHandler $chunkHandler, EditTaskHandler $editHandler, array $chunks): void
	{
		$constructors = iterator_to_array($task->prepareConstructors($editHandler), false);
		$this->isChunkPaused = true;
		$chunkHandler->requestAll($chunks, $constructors);
		$this->isChunkPaused = false;
		//Process all at once so relations are correct
		foreach ($this->chunkRequestQueue as $chunk) {
			$this->executeChunkRequest($chunk, $chunkHandler);
		}
		while (($key = $chunkHandler->getNextChunk()) !== null) {
			foreach ($chunkHandler->getData() as $k => $information) {
				$editHandler->setChunk($k, $information);
			}

			HeightMapCache::prepare();

			foreach ($constructors as $constructor) {
				$constructor->moveTo($key);
			}

			$editHandler->finish();
		}
	}
}