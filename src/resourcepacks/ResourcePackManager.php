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

namespace pocketmine\resourcepacks;

use pocketmine\resourcepacks\compression\ResourcePackCompressionCache;
use pocketmine\resourcepacks\compression\ZipDeflateResourcePackCompressor;
use pocketmine\utils\Config;
use pocketmine\utils\Filesystem;
use pocketmine\utils\Utils;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Filesystem\Path;
use function array_keys;
use function bin2hex;
use function copy;
use function count;
use function file_exists;
use function gettype;
use function is_array;
use function is_dir;
use function is_float;
use function is_int;
use function is_string;
use function max;
use function min;
use function mkdir;
use function rtrim;
use function str_contains;
use function strlen;
use function strtolower;
use const DIRECTORY_SEPARATOR;

class ResourcePackManager{
	private string $path;
	private \Logger $logger;
	private bool $serverForceResources = false;
	private bool $compressPacks = true;
	private int $compressLevel = 9;
	private int $packChunkSize = 256 * 1024;
	private int $packChunkConcurrency = 1;
	private ResourcePackCompressionCache $compressionCache;

	/**
	 * @var ResourcePack[]
	 * @phpstan-var list<ResourcePack>
	 */
	private array $resourcePacks = [];

	/**
	 * @var ResourcePack[]
	 * @phpstan-var array<string, ResourcePack>
	 */
	private array $uuidList = [];

	/**
	 * @var string[]
	 * @phpstan-var array<string, string>
	 */
	private array $encryptionKeys = [];

	/**
	 * @var ResourcePack[][]
	 * @phpstan-var array<string, list<ResourcePack>>
	 */
	private array $worldStacks = [];

	/**
	 * @var ResourcePack[][]
	 * @phpstan-var array<string, list<ResourcePack>>
	 */
	private array $namedStacks = [];

	/**
	 * @var string[] pack UUID (lowercase) => CDN URL
	 * @phpstan-var array<string, string>
	 */
	private array $cdnUrls = [];

	/**
	 * @var string[] pack UUID => "uuid|version|sha256hex" fingerprint from last successful load
	 * @phpstan-var array<string, string>
	 */
	private array $packFingerprints = [];

	/**
	 * @param string $path Path to resource-packs directory.
	 */
	public function __construct(string $path, \Logger $logger){
		$this->path = $path;
		$this->logger = $logger;

		if(!file_exists($this->path)){
			$logger->debug("Resource packs path $path does not exist, creating directory");
			mkdir($this->path);
		}elseif(!is_dir($this->path)){
			throw new \InvalidArgumentException("Resource packs path $path exists and is not a directory");
		}

		$resourcePacksYml = Path::join($this->path, "resource_packs.yml");
		if(!file_exists($resourcePacksYml)){
			copy(Path::join(\pocketmine\RESOURCE_PATH, "resource_packs.yml"), $resourcePacksYml);
		}

		$this->loadFromConfig();
	}

	/**
	 * Reloads packs and settings from resource_packs.yml. Already-connected players keep their current packs until reconnect.
	 */
	public function reload() : void{
		$this->logger->info("Reloading resource packs...");
		$this->loadFromConfig();
		$this->logger->info("Resource pack reload complete (" . count($this->resourcePacks) . " global, " .
			count($this->worldStacks) . " world stacks, " . count($this->namedStacks) . " named stacks)");
	}

