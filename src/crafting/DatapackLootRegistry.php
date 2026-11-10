<?php

declare(strict_types=1);

namespace pocketmine\crafting;

use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\utils\SingletonTrait;
use function array_key_last;
use function array_keys;
use function is_array;
use function is_int;
use function is_string;
use function max;
use function min;
use function mt_rand;

/**
 * PM-native loot table registry loaded from datapacks (not vanilla Java loot).
 *
 * JSON shape under datapacks/<pack>/loot_tables/<name>.json:
 * {
 *   "pools": [
 *     { "rolls": 1, "entries": [ { "item": "minecraft:diamond", "count": 1, "weight": 1 } ] }
 *   ]
 * }
 */
final class DatapackLootRegistry{
	use SingletonTrait;

	/**
	 * @var array<string, array{pools: list<array{rolls: int, entries: list<array{item: string, count: int, weight: int}>}>}>
	 */
	private array $tables = [];

	/**
	 * @param array<mixed> $data
	 */
	public function register(string $name, array $data) : void{
		$pools = [];
		$rawPools = $data["pools"] ?? [];
		if(!is_array($rawPools)){
			return;
		}
		foreach($rawPools as $pool){
			if(!is_array($pool)){
				continue;
			}
			$rolls = is_int($pool["rolls"] ?? null) ? max(1, $pool["rolls"]) : 1;
			$entries = [];
			foreach($pool["entries"] ?? [] as $entry){
				if(!is_array($entry) || !is_string($entry["item"] ?? null)){
					continue;
				}
				$entries[] = [
					"item" => $entry["item"],
					"count" => max(1, is_int($entry["count"] ?? null) ? $entry["count"] : 1),
					"weight" => max(1, is_int($entry["weight"] ?? null) ? $entry["weight"] : 1),
				];
			}
			if($entries !== []){
				$pools[] = ["rolls" => $rolls, "entries" => $entries];
			}
		}
		if($pools !== []){
			$this->tables[$name] = ["pools" => $pools];
		}
	}

	public function has(string $name) : bool{
		return isset($this->tables[$name]);
	}

	/**
	 * @return list<string>
	 */
	public function getNames() : array{
		return array_keys($this->tables);
	}

	/**
	 * Roll a registered table into concrete items (best-effort; unknown ids skipped).
	 *
	 * @return list<Item>
	 */
	public function roll(string $name) : array{
		$table = $this->tables[$name] ?? null;
		if($table === null){
			return [];
		}
		$out = [];
		foreach($table["pools"] as $pool){
			for($r = 0; $r < $pool["rolls"]; ++$r){
				$entry = $this->pickWeighted($pool["entries"]);
				if($entry === null){
					continue;
				}
				$item = StringToItemParser::getInstance()->parse($entry["item"]);
				if($item === null){
					continue;
				}
				$item = clone $item;
				$item->setCount(min($item->getMaxStackSize(), $entry["count"]));
				$out[] = $item;
			}
		}
		return $out;
	}

	/**
	 * @param list<array{item: string, count: int, weight: int}> $entries
	 * @return array{item: string, count: int, weight: int}|null
	 */
	private function pickWeighted(array $entries) : ?array{
		$total = 0;
		foreach($entries as $e){
			$total += $e["weight"];
		}
		if($total <= 0){
			return null;
		}
		$n = mt_rand(1, $total);
		foreach($entries as $e){
			$n -= $e["weight"];
			if($n <= 0){
				return $e;
			}
		}
		return $entries[array_key_last($entries)] ?? null;
	}
}
