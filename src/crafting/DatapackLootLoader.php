<?php

declare(strict_types=1);

namespace pocketmine\crafting;

use Symfony\Component\Filesystem\Path;
use function file_get_contents;
use function is_array;
use function is_dir;
use function is_file;
use function json_decode;
use function pathinfo;
use function scandir;
use function str_ends_with;
use const JSON_THROW_ON_ERROR;
use const PATHINFO_FILENAME;

/**
 * Loads PM-native loot table JSON from datapacks/<pack>/loot_tables/*.json
 * (and worlds/<world>/datapacks/...). Not Java datapack loot.
 */
final class DatapackLootLoader{

	private function __construct(){}

	public static function loadFromServerRoot(string $serverDataPath, \Logger $logger) : int{
		$added = self::loadFromDatapacksRoot(Path::join($serverDataPath, "datapacks"), $logger, null);
		$worldsRoot = Path::join($serverDataPath, "worlds");
		if(is_dir($worldsRoot)){
			foreach(scandir($worldsRoot) ?: [] as $worldName){
				if($worldName === "." || $worldName === ".."){
					continue;
				}
				$root = Path::join($worldsRoot, $worldName, "datapacks");
				if(is_dir($root)){
					$added += self::loadFromDatapacksRoot($root, $logger, $worldName);
				}
			}
		}
		return $added;
	}

	private static function loadFromDatapacksRoot(string $root, \Logger $logger, ?string $worldName) : int{
		if(!is_dir($root)){
			return 0;
		}
		$added = 0;
		$registry = DatapackLootRegistry::getInstance();
		foreach(scandir($root) ?: [] as $entry){
			if($entry === "." || $entry === ".."){
				continue;
			}
			$lootDir = Path::join($root, $entry, "loot_tables");
			if(!is_dir($lootDir)){
				continue;
			}
			foreach(scandir($lootDir) ?: [] as $file){
				if($file === "." || $file === ".." || !str_ends_with($file, ".json")){
					continue;
				}
				$path = Path::join($lootDir, $file);
				if(!is_file($path)){
					continue;
				}
				$raw = @file_get_contents($path);
				if($raw === false){
					continue;
				}
				try{
					$data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
				}catch(\Throwable $e){
					$logger->error("Failed loading loot table \"$entry/$file\": " . $e->getMessage());
					continue;
				}
				if(!is_array($data)){
					continue;
				}
				$name = is_string($data["name"] ?? null) ? $data["name"] : pathinfo($file, PATHINFO_FILENAME);
				if($worldName !== null){
					$name = $worldName . "/" . $name;
				}
				$registry->register($name, $data);
				$added++;
				$logger->info("Loaded datapack loot table \"$name\"");
			}
			$meta = Path::join($root, $entry, "pack.mcmeta");
			if(is_file($meta)){
				$logger->debug("Datapack pack.mcmeta present for \"$entry\"");
			}
		}
		return $added;
	}
}
