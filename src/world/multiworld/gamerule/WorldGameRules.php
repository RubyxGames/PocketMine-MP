<?php

declare(strict_types=1);

namespace pocketmine\world\multiworld\gamerule;

use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\world\format\io\data\BaseNbtWorldData;
use pocketmine\world\World;
use function array_key_exists;
use function is_bool;
use function is_int;
use function json_decode;
use function json_encode;

/**
 * Per-world gamerule bag with NBT persistence (MultiWorld-compatible GameRules compound).
 */
final class WorldGameRules{

	/** @var array<int, self> worldId => rules */
	private static array $cache = [];

	/**
	 * @param array<string, bool|int> $values
	 */
	public function __construct(
		private array $values = []
	){
		foreach(GameRuleDef::all() as $def){
			if(!array_key_exists($def->getName(), $this->values)){
				$this->values[$def->getName()] = $def->getDefault();
			}
		}
	}

	public static function for(World $world) : self{
		$id = $world->getId();
		return self::$cache[$id] ??= self::loadFromWorld($world);
	}

	public static function forget(World $world) : void{
		unset(self::$cache[$world->getId()]);
	}

	public static function clearCache() : void{
		self::$cache = [];
	}

	public function getBool(string $name, bool $default = true) : bool{
		$v = $this->values[$name] ?? $default;
		return is_bool($v) ? $v : (bool) $v;
	}

	public function getInt(string $name, int $default = 0) : int{
		$v = $this->values[$name] ?? $default;
		return is_int($v) ? $v : (int) $v;
	}

	public function get(string $name) : bool|int|null{
		return $this->values[$name] ?? null;
	}

	/**
	 * @return array<string, bool|int>
	 */
	public function all() : array{
		return $this->values;
	}

	public function set(string $name, bool|int $value) : self{
		$this->values[$name] = $value;
		return $this;
	}

	public function save(World $world) : bool{
		$worldData = $world->getProvider()->getWorldData();
		if(!$worldData instanceof BaseNbtWorldData){
			return false;
		}
		$nbt = new CompoundTag();
		foreach($this->values as $name => $value){
			$encoded = json_encode($value);
			if($encoded === false){
				continue;
			}
			$nbt->setString($name, $encoded);
		}
		$worldData->getCompoundTag()->setTag("GameRules", $nbt);
		self::$cache[$world->getId()] = $this;
		return true;
	}

	private static function loadFromWorld(World $world) : self{
		$worldData = $world->getProvider()->getWorldData();
		if(!$worldData instanceof BaseNbtWorldData){
			return new self();
		}
		$nbt = $worldData->getCompoundTag()->getCompoundTag("GameRules");
		if($nbt === null || $nbt->count() === 0){
			return new self();
		}
		$values = [];
		foreach($nbt->getValue() as $name => $tag){
			if(!$tag instanceof StringTag){
				continue;
			}
			$decoded = json_decode($tag->getValue());
			if(is_bool($decoded) || is_int($decoded)){
				$values[(string) $name] = $decoded;
			}
		}
		return new self($values);
	}
}
