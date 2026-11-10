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

namespace pocketmine\player;

use pocketmine\errorhandler\ErrorToExceptionHandler;
use pocketmine\nbt\BigEndianNbtSerializer;
use pocketmine\nbt\NbtDataException;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\TreeRoot;
use pocketmine\utils\Filesystem;
use pocketmine\utils\Utils;
use Symfony\Component\Filesystem\Path;
use function file_exists;
use function is_dir;
use function mkdir;
use function preg_match;
use function rename;
use function strtolower;
use function zlib_decode;
use function zlib_encode;
use const ZLIB_ENCODING_GZIP;

/**
 * Stores player data in a single .dat file per player. Each file is gzipped big-endian NBT.
 * When an XUID is provided, prefers players/xuid/<xuid>.dat and dual-writes name + xuid files on save.
 */
final class DatFilePlayerDataProvider implements PlayerDataProvider{

	public function __construct(
		private string $path
	){}

	private function getPlayerDataPath(string $username) : string{
		return Path::join($this->path, strtolower($username) . '.dat');
	}

	private function getXuidDataPath(string $xuid) : string{
		return Path::join($this->path, 'xuid', $xuid . '.dat');
	}

	private function normalizeXuid(?string $xuid) : ?string{
		if($xuid === null || $xuid === ""){
			return null;
		}
		if(preg_match('/^\d+$/', $xuid) !== 1){
			return null;
		}
		return $xuid;
	}

	private function handleCorruptedPlayerData(string $path) : void{
		rename($path, $path . '.bak');
	}

	public function hasData(string $name, ?string $xuid = null) : bool{
		$xuid = $this->normalizeXuid($xuid);
		if($xuid !== null && file_exists($this->getXuidDataPath($xuid))){
			return true;
		}
		return file_exists($this->getPlayerDataPath($name));
	}

	public function loadData(string $name, ?string $xuid = null) : ?CompoundTag{
		$name = strtolower($name);
		$xuid = $this->normalizeXuid($xuid);

		$path = null;
		if($xuid !== null){
			$xuidPath = $this->getXuidDataPath($xuid);
			if(file_exists($xuidPath)){
				$path = $xuidPath;
			}
		}
		if($path === null){
			$path = $this->getPlayerDataPath($name);
			if(!file_exists($path)){
				return null;
			}
		}

		return $this->readFile($path, $name);
	}

	private function readFile(string $path, string $label) : CompoundTag{
		try{
			$contents = Filesystem::fileGetContents($path);
		}catch(\RuntimeException $e){
			throw new PlayerDataLoadException("Failed to read player data file \"$path\": " . $e->getMessage(), 0, $e);
		}
		try{
			$decompressed = ErrorToExceptionHandler::trapAndRemoveFalse(fn() => zlib_decode($contents));
		}catch(\ErrorException $e){
			$this->handleCorruptedPlayerData($path);
			throw new PlayerDataLoadException("Failed to decompress raw player data for \"$label\": " . $e->getMessage(), 0, $e);
		}

		try{
			return (new BigEndianNbtSerializer())->read($decompressed)->mustGetCompoundTag();
		}catch(NbtDataException $e){
			$this->handleCorruptedPlayerData($path);
			throw new PlayerDataLoadException("Failed to decode NBT data for \"$label\": " . $e->getMessage(), 0, $e);
		}
	}

	public function saveData(string $name, CompoundTag $data, ?string $xuid = null) : void{
		$nbt = new BigEndianNbtSerializer();
		$contents = Utils::assumeNotFalse(zlib_encode($nbt->write(new TreeRoot($data)), ZLIB_ENCODING_GZIP), "zlib_encode() failed unexpectedly");

		$namePath = $this->getPlayerDataPath($name);
		try{
			Filesystem::safeFilePutContents($namePath, $contents);
		}catch(\RuntimeException $e){
			throw new PlayerDataSaveException("Failed to write player data file: " . $e->getMessage(), 0, $e);
		}

		$xuid = $this->normalizeXuid($xuid);
		if($xuid !== null){
			$xuidDir = Path::join($this->path, 'xuid');
			if(!is_dir($xuidDir) && !@mkdir($xuidDir, 0777, true) && !is_dir($xuidDir)){
				throw new PlayerDataSaveException("Failed to create xuid player data directory");
			}
			try{
				Filesystem::safeFilePutContents($this->getXuidDataPath($xuid), $contents);
			}catch(\RuntimeException $e){
				throw new PlayerDataSaveException("Failed to write xuid player data file: " . $e->getMessage(), 0, $e);
			}
		}
	}
}
