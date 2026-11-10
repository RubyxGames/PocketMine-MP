<?php

declare(strict_types=1);

namespace pocketmine\world\format\io\region;

use pocketmine\block\Block;
use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntArrayTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\LongArrayTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use pocketmine\world\format\PalettedBlockArray;
use pocketmine\world\format\SubChunk;
use pocketmine\world\worldedit\convert\BlockStateConvertor;
use function count;
use function intdiv;
use function max;
use function min;

/**
 * Lossy Java 1.13+ Anvil section reader (Palette + BlockStates).
 * Maps via WorldEdit Java→Bedrock convertor when available; unknown → air.
 * Height limited to classic section Y 0..15 (world 0..255) in this slice.
 */
final class PalettedAnvilSubChunkDeserializer{

	private function __construct(){}

	public static function tryDeserialize(CompoundTag $section, PalettedBlockArray $biomes3d, \Logger $logger) : ?SubChunk{
		$paletteTag = $section->getListTag("Palette", CompoundTag::class);
		$statesTag = $section->getTag("BlockStates");
		if($paletteTag === null || (!$statesTag instanceof LongArrayTag && !$statesTag instanceof IntArrayTag)){
			return null;
		}

		$runtimeIds = [];
		$deserializer = GlobalBlockStateHandlers::getDeserializer();
		$weAvailable = BlockStateConvertor::isAvailable();
		foreach($paletteTag as $i => $entry){
			$nameTag = $entry->getTag("Name");
			$name = $nameTag instanceof StringTag ? $nameTag->getValue() : "minecraft:air";
			$props = $entry->getCompoundTag("Properties") ?? new CompoundTag();
			$states = [];
			foreach($props->getValue() as $k => $v){
				$states[$k] = $v;
			}
			try{
				$java = new BlockStateData($name, $states, BlockStateData::CURRENT_VERSION);
				if($weAvailable){
					$bedrock = BlockStateConvertor::javaToBedrock($java, false);
				}else{
					$bedrock = $java;
				}
				$runtimeIds[$i] = $deserializer->deserialize($bedrock);
			}catch(\Throwable $e){
				$logger->debug("Paletted Anvil: unknown state $name → air (" . $e->getMessage() . ")");
				$runtimeIds[$i] = Block::EMPTY_STATE_ID;
			}
		}

		$paletteSize = max(1, count($runtimeIds));
		$bits = self::bitsPerBlock($paletteSize);
		$indices = self::unpackIndices($statesTag instanceof LongArrayTag ? $statesTag->getValue() : self::intsToLongs($statesTag->getValue()), $bits);

		$blocks = new PalettedBlockArray(Block::EMPTY_STATE_ID);
		for($i = 0; $i < 4096; ++$i){
			$paletteIndex = $indices[$i] ?? 0;
			$stateId = $runtimeIds[$paletteIndex] ?? Block::EMPTY_STATE_ID;
			$y = $i >> 8;
			$z = ($i >> 4) & 0xf;
			$x = $i & 0xf;
			// Java section storage is YZX
			$blocks->set($x, $y, $z, $stateId);
		}
		return new SubChunk(Block::EMPTY_STATE_ID, [$blocks], $biomes3d);
	}

	private static function bitsPerBlock(int $paletteSize) : int{
		$bits = 4;
		while((1 << $bits) < $paletteSize){
			++$bits;
		}
		return max(4, min(15, $bits));
	}

	/**
	 * @param list<int> $longs
	 * @return list<int>
	 */
	private static function unpackIndices(array $longs, int $bits) : array{
		$mask = (1 << $bits) - 1;
		$out = [];
		$bitIndex = 0;
		$totalBits = count($longs) * 64;
		while(count($out) < 4096 && $bitIndex + $bits <= $totalBits){
			$longIndex = intdiv($bitIndex, 64);
			$bitOffset = $bitIndex % 64;
			$value = $longs[$longIndex] ?? 0;
			if($bitOffset + $bits <= 64){
				$index = ($value >> $bitOffset) & $mask;
			}else{
				$lowBits = 64 - $bitOffset;
				$next = $longs[$longIndex + 1] ?? 0;
				$index = (($value >> $bitOffset) | (($next & ((1 << ($bits - $lowBits)) - 1)) << $lowBits)) & $mask;
			}
			$out[] = $index;
			$bitIndex += $bits;
		}
		while(count($out) < 4096){
			$out[] = 0;
		}
		return $out;
	}

	/**
	 * @param list<int> $ints
	 * @return list<int>
	 */
	private static function intsToLongs(array $ints) : array{
		// Some tools store BlockStates as int[]; treat pairs as little-endian longs poorly —
		// prefer empty (all air indices) over corrupt geometry.
		$longs = [];
		for($i = 0; $i + 1 < count($ints); $i += 2){
			$longs[] = ($ints[$i] & 0xffffffff) | (($ints[$i + 1] & 0xffffffff) << 32);
		}
		return $longs;
	}
}
