<?php

namespace pocketmine\world\worldedit\thread\chunk;

use pocketmine\world\worldedit\session\SessionIdentifier;
use pocketmine\world\worldedit\thread\EditThread;
use pocketmine\world\worldedit\thread\input\ChunkInputData;
use pocketmine\world\worldedit\thread\input\task\CancelTaskData;
use pocketmine\world\worldedit\utils\ExtendedBinaryStream;
use pocketmine\world\worldedit\utils\LoaderManager;
use pocketmine\world\worldedit\world\ChunkInformation;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\World;
use Throwable;

class ChunkRequestExecutor
{
	use SingletonTrait;

	public function addRequest(ChunkRequest $request): void
	{
		World::getXZ($request->getChunk(), $x, $z);

		try {
			$request->getWorld();
		} catch (Throwable $e) {
			EditThread::getInstance()->getLogger()->warning($e->getMessage());
			CancelTaskData::from(SessionIdentifier::internal("Chunkloading Failed"));
			return;
		}

		if ($request->getWorld()->isChunkLoaded($x, $z)) {
			//We can handle this request right away
			ChunkInputData::from($this->addChunk($request->getWorld(), $x, $z, new ExtendedBinaryStream()), $request->getPayload());
			return;
		}

		$request->getWorld()->requestChunkPopulation($x, $z, null)->onCompletion(
			function () use ($request, $x, $z): void {
				ChunkInputData::from($this->addChunk($request->getWorld(), $x, $z, new ExtendedBinaryStream()), $request->getPayload());
			},
			function () use ($x, $z): void {
				CancelTaskData::from(SessionIdentifier::internal("Chunkloading Failed"));
				EditThread::getInstance()->getLogger()->warning("Failed to load chunk $x $z");
			}
		);
	}

	/**
	 * @param World                $world
	 * @param int                  $x
	 * @param int                  $z
	 * @param ExtendedBinaryStream $stream
	 * @return string
	 */
	private function addChunk(World $world, int $x, int $z, ExtendedBinaryStream $stream): string
	{
		$stream->putInt($x);
		$stream->putInt($z);
		ChunkInformation::fromChunk(LoaderManager::getChunk($world, $x, $z))->putData($stream);
		return $stream->getBuffer();
	}
}