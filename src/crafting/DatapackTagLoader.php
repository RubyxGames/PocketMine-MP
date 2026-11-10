<?php

declare(strict_types=1);

namespace pocketmine\crafting;

use pocketmine\data\bedrock\ItemTagToIdMap;
use pocketmine\utils\Utils;
use Symfony\Component\Filesystem\Path;
use function file_get_contents;
use function is_array;
use function is_dir;
use function is_file;
use function is_string;
use function json_decode;
use function pathinfo;
use function scandir;
use function str_contains;
use function str_ends_with;
use const JSON_THROW_ON_ERROR;
use const PATHINFO_FILENAME;

/**
 * Loads PM/bedrock-data style item tag JSON from datapacks into ItemTagToIdMap.
 * Not vanilla Java datapack tag semantics.
 */
final class DatapackTagLoader{

	private function __construct(){}

	/**
	 * Scans datapacks/<pack>/tags/items/ (*.json) under the server root and worlds/<world>/datapacks/.
	 *
	 * Supported shapes:
	 * - bedrock-data map file: { "minecraft:planks": ["minecraft:oak_planks", ...] }
	 * - single-tag file named <tag>.json: { "values": ["minecraft:oak_planks", ...] }
	 *
	 * @return int number of tag→id bindings added
	 */
	public static function loadFromServerRoot(string $serverDataPath, \Logger $logger) : int{
		$added = self::loadFromDatapacksRoot(Path::join($serverDataPath, "datapacks"), $logger, null);

		$worldsRoot = Path::join($serverDataPath, "worlds");
		if(is_dir($worldsRoot)){
			foreach(scandir($worldsRoot) ?: [] as $worldName){
				if($worldName === "." || $worldName === ".."){
					continue;
				}
				$worldDatapacks = Path::join($worldsRoot, $worldName, "datapacks");
				if(!is_dir($worldDatapacks)){
					continue;
				}
				$added += self::loadFromDatapacksRoot($worldDatapacks, $logger, $worldName);
			}
		}
		return $added;
	}

	private static function loadFromDatapacksRoot(string $root, \Logger $logger, ?string $worldName) : int{
		if(!is_dir($root)){
			return 0;
		}
		$added = 0;
		$map = ItemTagToIdMap::getInstance();
		foreach(scandir($root) ?: [] as $entry){
			if($entry === "." || $entry === ".."){
				continue;
			}
			$tagsDir = Path::join($root, $entry, "tags", "items");
			if(!is_dir($tagsDir)){
				continue;
			}
			$label = $worldName !== null ? "$worldName/$entry" : $entry;
			$packAdded = self::loadDirectory($tagsDir, $map, $logger, $label);
			$added += $packAdded;
			if($packAdded > 0){
				$logger->info("Loaded datapack item tags from \"$label\" ($packAdded bindings)");
			}
		}
		return $added;
	}

	/**
	 * @return int bindings added
	 */
	private static function loadDirectory(string $dir, ItemTagToIdMap $map, \Logger $logger, string $packName) : int{
		$added = 0;
		foreach(scandir($dir) ?: [] as $file){
			if($file === "." || $file === ".." || !str_ends_with($file, ".json")){
				continue;
			}
			$path = Path::join($dir, $file);
			if(!is_file($path)){
				continue;
			}
			$raw = @file_get_contents($path);
			if($raw === false){
				$logger->error("Failed reading datapack tags \"$packName/$file\"");
				continue;
			}
			try{
				$data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
			}catch(\Throwable $e){
				$logger->error("Failed loading datapack tags \"$packName/$file\": " . $e->getMessage());
				continue;
			}
			if(!is_array($data)){
				$logger->error("Datapack tags \"$packName/$file\": expected JSON object");
				continue;
			}

			if(isset($data["values"]) && is_array($data["values"])){
				$tag = is_string($data["tag"] ?? null) ? $data["tag"] : pathinfo($file, PATHINFO_FILENAME);
				if(!str_contains($tag, ":")){
					$tag = "minecraft:" . $tag;
				}
				foreach($data["values"] as $id){
					if(is_string($id)){
						$map->addIdToTag($tag, $id);
						$added++;
					}
				}
				continue;
			}

			foreach(Utils::promoteKeys($data) as $tagName => $ids){
				if(!is_string($tagName) || !is_array($ids)){
					continue;
				}
				foreach($ids as $id){
					if(is_string($id)){
						$map->addIdToTag($tagName, $id);
						$added++;
					}
				}
			}
		}
		return $added;
	}
}
