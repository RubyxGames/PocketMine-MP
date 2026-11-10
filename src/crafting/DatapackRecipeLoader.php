<?php

declare(strict_types=1);

namespace pocketmine\crafting;

use Symfony\Component\Filesystem\Path;
use function is_dir;
use function scandir;

/**
 * Loads PM-format recipe JSON from server datapacks/ folders into CraftingManager.
 * Also scans worlds/<world>/datapacks/ on disk at boot.
 * Item tags: see DatapackTagLoader. Not vanilla Java datapack semantics (loot/advancements deferred).
 */
final class DatapackRecipeLoader{

	private function __construct(){}

	/**
	 * Scans datapacks/<pack>/recipes/ under the server root and each world folder.
	 *
	 * @return list<string> loaded pack labels (folder or world/pack)
	 */
	public static function loadFromServerRoot(CraftingManager $manager, string $serverDataPath, \Logger $logger) : array{
		DatapackTagLoader::loadFromServerRoot($serverDataPath, $logger);
		DatapackLootLoader::loadFromServerRoot($serverDataPath, $logger);

		$loaded = self::loadFromDatapacksRoot($manager, Path::join($serverDataPath, "datapacks"), $logger, null);

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
				$loaded = [...$loaded, ...self::loadFromDatapacksRoot($manager, $worldDatapacks, $logger, $worldName)];
			}
		}
		return $loaded;
	}

	/**
	 * @return list<string>
	 */
	private static function loadFromDatapacksRoot(CraftingManager $manager, string $root, \Logger $logger, ?string $worldName) : array{
		if(!is_dir($root)){
			return [];
		}
		$loaded = [];
		foreach(scandir($root) ?: [] as $entry){
			if($entry === "." || $entry === ".."){
				continue;
			}
			$packDir = Path::join($root, $entry);
			$recipesDir = Path::join($packDir, "recipes");
			if(!is_dir($recipesDir)){
				if(!is_dir($packDir)){
					continue;
				}
				if(is_dir(Path::join($packDir, "tags"))){
					continue;
				}
				$recipesDir = $packDir;
			}
			$label = $worldName !== null ? "$worldName/$entry" : $entry;
			try{
				CraftingManagerFromDataHelper::loadInto($manager, $recipesDir);
				$loaded[] = $label;
				$logger->info("Loaded datapack recipes from \"$label\"");
			}catch(\Throwable $e){
				$logger->error("Failed loading datapack \"$label\": " . $e->getMessage());
			}
		}
		return $loaded;
	}
}