	private function loadFromConfig() : void{
		$config = new Config(Path::join($this->path, "resource_packs.yml"), Config::YAML, []);

		$this->serverForceResources = (bool) $config->get("force_resources", false);
		$this->compressPacks = (bool) $config->get("compress_packs", true);
		$this->compressLevel = max(0, min(9, (int) $config->get("compress_level", 9)));
		$this->packChunkSize = max(16 * 1024, min(1024 * 1024, (int) $config->get("pack_chunk_size_kb", 256) * 1024));
		$this->packChunkConcurrency = max(1, min(8, (int) $config->get("pack_chunk_concurrency", 1)));
		$cacheDirName = (string) $config->get("compress_cache_dir", ".compressed");
		if($cacheDirName === "" || str_contains($cacheDirName, "..") || str_contains($cacheDirName, "/") || str_contains($cacheDirName, "\\")){
			$this->logger->warning("Invalid compress_cache_dir \"$cacheDirName\", falling back to .compressed");
			$cacheDirName = ".compressed";
		}

		$this->compressionCache = new ResourcePackCompressionCache(
			Path::join($this->path, $cacheDirName),
			new ZipDeflateResourcePackCompressor(),
			$this->compressLevel,
			$this->logger
		);
		if($this->compressPacks){
			$this->logger->info("Resource pack ZIP compression enabled (level {$this->compressLevel}, cache \"$cacheDirName\")");
		}else{
			$this->logger->info("Resource pack folder zip cache enabled (recompress disabled, cache \"$cacheDirName\")");
		}
		$this->logger->info("Resource pack send settings: chunk " . (int) ($this->packChunkSize / 1024) . " KiB, concurrency {$this->packChunkConcurrency}");

		$previousFingerprints = $this->packFingerprints;
		$this->resourcePacks = [];
		$this->uuidList = [];
		$this->encryptionKeys = [];
		$this->worldStacks = [];
		$this->namedStacks = [];
		$this->cdnUrls = [];
		$this->packFingerprints = [];

		$this->logger->info("Loading resource packs...");

		$resourceStack = $config->get("resource_stack", []);
		if(!is_array($resourceStack)){
			throw new \InvalidArgumentException("\"resource_stack\" key should contain a list of pack names");
		}
		$this->resourcePacks = $this->loadStackEntries($resourceStack, "resource_stack", $previousFingerprints);

		$worldStacks = $config->get("world_stacks", []);
		if(is_array($worldStacks)){
			foreach(Utils::stringifyKeys($worldStacks) as $worldName => $entries){
				if(!is_array($entries)){
					$this->logger->critical("world_stacks.$worldName must be a list of pack entries");
					continue;
				}
				$this->worldStacks[strtolower((string) $worldName)] = $this->loadStackEntries($entries, "world_stacks.$worldName", $previousFingerprints);
			}
		}

		$namedStacks = $config->get("named_stacks", []);
		if(is_array($namedStacks)){
			foreach(Utils::stringifyKeys($namedStacks) as $stackName => $entries){
				if(!is_array($entries)){
					$this->logger->critical("named_stacks.$stackName must be a list of pack entries");
					continue;
				}
				$this->namedStacks[strtolower((string) $stackName)] = $this->loadStackEntries($entries, "named_stacks.$stackName", $previousFingerprints);
			}
		}

		$cdnUrls = $config->get("cdn_urls", []);
		if(is_array($cdnUrls)){
			foreach(Utils::stringifyKeys($cdnUrls) as $key => $url){
				if(!is_string($url) || $url === ""){
					$this->logger->critical("cdn_urls.$key must be a non-empty URL string");
					continue;
				}
				$resolvedUuid = $this->resolveCdnKeyToUuid((string) $key);
				if($resolvedUuid === null){
					$this->logger->warning("cdn_urls key \"$key\" did not match a loaded pack UUID or filename; ignored");
					continue;
				}
				$this->cdnUrls[$resolvedUuid] = $url;
				$this->logger->info("CDN URL for pack $resolvedUuid: $url");
			}
		}

		$this->logger->debug("Successfully loaded " . count($this->resourcePacks) . " global resource packs");
	}

	/**
	 * @param mixed[]              $entries
	 * @param array<string, string> $previousFingerprints
	 * @return ResourcePack[]
	 * @phpstan-return list<ResourcePack>
	 */
	private function loadStackEntries(array $entries, string $where, array $previousFingerprints) : array{
		$loaded = [];
		foreach(Utils::promoteKeys($entries) as $pos => $entry){
			try{
				[$packName, $keyFile] = $this->parseStackEntry($entry, $where, $pos);
			}catch(ResourcePackException $e){
				$this->logger->critical($e->getMessage());
				continue;
			}

			$keyPathCandidate = $keyFile !== null
				? Path::join($this->path, $keyFile)
				: Path::join($this->path, $packName . ".key");
			$orphanKey = ($keyFile === null && file_exists($keyPathCandidate));

			try{
				$newPack = $this->loadPackFromPath(Path::join($this->path, $packName));
				$index = strtolower($newPack->getPackId());
				if(!Uuid::isValid($index)){
					throw new ResourcePackException("Invalid UUID ($index)");
				}

				$fingerprint = $this->buildFingerprint($newPack);
				$this->packFingerprints[$index] = $fingerprint;
				if(isset($previousFingerprints[$index]) && $previousFingerprints[$index] === $fingerprint){
					$this->logger->debug("Pack $packName unchanged ($fingerprint)");
				}elseif(isset($previousFingerprints[$index])){
					$this->logger->info("Pack $packName content changed (uuid/version/hash)");
				}

				$this->uuidList[$index] = $newPack;
				$loaded[] = $newPack;

				$keyPath = null;
				if($keyFile !== null){
					$keyPath = Path::join($this->path, $keyFile);
				}elseif(file_exists(Path::join($this->path, $packName . ".key"))){
					$keyPath = Path::join($this->path, $packName . ".key");
				}

				if($keyPath !== null){
					if(!file_exists($keyPath)){
						throw new ResourcePackException("Encryption key file not found: $keyPath");
					}
					try{
						$key = Filesystem::fileGetContents($keyPath);
					}catch(\RuntimeException $e){
						throw new ResourcePackException("Could not read encryption key file: " . $e->getMessage(), 0, $e);
					}
					$key = rtrim($key, "\r\n");
					if(strlen($key) !== 32){
						throw new ResourcePackException("Invalid encryption key length for \"$packName\", must be exactly 32 bytes (got " . strlen($key) . ")");
					}
					$this->encryptionKeys[$index] = $key;
					$this->logger->info("Encrypted resource pack \"$packName\" (uuid=$index)");
				}
			}catch(ResourcePackException $e){
				$this->logger->critical("Could not load resource pack \"$packName\" ($where): " . $e->getMessage());
				if($orphanKey || $keyFile !== null){
					$this->logger->warning("Encryption key was configured for \"$packName\" but the pack failed to load");
				}
			}
		}
		return $loaded;
	}

