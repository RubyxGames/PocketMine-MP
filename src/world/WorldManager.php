<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\world;

use pocketmine\entity\Entity;
use pocketmine\event\world\WorldInitEvent;
use pocketmine\event\world\WorldLoadEvent;
use pocketmine\event\world\WorldUnloadEvent;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\player\ChunkSelector;
use pocketmine\Server;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\io\exception\CorruptedWorldException;
use pocketmine\world\format\io\exception\UnsupportedWorldFormatException;
use pocketmine\world\format\io\FormatConverter;
use pocketmine\world\format\io\WorldProviderManager;
use pocketmine\world\format\io\WritableWorldProvider;
use pocketmine\world\generator\GeneratorManager;
use pocketmine\world\generator\InvalidGeneratorOptionsException;
use Symfony\Component\Filesystem\Path;
use function array_keys;
use function array_shift;
use function array_values;
use function assert;
use function count;
use function file_exists;
use function floor;
use function implode;
use function intdiv;
use function is_dir;
use function iterator_to_array;
use function microtime;
use function rename;
use function round;
use function rtrim;
use function scandir;
use function sort;
use function sprintf;
use function str_contains;
use function strval;
use function trim;
use const SCANDIR_SORT_NONE;

class WorldManager{
	public const TICKS_PER_AUTOSAVE = 300 * Server::TARGET_TICKS_PER_SECOND;

	/**
	 * @var World[]
	 * @phpstan-var array<int, World>
	 */
	private array $worlds = [];
	private ?World $defaultWorld = null;

	private bool $autoSave = true;
	private int $autoSaveTicks = self::TICKS_PER_AUTOSAVE;
	private int $autoSaveTicker = 0;

	public function __construct(
		private Server $server,
		private string $dataPath,
		private WorldProviderManager $providerManager
	){}

	public function getProviderManager() : WorldProviderManager{
		return $this->providerManager;
	}

	/**
	 * @return World[]
	 * @phpstan-return array<int, World>
	 */
	public function getWorlds() : array{
		return $this->worlds;
	}

	public function getDefaultWorld() : ?World{
		return $this->defaultWorld;
	}

	/**
	 * Sets the default world to a different world
	 * This won't change the level-name property,
	 * it only affects the server on runtime
	 */
	public function setDefaultWorld(?World $world) : void{
		if($world === null || ($this->isWorldLoaded($world->getFolderName()) && $world !== $this->defaultWorld)){
			$this->defaultWorld = $world;
		}
	}

	public function isWorldLoaded(string $name) : bool{
		return $this->getWorldByName($name) instanceof World;
	}

	public function getWorld(int $worldId) : ?World{
		return $this->worlds[$worldId] ?? null;
	}

	/**
	 * NOTE: This matches worlds based on the FOLDER name, NOT the display name.
	 */
	public function getWorldByName(string $name) : ?World{
		foreach($this->worlds as $world){
			if($world->getFolderName() === $name){
				return $world;
			}
		}

		return null;
	}

	/**
	 * @throws \InvalidArgumentException
	 */
	public function unloadWorld(World $world, bool $forceUnload = false) : bool{
		if($world === $this->getDefaultWorld() && !$forceUnload){
			throw new \InvalidArgumentException("The default world cannot be unloaded while running, please switch worlds.");
		}
		if($world->isDoingTick()){
			throw new \InvalidArgumentException("Cannot unload a world during world tick");
		}

		$ev = new WorldUnloadEvent($world);
		$ev->call();

		if(!$forceUnload && $ev->isCancelled()){
			return false;
		}

		$this->server->getLogger()->info($this->server->getLanguage()->translate(KnownTranslationFactory::pocketmine_level_unloading($world->getDisplayName())));
		if(count($world->getPlayers()) !== 0){
			try{
				$safeSpawn = $this->defaultWorld !== null && $this->defaultWorld !== $world ? $this->defaultWorld->getSafeSpawn() : null;
			}catch(WorldException $e){
				$safeSpawn = null;
			}
			foreach($world->getPlayers() as $player){
				if($safeSpawn === null){
					$player->disconnect("Forced default world unload");
				}else{
					$player->teleport($safeSpawn);
				}
			}
		}

		if($world === $this->defaultWorld){
			$this->defaultWorld = null;
		}
		unset($this->worlds[$world->getId()]);

		$world->onUnload();
		return true;
	}

