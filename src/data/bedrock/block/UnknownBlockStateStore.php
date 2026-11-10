<?php

declare(strict_types=1);

namespace pocketmine\data\bedrock\block;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;

/**
 * Allocates runtime state IDs for unrecognized blockstates so LevelDB palettes can round-trip original NBT.
 */
final class UnknownBlockStateStore{

	/** @var array<int, BlockStateData> */
	private static array $byStateId = [];

	/** @var array<string, int> fingerprint => stateId */
	private static array $byFingerprint = [];

	private function __construct(){}

	/**
	 * Registers (or reuses) a preserved unknown blockstate and returns a runtime state ID.
	 */
	public static function register(BlockStateData $data) : int{
		$fingerprint = self::fingerprint($data);
		if(isset(self::$byFingerprint[$fingerprint])){
			return self::$byFingerprint[$fingerprint];
		}

		$typeId = BlockTypeIds::newId();
		$stateId = $typeId << Block::INTERNAL_STATE_DATA_BITS;
		self::$byStateId[$stateId] = $data;
		self::$byFingerprint[$fingerprint] = $stateId;
		return $stateId;
	}

	public static function get(int $stateId) : ?BlockStateData{
		return self::$byStateId[$stateId] ?? null;
	}

	public static function has(int $stateId) : bool{
		return isset(self::$byStateId[$stateId]);
	}

	/**
	 * @internal tests only
	 */
	public static function reset() : void{
		self::$byStateId = [];
		self::$byFingerprint = [];
	}

	private static function fingerprint(BlockStateData $data) : string{
		return $data->getName() . "@" . $data->getVersion() . "@" . $data->toNbt()->toString();
	}
}
