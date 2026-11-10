<?php

declare(strict_types=1);

namespace pocketmine\world\format\io\region;

use pocketmine\block\Block;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\format\io\ChunkData;
use pocketmine\world\format\PalettedBlockArray;
use pocketmine\world\format\SubChunk;

/**
 * Legacy Anvil provider with write support (pre-1.13 Blocks/Data).
 * Modern Java 1.13+ palette format is not supported.
 */
class WritableAnvil extends WritableRegionWorldProvider{
	use LegacyAnvilChunkTrait;

	protected function deserializeSubChunk(CompoundTag $subChunk, PalettedBlockArray $biomes3d, \Logger $logger) : SubChunk{
		return new SubChunk(Block::EMPTY_STATE_ID, [$this->palettizeLegacySubChunkYZX(
			self::readFixedSizeByteArray($subChunk, "Blocks", 4096),
			self::readFixedSizeByteArray($subChunk, "Data", 2048),
			$logger
		)], $biomes3d);
	}

	protected function serializeChunk(ChunkData $chunk) : string{
		// chunk coordinates are not on ChunkData — WritableRegionWorldProvider.saveChunk has them;
		// serializeChunk is only given ChunkData. Store coords via thread-local is awkward.
		// Region write path passes chunkX/Z separately; we need them for Level xPos/zPos.
		throw new \LogicException("Use serializeChunkAt()");
	}

	/**
	 * @internal called from saveChunk override
	 */
	protected function serializeChunkAt(int $chunkX, int $chunkZ, ChunkData $chunk) : string{
		return LegacyAnvilSerializer::serializeChunk($chunkX, $chunkZ, $chunk);
	}

	public function saveChunk(int $chunkX, int $chunkZ, ChunkData $chunkData, int $dirtyFlags) : void{
		self::getRegionIndex($chunkX, $chunkZ, $regionX, $regionZ);
		$this->loadRegion($regionX, $regionZ)->writeChunk($chunkX & 0x1f, $chunkZ & 0x1f, $this->serializeChunkAt($chunkX, $chunkZ, $chunkData));
	}

	protected static function getRegionFileExtension() : string{
		return "mca";
	}

	protected static function getPcWorldFormatVersion() : int{
		return 19133;
	}

	public function getWorldMinY() : int{
		return 0;
	}

	public function getWorldMaxY() : int{
		return 256;
	}
}