	/**
	 * Loads a world from the data directory
	 *
	 * @param bool $autoUpgrade Converts worlds to the default format if the world's format is not writable / deprecated
	 *
	 * @throws WorldException
	 */
	public function loadWorld(string $name, bool $autoUpgrade = false) : bool{
		if(trim($name) === ""){
			throw new \InvalidArgumentException("Invalid empty world name");
		}
		if($this->isWorldLoaded($name)){
			return true;
		}elseif(!$this->isWorldGenerated($name)){
			return false;
		}

		$path = $this->getWorldPath($name);

		$providers = $this->providerManager->getMatchingProviders($path);
		if(count($providers) !== 1){
			$this->server->getLogger()->error($this->server->getLanguage()->translate(KnownTranslationFactory::pocketmine_level_loadError(
				$name,
				count($providers) === 0 ?
					KnownTranslationFactory::pocketmine_level_unknownFormat() :
					KnownTranslationFactory::pocketmine_level_ambiguousFormat(implode(", ", array_keys($providers)))
			)));
			return false;
		}
		$providerClass = array_shift($providers);

		try{
			$provider = $providerClass->fromPath($path, new \PrefixedLogger($this->server->getLogger(), "World Provider: $name"));
		}catch(CorruptedWorldException $e){
			$this->server->getLogger()->error($this->server->getLanguage()->translate(KnownTranslationFactory::pocketmine_level_loadError(
				$name,
				KnownTranslationFactory::pocketmine_level_corrupted($e->getMessage())
			)));
			return false;
		}catch(UnsupportedWorldFormatException $e){
			$this->server->getLogger()->error($this->server->getLanguage()->translate(KnownTranslationFactory::pocketmine_level_loadError(
				$name,
				KnownTranslationFactory::pocketmine_level_unsupportedFormat($e->getMessage())
			)));
			return false;
		}

		$generatorEntry = GeneratorManager::getInstance()->getGenerator($provider->getWorldData()->getGenerator());
		if($generatorEntry === null){
			$this->server->getLogger()->error($this->server->getLanguage()->translate(KnownTranslationFactory::pocketmine_level_loadError(
				$name,
				KnownTranslationFactory::pocketmine_level_unknownGenerator($provider->getWorldData()->getGenerator())
			)));
			return false;
		}
		try{
			$generatorEntry->validateGeneratorOptions($provider->getWorldData()->getGeneratorOptions());
		}catch(InvalidGeneratorOptionsException $e){
			$this->server->getLogger()->error($this->server->getLanguage()->translate(KnownTranslationFactory::pocketmine_level_loadError(
				$name,
				KnownTranslationFactory::pocketmine_level_invalidGeneratorOptions(
					$provider->getWorldData()->getGeneratorOptions(),
					$provider->getWorldData()->getGenerator(),
					$e->getMessage()
				)
			)));
			return false;
		}
		if(!($provider instanceof WritableWorldProvider)){
			if(!$autoUpgrade){
				throw new UnsupportedWorldFormatException("World \"$name\" is in an unsupported format and needs to be upgraded");
			}
			$this->server->getLogger()->notice($this->server->getLanguage()->translate(KnownTranslationFactory::pocketmine_level_conversion_start($name)));

			$providerClass = $this->providerManager->getDefault();
			$converter = new FormatConverter($provider, $providerClass, Path::join($this->server->getDataPath(), "backups", "worlds"), $this->server->getLogger());
			$converter->execute();
			$provider = $providerClass->fromPath($path, new \PrefixedLogger($this->server->getLogger(), "World Provider: $name"));

			$this->server->getLogger()->notice($this->server->getLanguage()->translate(KnownTranslationFactory::pocketmine_level_conversion_finish($name, $converter->getBackupPath())));
		}

		$world = new World($this->server, $name, $provider, $this->server->getAsyncPool());

		$this->worlds[$world->getId()] = $world;
		$world->setAutoSave($this->autoSave);

		(new WorldLoadEvent($world))->call();

		$this->preloadSpawnChunks($world);

		return true;
	}

