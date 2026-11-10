<?php

declare(strict_types=1);

namespace pocketmine\plugin;

use function array_keys;
use function array_values;
use function sort;
use function strtolower;
use function trim;

/**
 * Registry of optional server features plugins can declare as requirements via plugin.yml
 * `requires-capabilities`. Prefer this over probing internals with class_exists().
 */
final class ServerCapabilities{

	/**
	 * @var true[]
	 * @phpstan-var array<string, true>
	 */
	private array $capabilities = [];

	public function register(string $id) : void{
		$id = strtolower(trim($id));
		if($id === ""){
			throw new \InvalidArgumentException("Capability id must not be empty");
		}
		$this->capabilities[$id] = true;
	}

	public function has(string $id) : bool{
		return isset($this->capabilities[strtolower(trim($id))]);
	}

	/**
	 * @return list<string>
	 */
	public function getAll() : array{
		$ids = array_keys($this->capabilities);
		sort($ids);
		return array_values($ids);
	}

	/**
	 * @param list<string> $required
	 * @return list<string> missing capability ids
	 */
	public function missing(array $required) : array{
		$missing = [];
		foreach($required as $id){
			if(!$this->has((string) $id)){
				$missing[] = (string) $id;
			}
		}
		return $missing;
	}
}