	/**
	 * @return array{string, ?string} pack filename/folder, optional key file
	 */
	private function parseStackEntry(mixed $entry, string $where, int|string $pos) : array{
		if(is_string($entry) || is_int($entry) || is_float($entry)){
			return [(string) $entry, null];
		}
		if(!is_array($entry)){
			throw new ResourcePackException("Found invalid entry in $where at offset $pos of type " . gettype($entry));
		}
		$pack = $entry["pack"] ?? $entry["name"] ?? null;
		if(!is_string($pack) || $pack === ""){
			throw new ResourcePackException("Stack entry in $where at offset $pos must include string \"pack\"");
		}
		$keyFile = $entry["key_file"] ?? $entry["key"] ?? null;
		if($keyFile !== null && !is_string($keyFile)){
			throw new ResourcePackException("key_file for \"$pack\" in $where must be a string");
		}
		return [$pack, $keyFile];
	}

	private function resolveCdnKeyToUuid(string $key) : ?string{
		$lower = strtolower($key);
		if(Uuid::isValid($lower) && isset($this->uuidList[$lower])){
			return $lower;
		}
		// filename / folder name: find pack loaded from that basename by scanning uuid list packs' paths is hard;
		// match against stack by re-resolving path
		$packPath = Path::join($this->path, $key);
		if(file_exists($packPath)){
			try{
				$pack = $this->loadPackFromPath($packPath);
				$id = strtolower($pack->getPackId());
				if(isset($this->uuidList[$id])){
					return $id;
				}
			}catch(ResourcePackException){
				return null;
			}
		}
		return isset($this->uuidList[$lower]) ? $lower : null;
	}

	private function loadPackFromPath(string $packPath) : ResourcePack{
		if(!file_exists($packPath)){
			throw new ResourcePackException("File or directory not found");
		}
		if(is_dir($packPath)){
			$resolved = $this->compressionCache->resolveDirectory($packPath);
			return new ZippedResourcePack($resolved);
		}

		$info = new \SplFileInfo($packPath);
		switch($info->getExtension()){
			case "zip":
			case "mcpack":
				$resolved = $packPath;
				if($this->compressPacks){
					$resolved = $this->compressionCache->resolve($packPath);
				}
				return new ZippedResourcePack($resolved);
		}

		throw new ResourcePackException("Format not recognized");
	}

	private function buildFingerprint(ResourcePack $pack) : string{
		return strtolower($pack->getPackId()) . "|" . $pack->getPackVersion() . "|" . bin2hex($pack->getSha256());
	}

	/**
	 * Content fingerprint (uuid|version|sha256hex) for cache/debug use.
	 */
	public function getPackContentFingerprint(ResourcePack $pack) : string{
		$id = strtolower($pack->getPackId());
		return $this->packFingerprints[$id] ?? $this->buildFingerprint($pack);
	}

	/**
	 * Chunk size in bytes used when sending packs to clients.
	 */
	public function getPackChunkSize() : int{
		return $this->packChunkSize;
	}

	/**
	 * Max concurrent pack-chunk sends per connecting session.
	 */
	public function getPackChunkConcurrency() : int{
		return $this->packChunkConcurrency;
	}