	/**
	 * Warm spawn terrain so the first player does not wait on a cold LevelDB read
	 * for the same radius used by chunk-sending.spawn-radius.
	 */
	private function preloadSpawnChunks(World $world) : void{
		$radius = max(1, $this->server->getConfigGroup()->getPropertyInt("chunk-sending.spawn-radius", 4));
		$spawn = $world->getSpawnLocation();
		$cx = $spawn->getFloorX() >> Chunk::COORD_BIT_SIZE;
		$cz = $spawn->getFloorZ() >> Chunk::COORD_BIT_SIZE;
		$loaded = 0;
		for($x = $cx - $radius; $x <= $cx + $radius; ++$x){
			for($z = $cz - $radius; $z <= $cz + $radius; ++$z){
				if($world->loadChunk($x, $z) !== null){
					++$loaded;
				}
			}
		}
		$this->server->getLogger()->debug("Preloaded $loaded spawn chunks for world \"" . $world->getFolderName() . "\" (radius $radius)");
	}

	/**
	 * Generates a new world if it does not exist
	 *
	 * @throws \InvalidArgumentException
	 */
	public function generateWorld(string $name, WorldCreationOptions $options, bool $backgroundGeneration = true) : bool{
		if(trim($name) === "" || $this->isWorldGenerated($name)){
			return false;
		}

		$providerEntry = $this->providerManager->getDefault();

		$path = $this->getWorldPath($name);
		$providerEntry->generate($path, $name, $options);

		$world = new World($this->server, $name, $providerEntry->fromPath($path, new \PrefixedLogger($this->server->getLogger(), "World Provider: $name")), $this->server->getAsyncPool());
		$this->worlds[$world->getId()] = $world;

		$world->setAutoSave($this->autoSave);

		(new WorldInitEvent($world))->call();

		(new WorldLoadEvent($world))->call();
		$this->preloadSpawnChunks($world);

		if($backgroundGeneration){
			$this->server->getLogger()->notice($this->server->getLanguage()->translate(KnownTranslationFactory::pocketmine_level_backgroundGeneration($name)));

			$spawnLocation = $world->getSpawnLocation();
			$centerX = $spawnLocation->getFloorX() >> Chunk::COORD_BIT_SIZE;
			$centerZ = $spawnLocation->getFloorZ() >> Chunk::COORD_BIT_SIZE;

			$selected = iterator_to_array((new ChunkSelector())->selectChunks(8, $centerX, $centerZ), preserve_keys: false);
			$done = 0;
			$total = count($selected);
			foreach($selected as $index){
				World::getXZ($index, $chunkX, $chunkZ);
				$world->orderChunkPopulation($chunkX, $chunkZ, null)->onCompletion(
					static function() use ($world, &$done, $total) : void{
						$oldProgress = (int) floor(($done / $total) * 100);
						$newProgress = (int) floor((++$done / $total) * 100);
						if(intdiv($oldProgress, 10) !== intdiv($newProgress, 10) || $done === $total || $done === 1){
							$world->getLogger()->info($world->getServer()->getLanguage()->translate(KnownTranslationFactory::pocketmine_level_spawnTerrainGenerationProgress(strval($done), strval($total), strval($newProgress))));
						}
					},
					static function() : void{
						//NOOP: we don't care if the world was unloaded
					});
			}
		}

		return true;
	}

