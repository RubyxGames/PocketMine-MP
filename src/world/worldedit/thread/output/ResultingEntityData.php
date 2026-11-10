<?php

declare(strict_types=1);

namespace pocketmine\world\worldedit\thread\output;

use pocketmine\data\SavedDataLoadingException;
use pocketmine\entity\EntityFactory;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\worldedit\WorldEdit;
use pocketmine\world\worldedit\utils\EntityUtils;
use pocketmine\world\worldedit\utils\ExtendedBinaryStream;
use pocketmine\world\worldedit\world\ReferencedWorldHolder;
use Throwable;

/**
 * Spawns clipboard entities on the main thread after chunks/tiles have been applied.
 */
class ResultingEntityData extends OutputData
{
	use ReferencedWorldHolder;

	/**
	 * @param string        $world
	 * @param CompoundTag[] $entities
	 * @phpstan-param list<CompoundTag> $entities
	 */
	public function __construct(string $world, private array $entities)
	{
		$this->world = $world;
	}

	public function checkSend(): bool
	{
		return $this->entities !== [];
	}

	public function handle(): void
	{
		$world = $this->getWorld();
		$factory = EntityFactory::getInstance();
		$spawned = 0;
		$skipped = 0;
		foreach ($this->entities as $nbt) {
			try {
				$pos = EntityUtils::readPos($nbt);
				if ($pos !== null) {
					// LoaderManager unloads after inject — reload so the entity can bind to a chunk.
					$world->loadChunk(((int) $pos[0]) >> 4, ((int) $pos[2]) >> 4);
				}
				$entity = $factory->createFromData($world, $nbt);
				if ($entity === null) {
					$skipped++;
					continue;
				}
				// Entity constructor already calls World::addEntity
				$spawned++;
			} catch (SavedDataLoadingException|Throwable $e) {
				$skipped++;
				WorldEdit::getInstance()->getLogger()->debug("Failed to spawn pasted entity: " . $e->getMessage());
			}
		}
		WorldEdit::getInstance()->getLogger()->debug("Pasted entities: spawned $spawned" . ($skipped > 0 ? ", skipped $skipped" : ""));
	}

	public function putData(ExtendedBinaryStream $stream): void
	{
		$stream->putString($this->world);
		$stream->putCompoundList($this->entities);
	}

	public function parseData(ExtendedBinaryStream $stream): void
	{
		$this->world = $stream->getString();
		$this->entities = $stream->getCompoundList();
	}

	/**
	 * @return CompoundTag[]
	 * @phpstan-return list<CompoundTag>
	 */
	public function getEntities(): array
	{
		return $this->entities;
	}
}
