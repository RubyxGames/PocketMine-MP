<?php

namespace pocketmine\world\worldedit\task\editing;

use BadMethodCallException;
use pocketmine\world\worldedit\WorldEdit;
use pocketmine\world\worldedit\selection\constructor\ShapeConstructor;
use pocketmine\world\worldedit\selection\Selection;
use pocketmine\world\worldedit\thread\chunk\ChunkRequest;
use pocketmine\world\worldedit\world\ChunkInformation;
use pocketmine\Server;
use pocketmine\world\World;

class SingleChunkHandler extends GroupedChunkHandler
{
	/**
	 * @var array<int, ChunkInformation>
	 */
	private array $chunks = [];

	/**
	 * @param int $chunk
	 */
	public function request(int $chunk): void
	{
		WorldEdit::getEnv()->processChunkRequest(new ChunkRequest($this->world, $chunk), $this);
	}

	/**
	 * @param int                $chunk
	 * @param ShapeConstructor[] $constructors
	 * @return bool
	 */
	public function shouldRequest(int $chunk, array $constructors): bool
	{
		foreach ($constructors as $constructor) {
			if ($constructor->needsChunk($chunk)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param Selection $selection
	 * @return bool
	 */
	public function checkLoaded(Selection $selection): bool
	{
		$world = Server::getInstance()->getWorldManager()->getWorldByName($this->world);
		if ($world === null) {
			return false;
		}
		foreach ($selection->getNeededChunks() as $chunk) {
			World::getXZ($chunk, $x, $z);
			if (!$world->isChunkLoaded($x, $z)) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @param int              $chunk
	 * @param ChunkInformation $data
	 * @param int|null         $payload
	 */
	public function handleInput(int $chunk, ChunkInformation $data, ?int $payload): void
	{
		$this->chunks[$chunk] = $data;
	}

	public function clear(): void
	{
		$this->chunks = [];
	}

	/**
	 * @return int|null
	 */
	public function getNextChunk(): ?int
	{
		return array_key_first($this->chunks);
	}

	/**
	 * @return ChunkInformation[]
	 */
	public function getData(): array
	{
		if (($key = $this->getNextChunk()) === null) {
			throw new BadMethodCallException("No chunk available");
		}
		WorldEdit::getEnv()->finalizeChunkStep();
		$ret = $this->chunks[$key];
		unset($this->chunks[$key]);
		return [$key => $ret];
	}
}