	public function getWorldPath(string $name) : string{
		return Path::join($this->dataPath, $name) . "/"; //TODO: check if we still need the trailing dirsep (I'm a little scared to remove it)
	}

	/**
	 * Folder names under the worlds data path that look like generated worlds (loaded or not).
	 *
	 * @return list<string>
	 */
	public function getWorldFolderNames() : array{
		if(!is_dir($this->dataPath)){
			return [];
		}
		$names = [];
		$entries = scandir($this->dataPath, SCANDIR_SORT_NONE);
		if($entries === false){
			return [];
		}
		foreach($entries as $entry){
			if($entry === "." || $entry === ".."){
				continue;
			}
			if($this->isWorldGenerated($entry)){
				$names[] = $entry;
			}
		}
		sort($names);
		return array_values($names);
	}

	/**
	 * Copies a world folder on disk and optionally loads the clone.
	 * Source must have no players; if loaded, it is briefly unloaded for a safe LevelDB copy.
	 *
	 * @throws \InvalidArgumentException
	 * @throws WorldException
	 */
	public function cloneWorld(string $sourceName, string $destName, bool $loadAfter = true) : ?World{
		return (new WorldCloner($this))->cloneWorld($sourceName, $destName, $loadAfter);
	}

	/**
	 * Deletes a world folder from disk. Moves players to the default world first.
	 * Refuses to delete the current default world.
	 *
	 * @throws \InvalidArgumentException
	 * @throws WorldException
	 */
	public function deleteWorld(string $name) : void{
		$name = $this->validateWorldFolderName($name);
		if(!$this->isWorldGenerated($name)){
			throw new \InvalidArgumentException("World \"$name\" does not exist on disk");
		}
		$world = $this->getWorldByName($name);
		if($world !== null && $world === $this->defaultWorld){
			throw new \InvalidArgumentException("Cannot delete the default world");
		}
		if($world !== null){
			$fallback = $this->defaultWorld;
			foreach($world->getPlayers() as $player){
				if($fallback !== null){
					try{
						$player->teleport($fallback->getSafeSpawn());
					}catch(WorldException){
						$player->disconnect("World deleted");
					}
				}else{
					$player->disconnect("World deleted");
				}
			}
			if(!$this->unloadWorld($world, true)){
				throw new WorldException("Failed to unload world \"$name\" for deletion");
			}
		}
		$path = rtrim($this->getWorldPath($name), "/\\");
		if(is_dir($path)){
			\pocketmine\utils\Filesystem::recursiveUnlink($path);
		}
	}

	/**
	 * Renames a world folder and LevelName. Refuses to rename the default world.
	 *
	 * @throws \InvalidArgumentException
	 * @throws WorldException
	 */
	public function renameWorld(string $from, string $to) : void{
		$from = $this->validateWorldFolderName($from);
		$to = $this->validateWorldFolderName($to);
		if($from === $to){
			throw new \InvalidArgumentException("Source and destination names must differ");
		}
		if(!$this->isWorldGenerated($from)){
			throw new \InvalidArgumentException("World \"$from\" does not exist on disk");
		}
		$toPath = rtrim($this->getWorldPath($to), "/\\");
		if(file_exists($toPath)){
			throw new \InvalidArgumentException("World \"$to\" already exists");
		}
		$world = $this->getWorldByName($from);
		if($world !== null && $world === $this->defaultWorld){
			throw new \InvalidArgumentException("Cannot rename the default world");
		}
		if($world !== null){
			if(count($world->getPlayers()) > 0){
				throw new WorldException("Cannot rename world \"$from\" while players are inside; move them out first");
			}
			$world->save(true);
			if(!$this->unloadWorld($world, true)){
				throw new WorldException("Failed to unload world \"$from\" for rename");
			}
		}
		$fromPath = rtrim($this->getWorldPath($from), "/\\");
		if(!rename($fromPath, $toPath)){
			throw new WorldException("Failed to rename world folder \"$from\" -> \"$to\"");
		}
		// Reuse cloner LevelName rewrite via temporary WorldCloner reflection-free path:
		$providers = $this->providerManager->getMatchingProviders($toPath . "/");
		if(count($providers) === 1){
			$entry = array_shift($providers);
			$provider = $entry->fromPath($toPath . "/", new \PrefixedLogger($this->server->getLogger(), "WorldRename: $to"));
			try{
				$data = $provider->getWorldData();
				$data->setName($to);
				$data->save();
			}finally{
				$provider->close();
			}
		}
		if(!$this->loadWorld($to)){
			throw new WorldException("Renamed world to \"$to\" but failed to load it");
		}
	}

