<?php

declare(strict_types=1);

namespace pocketmine\world\multiworld\gamerule;

use function array_values;
use function strtolower;

/**
 * Built-in per-world gamerule definitions (MultiWorld-compatible camelCase names).
 */
final class GameRuleDef{

	public const TYPE_BOOL = 1;
	public const TYPE_INT = 2;

	/** @var array<string, self>|null */
	private static ?array $byName = null;

	/**
	 * @param bool|int $default
	 */
	private function __construct(
		private string $name,
		private int $type,
		private bool|int $default
	){}

	public function getName() : string{
		return $this->name;
	}

	public function getType() : int{
		return $this->type;
	}

	public function getDefault() : bool|int{
		return $this->default;
	}

	/**
	 * @return list<self>
	 */
	public static function all() : array{
		self::init();
		return array_values(self::$byName ?? []);
	}

	public static function get(string $name) : ?self{
		self::init();
		$key = self::$byName[self::normalize($name)] ?? null;
		if($key !== null){
			return $key;
		}
		// case-insensitive lookup
		foreach(self::$byName ?? [] as $rule){
			if(strtolower($rule->name) === strtolower($name)){
				return $rule;
			}
		}
		return null;
	}

	private static function normalize(string $name) : string{
		return $name;
	}

	private static function init() : void{
		if(self::$byName !== null){
			return;
		}
		$defs = [
			new self("keepInventory", self::TYPE_BOOL, false),
			new self("doTileDrops", self::TYPE_BOOL, true),
			new self("naturalRegeneration", self::TYPE_BOOL, true),
			new self("pvp", self::TYPE_BOOL, true),
			new self("tntExplodes", self::TYPE_BOOL, true),
			new self("doDaylightCycle", self::TYPE_BOOL, true),
			new self("doWeatherCycle", self::TYPE_BOOL, true),
			new self("doMobLoot", self::TYPE_BOOL, true),
			new self("doEntityDrops", self::TYPE_BOOL, true),
			new self("fallDamage", self::TYPE_BOOL, true),
			new self("fireDamage", self::TYPE_BOOL, true),
			new self("drowningDamage", self::TYPE_BOOL, true),
			new self("showCoordinates", self::TYPE_BOOL, true),
			new self("randomTickSpeed", self::TYPE_INT, 1),
			new self("spawnRadius", self::TYPE_INT, 5),
		];
		self::$byName = [];
		foreach($defs as $def){
			self::$byName[$def->name] = $def;
		}
	}
}
