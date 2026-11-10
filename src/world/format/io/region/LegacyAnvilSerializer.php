<?php

declare(strict_types=1);

namespace pocketmine\world\format\io\region;

use pocketmine\block\Block;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\data\bedrock\block\upgrade\LegacyBlockIdToStringIdMap;
use pocketmine\nbt\BigEndianNbtSerializer;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\TreeRoot;
use pocketmine\world\format\io\ChunkData;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use pocketmine\world\format\SubChunk;
use function array_flip;
use function chr;
use function ord;
use function str_repeat;
use function zlib_encode;
use const ZLIB_ENCODING_DEFLATE;

/**
 * Best-effort legacy Anvil (pre-1.13) chunk serializer.
 * Unknown / modern blocks become air (lossy). Not Java 1.13+ palette write.
 */
final class LegacyAnvilSerializer{

	/** @var array<string, int>|null */
	private static ?array $stringToLegacyId = null;

	private function __construct(){}

	public static function serializeChunk(int $chunkX, int $chunkZ, ChunkData $chunkData) : string{
		$sections = new ListTag();
		foreach($chunkData->getSubChunks() as $y => $subChunk){
			if($y < 0 || $y > 15){
				// pre-1.13 Anvil only supports Y 0..15 (world height 0..255)
				continue;
			}
			if($subChunk->isEmptyFast()){
				continue;
			}
			[$blocks, $data] = self::encodeSubChunkYZX($subChunk);
			$sections->push(CompoundTag::create()
				->setByte("Y", $y)
				->setByteArray("Blocks", $blocks)
				->setByteArray("Data", $data)
			);
		}

		$biomes = self::encodeBiomes2d($chunkData);
		$level = CompoundTag::create()
			->setInt("xPos", $chunkX)
			->setInt("zPos", $chunkZ)
			->setByte("TerrainPopulated", $chunkData->isPopulated() ? 1 : 0)
			->setByte("LightPopulated", 1)
			->setTag("Sections", $sections)
			->setByteArray("Biomes", $biomes)
			->setTag("Entities", new ListTag($chunkData->getEntityNBT()))
			->setTag("TileEntities", new ListTag($chunkData->getTileNBT()));

		$root = CompoundTag::create()->setTag("Level", $level);
		$nbt = (new BigEndianNbtSerializer())->write(new TreeRoot($root));
		return zlib_encode($nbt, ZLIB_ENCODING_DEFLATE);
	}

	/**
	 * @return array{string, string} Blocks (4096) + Data nibbles (2048)
	 */
	private static function encodeSubChunkYZX(SubChunk $subChunk) : array{
		$blocks = str_repeat("\x00", 4096);
		$data = str_repeat("\x00", 2048);
		$registry = RuntimeBlockStateRegistry::getInstance();
		$map = self::stringToLegacyMap();
		$serializer = GlobalBlockStateHandlers::getSerializer();

		for($x = 0; $x < 16; ++$x){
			for($z = 0; $z < 16; ++$z){
				for($y = 0; $y < 16; ++$y){
					$stateId = $subChunk->getBlockStateId($x, $y, $z);
					if($stateId === Block::EMPTY_STATE_ID){
						continue;
					}
					$legacyId = 0;
					try{
						$block = $registry->fromStateId($stateId);
						$name = $serializer->serialize($block)->getName();
						$legacyId = $map[$name] ?? 0;
					}catch(\Throwable){
						$legacyId = 0;
					}
					if($legacyId <= 0 || $legacyId > 255){
						continue;
					}
					$i = ($y << 8) | ($z << 4) | $x;
					$blocks[$i] = chr($legacyId);
					// meta always 0 in lite export
					$nibbleIndex = $i >> 1;
					$existing = ord($data[$nibbleIndex]);
					if(($i & 1) === 0){
						$data[$nibbleIndex] = chr(($existing & 0xf0) | 0);
					}else{
						$data[$nibbleIndex] = chr(($existing & 0x0f) | 0);
					}
				}
			}
		}
		return [$blocks, $data];
	}

	private static function encodeBiomes2d(ChunkData $chunkData) : string{
		$out = str_repeat("\x00", 256);
		$subChunks = $chunkData->getSubChunks();
		$sample = $subChunks[0] ?? null;
		if($sample === null){
			return $out;
		}
		$biomes = $sample->getBiomeArray();
		for($x = 0; $x < 16; ++$x){
			for($z = 0; $z < 16; ++$z){
				$id = $biomes->get($x, 0, $z) & 0xff;
				$out[($z << 4) | $x] = chr($id);
			}
		}
		return $out;
	}

	/**
	 * @return array<string, int>
	 */
	private static function stringToLegacyMap() : array{
		if(self::$stringToLegacyId === null){
			self::$stringToLegacyId = array_flip(LegacyBlockIdToStringIdMap::getInstance()->getLegacyToStringMap());
		}
		return self::$stringToLegacyId;
	}
}