	/**
	 * @throws \InvalidArgumentException
	 */
	private function validateWorldFolderName(string $name) : string{
		$name = trim($name);
		if($name === "" || $name === "." || $name === ".." || str_contains($name, "/") || str_contains($name, "\\")){
			throw new \InvalidArgumentException("Invalid world name \"$name\"");
		}
		return $name;
	}

	public function isWorldGenerated(string $name) : bool{
		if(trim($name) === ""){
			return false;
		}
		$path = $this->getWorldPath($name);
		if(!($this->getWorldByName($name) instanceof World)){
			return count($this->providerManager->getMatchingProviders($path)) > 0;
		}

		return true;
	}

	/**
	 * Searches all worlds for the entity with the specified ID.
	 * Useful for tracking entities across multiple worlds without needing strong references.
	 */
	public function findEntity(int $entityId) : ?Entity{
		foreach($this->worlds as $world){
			assert($world->isLoaded());
			if(($entity = $world->getEntity($entityId)) instanceof Entity){
				return $entity;
			}
		}

		return null;
	}

	public function tick(int $currentTick) : void{
		foreach($this->worlds as $k => $world){
			if(!isset($this->worlds[$k])){
				// World unloaded during the tick of a world earlier in this loop, perhaps by plugin
				continue;
			}

			$worldTime = microtime(true);
			$world->doTick($currentTick);
			$tickMs = (microtime(true) - $worldTime) * 1000;
			$world->tickRateTime = $tickMs;
			if($tickMs >= Server::TARGET_SECONDS_PER_TICK * 1000){
				$world->getLogger()->debug(sprintf("Tick took too long: %gms (%g ticks)", $tickMs, round($tickMs / (Server::TARGET_SECONDS_PER_TICK * 1000), 2)));
			}
		}

		if($this->autoSave && ++$this->autoSaveTicker >= $this->autoSaveTicks){
			$this->autoSaveTicker = 0;
			$this->server->getLogger()->debug("[Auto Save] Saving worlds...");
			$start = microtime(true);
			$this->doAutoSave();
			$time = microtime(true) - $start;
			$this->server->getLogger()->debug("[Auto Save] Save completed in " . ($time >= 1 ? round($time, 3) . "s" : round($time * 1000) . "ms"));
		}
	}

	public function getAutoSave() : bool{
		return $this->autoSave;
	}

	public function setAutoSave(bool $value) : void{
		$this->autoSave = $value;
		foreach($this->worlds as $world){
			$world->setAutoSave($this->autoSave);
		}
	}

	/**
	 * Returns the period in ticks after which loaded worlds will be automatically saved to disk.
	 */
	public function getAutoSaveInterval() : int{
		return $this->autoSaveTicks;
	}

	public function setAutoSaveInterval(int $autoSaveTicks) : void{
		if($autoSaveTicks <= 0){
			throw new \InvalidArgumentException("Autosave ticks must be positive");
		}
		$this->autoSaveTicks = $autoSaveTicks;
	}

	private function doAutoSave() : void{
		foreach($this->worlds as $world){
			foreach($world->getPlayers() as $player){
				if($player->spawned){
					$player->save();
				}
			}
			$world->save(false);
		}
	}
}