	/**
	 * Returns the directory which resource packs are loaded from.
	 */
	public function getPath() : string{
		return $this->path . DIRECTORY_SEPARATOR;
	}

	/**
	 * Returns whether players must accept resource packs in order to join.
	 */
	public function resourcePacksRequired() : bool{
		return $this->serverForceResources;
	}

	/**
	 * Sets whether players must accept resource packs in order to join.
	 */
	public function setResourcePacksRequired(bool $value) : void{
		$this->serverForceResources = $value;
	}

	/**
	 * Returns an array of resource packs in use, sorted in order of priority.
	 * @return ResourcePack[]
	 * @phpstan-return list<ResourcePack>
	 */
	public function getResourceStack() : array{
		return $this->resourcePacks;
	}

	/**
	 * Stack for a world folder name, or the global stack if no world-specific stack is configured.
	 *
	 * @return ResourcePack[]
	 * @phpstan-return list<ResourcePack>
	 */
	public function getResourceStackForWorld(string $worldName) : array{
		return $this->worldStacks[strtolower($worldName)] ?? $this->resourcePacks;
	}

	/**
	 * Named stack from config, or null if unknown.
	 *
	 * @return ResourcePack[]|null
	 * @phpstan-return list<ResourcePack>|null
	 */
	public function getNamedStack(string $name) : ?array{
		return $this->namedStacks[strtolower($name)] ?? null;
	}

	/**
	 * @return string[] UUID => CDN URL
	 * @phpstan-return array<string, string>
	 */
	public function getCdnUrls() : array{
		return $this->cdnUrls;
	}

	/**
	 * Encryption keys for every pack in the given stack (UUID => key).
	 *
	 * @param ResourcePack[] $stack
	 * @return string[]
	 * @phpstan-param list<ResourcePack> $stack
	 * @phpstan-return array<string, string>
	 */
	public function getEncryptionKeysForStack(array $stack) : array{
		$keys = [];
		foreach($stack as $pack){
			$key = $this->getPackEncryptionKey($pack->getPackId());
			if($key !== null){
				$keys[$pack->getPackId()] = $key;
			}
		}
		return $keys;
	}

	/**
	 * Sets the resource packs to use. Packs earliest in the list will appear at the top of the stack (maximum
	 * priority), and later ones will appear below (lower priority), in the same manner as the Bedrock resource packs
	 * screen in-game.
	 *
	 * @param ResourcePack[] $resourceStack
	 * @phpstan-param list<ResourcePack> $resourceStack
	 */
	public function setResourceStack(array $resourceStack) : void{
		$uuidList = [];
		$resourcePacks = [];
		foreach($resourceStack as $pack){
			$uuid = strtolower($pack->getPackId());
			if(!Uuid::isValid($uuid)){
				throw new \InvalidArgumentException("Invalid resource pack UUID ($uuid)");
			}
			if(isset($uuidList[$uuid])){
				throw new \InvalidArgumentException("Cannot load two resource pack with the same UUID ($uuid)");
			}
			$uuidList[$uuid] = $pack;
			$resourcePacks[] = $pack;
		}
		$this->resourcePacks = $resourcePacks;
		$this->uuidList = $uuidList;
	}

	/**
	 * Returns the resource pack matching the specified UUID string, or null if the ID was not recognized.
	 */
	public function getPackById(string $id) : ?ResourcePack{
		return $this->uuidList[strtolower($id)] ?? null;
	}

	/**
	 * Returns an array of pack IDs for packs currently in use.
	 * @return string[]
	 */
	public function getPackIdList() : array{
		return array_keys($this->uuidList);
	}

	/**
	 * Returns the key with which the pack was encrypted, or null if the pack has no key.
	 */
	public function getPackEncryptionKey(string $id) : ?string{
		return $this->encryptionKeys[strtolower($id)] ?? null;
	}

	/**
	 * Sets the encryption key to use for decrypting the specified resource pack. The pack will **NOT** be decrypted by
	 * PocketMine-MP; the key is simply passed to the client to allow it to decrypt the pack after downloading it.
	 */
	public function setPackEncryptionKey(string $id, ?string $key) : void{
		$id = strtolower($id);
		if($key === null){
			unset($this->encryptionKeys[$id]);
		}elseif(isset($this->uuidList[$id])){
			if(strlen($key) !== 32){
				throw new \InvalidArgumentException("Encryption key must be exactly 32 bytes long");
			}
			$this->encryptionKeys[$id] = $key;
		}else{
			throw new \InvalidArgumentException("Unknown pack ID $id");
		}
	}
}
