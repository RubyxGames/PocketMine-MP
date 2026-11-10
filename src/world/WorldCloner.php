<?php

declare(strict_types=1);

namespace pocketmine\world;

use pocketmine\utils\Filesystem;
use pocketmine\utils\Utils;
use Symfony\Component\Filesystem\Path;
use function array_shift;
use function copy;
use function count;
use function dirname;
use function file_exists;
use function is_dir;
use function mkdir;
use function rtrim;
use function scandir;
use function str_contains;
use function trim;
use const SCANDIR_SORT_NONE;

/**
 * Copies a world folder on disk (for arena templates / multiworld).
 * Source must be unloaded, or briefly unloadable with no players online in that world.
 */
final class WorldCloner{

	public function __construct(
		private WorldManager $worldManager
	){}

	/**
	 * @throws \InvalidArgumentException
	 * @throws WorldException
	 */
	public function cloneWorld(string $sourceName, string $destName, bool $loadAfter = true) : ?World{
		$sourceName = $this->validateWorldFolderName($sourceName, "source");
		$destName = $this->validateWorldFolderName($destName, "destination");

		if($sourceName === $destName){
			throw new \InvalidArgumentException("Source and destination world names must differ");
		}
		if(!$this->worldManager->isWorldGenerated($sourceName)){
			throw new \InvalidArgumentException("Source world \"$sourceName\" does not exist on disk");
		}

		$destPath = $this->worldManager->getWorldPath($destName);
		if(file_exists(rtrim($destPath, "/\\"))){
			throw new \InvalidArgumentException("Destination world \"$destName\" already exists");
		}

		$sourceWorld = $this->worldManager->getWorldByName($sourceName);
		$wasLoaded = $sourceWorld !== null;
		$wasDefault = $wasLoaded && $this->worldManager->getDefaultWorld() === $sourceWorld;

		if($wasLoaded){
			if(count($sourceWorld->getPlayers()) > 0){
				throw new WorldException("Cannot clone loaded world \"$sourceName\" while players are inside it; move them out or unload first");
			}
			$sourceWorld->save(true);
			if(!$this->worldManager->unloadWorld($sourceWorld, true)){
				throw new WorldException("Failed to unload source world \"$sourceName\" for cloning");
			}
		}

		$sourcePath = $this->worldManager->getWorldPath($sourceName);
		try{
			$this->copyWorldFolder($sourcePath, $destPath);
			$this->rewriteLevelName($destPath, $destName);
		}catch(\Throwable $e){
			if(is_dir($destPath)){
				try{
					Filesystem::recursiveUnlink($destPath);
				}catch(\Throwable){
					// best-effort cleanup
				}
			}
			if($wasLoaded){
				$this->reloadSource($sourceName, $wasDefault);
			}
			throw $e instanceof WorldException ? $e : new WorldException("World clone failed: " . $e->getMessage(), 0, $e);
		}

		if($wasLoaded){
			$this->reloadSource($sourceName, $wasDefault);
		}

		if(!$loadAfter){
			return null;
		}
		if(!$this->worldManager->loadWorld($destName)){
			throw new WorldException("Cloned world \"$destName\" but failed to load it");
		}
		$world = $this->worldManager->getWorldByName($destName);
		if($world === null){
			throw new WorldException("Cloned world \"$destName\" but it is not loaded");
		}
		return $world;
	}

	private function reloadSource(string $sourceName, bool $wasDefault) : void{
		if(!$this->worldManager->loadWorld($sourceName)){
			throw new WorldException("Failed to reload source world \"$sourceName\" after clone");
		}
		if($wasDefault){
			$reloaded = $this->worldManager->getWorldByName($sourceName);
			if($reloaded !== null){
				$this->worldManager->setDefaultWorld($reloaded);
			}
		}
	}

	/**
	 * @throws \InvalidArgumentException
	 */
	private function validateWorldFolderName(string $name, string $label) : string{
		$name = trim($name);
		if($name === "" || $name === "." || $name === ".." || str_contains($name, "/") || str_contains($name, "\\")){
			throw new \InvalidArgumentException("Invalid $label world name \"$name\"");
		}
		return $name;
	}

	private function copyWorldFolder(string $origin, string $destination) : void{
		$origin = rtrim($origin, "/\\");
		$destination = rtrim($destination, "/\\");
		if(!is_dir($origin)){
			throw new \RuntimeException("$origin does not exist, or is not a directory");
		}
		if(file_exists($destination)){
			throw new \RuntimeException("$destination already exists");
		}
		$parent = dirname($destination);
		if(!is_dir($parent)){
			throw new \RuntimeException("Parent directory of $destination does not exist");
		}
		mkdir($destination);
		$this->copyWorldFolderInternal($origin, $destination);
	}

	private function copyWorldFolderInternal(string $origin, string $destination) : void{
		if(is_dir($origin)){
			if(!is_dir($destination)){
				mkdir($destination);
			}
			$objects = Utils::assumeNotFalse(scandir($origin, SCANDIR_SORT_NONE));
			foreach($objects as $object){
				if($object === "." || $object === ".." || $object === "LOCK"){
					continue;
				}
				$this->copyWorldFolderInternal(Path::join($origin, $object), Path::join($destination, $object));
			}
			return;
		}
		copy($origin, $destination);
	}

	private function rewriteLevelName(string $worldPath, string $displayName) : void{
		$providers = $this->worldManager->getProviderManager()->getMatchingProviders($worldPath);
		if(count($providers) !== 1){
			throw new WorldException("Cannot open cloned world data to set LevelName");
		}
		$entry = array_shift($providers);
		$provider = $entry->fromPath($worldPath, new \PrefixedLogger(\pocketmine\Server::getInstance()->getLogger(), "WorldCloner: $displayName"));
		try{
			$data = $provider->getWorldData();
			$data->setName($displayName);
			$data->save();
		}finally{
			$provider->close();
		}
	}
}
