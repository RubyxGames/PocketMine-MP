<?php

namespace pocketmine\world\worldedit\task\editing;

use pocketmine\world\worldedit\WorldEdit;
use pocketmine\world\worldedit\selection\BlockListSelection;
use pocketmine\world\worldedit\thread\EditThread;
use pocketmine\world\worldedit\thread\output\ResultingChunkData;
use pocketmine\world\worldedit\utils\TileUtils;
use pocketmine\world\worldedit\world\blockupdate\InjectingData;
use pocketmine\world\worldedit\world\blockupdate\InjectingSubChunkController;
use pocketmine\world\worldedit\world\ChunkController;
use pocketmine\world\worldedit\world\ChunkInformation;
use pocketmine\world\worldedit\world\ReferencedChunkManager;
use pocketmine\block\tile\Tile;
use pocketmine\nbt\tag\CompoundTag;

class EditTaskHandler
{
	private ChunkController $origin; //Read-only
	private ChunkController $result; //Write-only

	/**
	 * Entities queued for main-thread spawn after the current chunk flush.
	 *
	 * @var CompoundTag[]
	 * @phpstan-var list<CompoundTag>
	 */
	private array $pendingEntities = [];

	/**
	 * @param BlockListSelection $changes Saves made changes, used for undoing
	 */
	public function __construct(private string $world, protected BlockListSelection $changes)
	{
		//TODO: Never use changes as result (eg. copy)
		$this->origin = new ChunkController(new ReferencedChunkManager($world));
		$this->result = WorldEdit::getEnv()->getChunkController(new ReferencedChunkManager($world));
	}

	public function setChunk(int $key, ChunkInformation $chunk): void
	{
		$this->origin->getManager()->setChunk($key, $chunk);
		$this->result->getManager()->setChunk($key, clone $chunk);
	}

	/**
	 * @return int
	 */
	public function getChunkCount(): int
	{
		return count($this->origin->getManager()->getChunks());
	}

	/**
	 * @return int
	 */
	public function getChangedBlockCount(): int
	{
		return $this->result->getWrittenBlockCount();
	}

	/**
	 * @return int
	 */
	public function getWrittenBlockCount(): int
	{
		return $this->origin->getWrittenBlockCount() + $this->result->getWrittenBlockCount() + $this->changes->getBlockCount();
	}

	/**
	 * @return int
	 */
	public function getReadBlockCount(): int
	{
		//TODO: Blocklist selections
		return $this->origin->getReadBlockCount() + $this->result->getReadBlockCount() + $this->result->getReadBlockCount();
	}

	/**
	 * @return ReferencedChunkManager
	 */
	public function getResult(): ReferencedChunkManager
	{
		return $this->result->getManager();
	}

	public function finish(): void
	{
		WorldEdit::getEnv()->submitResultingChunks($this->result);
		if ($this->pendingEntities !== []) {
			WorldEdit::getEnv()->submitResultingEntities($this->world, $this->pendingEntities);
			$this->pendingEntities = [];
		}
		$this->origin->reset();
		$this->result->reset();
	}

	/**
	 * @param int $chunk
	 * @return string[]
	 */
	public function prepareInjectionData(int $chunk): array
	{
		if (!$this->result instanceof InjectingSubChunkController) {
			return [];
		}
		return array_map(static function (InjectingData $injection) {
			return $injection->toProtocol();
		}, $this->result->getInjection($chunk));
	}

	/**
	 * @return BlockListSelection
	 */
	public function getChanges(): BlockListSelection
	{
		return $this->changes;
	}

	/**
	 * @return ChunkController
	 *
	 * @deprecated
	 */
	public function getOrigin(): ChunkController
	{
		return $this->origin;
	}

	/**
	 * @param int $x
	 * @param int $y
	 * @param int $z
	 * @return int
	 */
	public function getBlock(int $x, int $y, int $z): int
	{
		return $this->origin->getBlock($x, $y, $z);
	}

	/**
	 * @param int $x
	 * @param int $y
	 * @param int $z
	 * @return CompoundTag|null
	 */
	public function getTile(int $x, int $y, int $z): ?CompoundTag
	{
		return $this->origin->getTile($x, $y, $z);
	}

	/**
	 * @param int $x
	 * @param int $y
	 * @param int $z
	 * @return int
	 */
	public function getResultingBlock(int $x, int $y, int $z): int
	{
		return $this->result->getBlock($x, $y, $z);
	}

	/**
	 * @param int  $x
	 * @param int  $y
	 * @param int  $z
	 * @param int  $block
	 * @param bool $overwrite
	 */
	public function changeBlock(int $x, int $y, int $z, int $block, bool $overwrite = true): void
	{
		$this->changes->addBlock($x, $y, $z, $this->origin->getBlock($x, $y, $z), $overwrite);
		$this->changes->addTile($this->origin->getTile($x, $y, $z));

		$this->result->setBlock($x, $y, $z, $block);
		$this->result->setTile($x, $y, $z, null);
	}

	/**
	 * @param int  $x
	 * @param int  $y
	 * @param int  $z
	 * @param int  $ox x of block to be copied
	 * @param int  $oy y of block to be copied
	 * @param int  $oz z of block to be copied
	 * @param bool $overwrite
	 */
	public function copyBlock(int $x, int $y, int $z, int $ox, int $oy, int $oz, bool $overwrite = true): void
	{
		$this->changeBlock($x, $y, $z, $this->origin->getBlock($ox, $oy, $oz), $overwrite);
		$this->addTile(TileUtils::offsetCompound($this->origin->getTile($ox, $oy, $oz), $x - $ox, $y - $oy, $z - $oz));
	}

	/**
	 * @param CompoundTag|null $tile
	 */
	public function addTile(?CompoundTag $tile): void
	{
		if ($tile !== null) {
			$this->result->setTile($tile->getInt(Tile::TAG_X), $tile->getInt(Tile::TAG_Y), $tile->getInt(Tile::TAG_Z), $tile);
		}
	}

	/**
	 * Queue an entity NBT for main-thread spawn after chunks for this finish() cycle.
	 *
	 * @param CompoundTag|null $entity
	 */
	public function addEntity(?CompoundTag $entity): void
	{
		if ($entity !== null) {
			$this->pendingEntities[] = $entity;
		}
	}
}