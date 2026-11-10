<?php

namespace pocketmine\world\worldedit\selection;

use Generator;
use pocketmine\world\worldedit\math\BlockVector;
use pocketmine\world\worldedit\selection\identifier\BlockListSelectionIdentifier;
use pocketmine\world\worldedit\selection\identifier\StoredSelectionIdentifier;
use pocketmine\world\worldedit\thread\modules\StorageModule;
use pocketmine\world\worldedit\utils\EntityUtils;
use pocketmine\world\worldedit\utils\ExtendedBinaryStream;
use pocketmine\block\tile\Tile;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\World;
use function count;
use function floor;

abstract class BlockListSelection extends Selection implements BlockListSelectionIdentifier
{
	/**
	 * @var CompoundTag[]
	 */
	private array $tiles = [];

	/**
	 * Entities are a list (not block-hash keyed) — several can occupy the same cell.
	 *
	 * @var CompoundTag[]
	 * @phpstan-var list<CompoundTag>
	 */
	private array $entities = [];

	/**
	 * @param int  $x
	 * @param int  $y
	 * @param int  $z
	 * @param int  $id
	 * @param bool $overwrite
	 */
	abstract public function addBlock(int $x, int $y, int $z, int $id, bool $overwrite = true): void;

	/**
	 * @param CompoundTag|null $tile
	 */
	public function addTile(?CompoundTag $tile): void
	{
		if ($tile !== null) {
			$this->tiles[World::blockHash($tile->getInt(Tile::TAG_X), $tile->getInt(Tile::TAG_Y), $tile->getInt(Tile::TAG_Z))] = $tile;
		}
	}

	/**
	 * @param CompoundTag|null $entity
	 */
	public function addEntity(?CompoundTag $entity): void
	{
		if ($entity !== null) {
			$this->entities[] = $entity;
		}
	}

	/**
	 * @param BlockVector $min
	 * @param BlockVector $max
	 * @return Generator<CompoundTag>
	 */
	public function getTiles(BlockVector $min, BlockVector $max): Generator
	{
		foreach ($this->tiles as $hash => $tile) {
			World::getBlockXYZ($hash, $x, $y, $z);
			if ($x >= $min->x && $y >= $min->y && $z >= $min->z && $x <= $max->x && $y <= $max->y && $z <= $max->z) {
				yield $tile;
			}
		}
	}

	/**
	 * @return CompoundTag[]
	 * @phpstan-return list<CompoundTag>
	 */
	public function getEntities(): array
	{
		return $this->entities;
	}

	/**
	 * Entities whose floor(Pos) lies inside the inclusive block AABB.
	 *
	 * @param BlockVector $min
	 * @param BlockVector $max
	 * @return Generator<CompoundTag>
	 */
	public function getEntitiesInBounds(BlockVector $min, BlockVector $max): Generator
	{
		foreach ($this->entities as $entity) {
			$pos = EntityUtils::readPos($entity);
			if ($pos === null) {
				continue;
			}
			$x = (int) floor($pos[0]);
			$y = (int) floor($pos[1]);
			$z = (int) floor($pos[2]);
			if ($x >= $min->x && $y >= $min->y && $z >= $min->z && $x <= $max->x && $y <= $max->y && $z <= $max->z) {
				yield $entity;
			}
		}
	}

	abstract public function getBlockCount(): int;

	public function toIdentifier(): StoredSelectionIdentifier
	{
		return StorageModule::store($this);
	}

	/**
	 * @param ExtendedBinaryStream $stream
	 */
	public function putData(ExtendedBinaryStream $stream): void
	{
		parent::putData($stream);
		$stream->putCompounds($this->tiles);
		$stream->putCompoundList($this->entities);
	}

	/**
	 * @param ExtendedBinaryStream $stream
	 */
	public function parseData(ExtendedBinaryStream $stream): void
	{
		parent::parseData($stream);
		$this->tiles = $stream->getCompounds();
		$this->entities = $stream->feof() ? [] : $stream->getCompoundList();
	}

	public function free(): void
	{
		$this->tiles = [];
		$this->entities = [];
	}

	abstract public function createSafeClone(): BlockListSelection;

	public function containsData(): bool
	{
		return count($this->tiles) > 0 || count($this->entities) > 0;
	}
}
