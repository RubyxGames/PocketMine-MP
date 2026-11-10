<?php

declare(strict_types=1);

namespace pocketmine\form\ddui;

use pocketmine\resourcepacks\ResourcePackManager;
use pocketmine\utils\Utils;
use Symfony\Component\Filesystem\Path;
use function count;
use function file_get_contents;
use function glob;
use function is_array;
use function is_dir;
use function is_file;
use function is_string;
use function strtolower;

/** Discovers screen definition JSON under RP ddui/screens. */
final class DduiScreenScanner{
	/** @var array<string, true> screenId lower-case */
	private static array $knownScreens = [];

	private function __construct(){
	}

	public static function reloadFromPacks(ResourcePackManager $manager, \Logger $logger) : void{
		self::$knownScreens = [];
		foreach(self::scanPackStack($manager) as $screenId){
			self::$knownScreens[strtolower($screenId)] = true;
		}
		if(count(self::$knownScreens) > 0){
			$logger->info("DDUI: discovered " . count(self::$knownScreens) . " custom screen(s): " . implode(", ", array_keys(self::$knownScreens)));
		}
	}

	public static function hasScreen(string $screenId) : bool{
		return isset(self::$knownScreens[strtolower($screenId)]);
	}

	/**
	 * @return list<string>
	 */
	public static function scan(ResourcePackManager $manager, \Logger $logger) : array{
		self::reloadFromPacks($manager, $logger);

		return array_keys(self::$knownScreens);
	}

	/**
	 * @return list<string>
	 */
	private static function scanPackStack(ResourcePackManager $manager) : array{
		$base = $manager->getPath();
		$config = new \pocketmine\utils\Config(Path::join($base, "resource_packs.yml"), \pocketmine\utils\Config::YAML, []);
		$stack = $config->get("resource_stack", []);
		if(!is_array($stack)){
			return [];
		}
		$found = [];
		$seen = [];
		foreach(Utils::promoteKeys($stack) as $entry){
			$packName = self::packNameFromEntry($entry);
			if($packName === null){
				continue;
			}
			$packPath = Path::join($base, $packName);
			foreach(self::scanPack($packPath) as $screenId){
				$key = strtolower($screenId);
				if(isset($seen[$key])){
					continue;
				}
				$seen[$key] = true;
				$found[] = $screenId;
			}
		}
		return $found;
	}

	private static function packNameFromEntry(mixed $entry) : ?string{
		if(is_string($entry) || is_int($entry) || is_float($entry)){
			return (string) $entry;
		}
		if(is_array($entry)){
			$pack = $entry["pack"] ?? $entry["name"] ?? null;
			return is_string($pack) && $pack !== "" ? $pack : null;
		}

		return null;
	}

	/**
	 * @return list<string>
	 */
	private static function scanPack(string $packPath) : array{
		if(is_dir($packPath)){
			return self::scanScreensDir(Path::join($packPath, "ddui", "screens"));
		}
		if(is_file($packPath) && str_ends_with(strtolower($packPath), ".zip")){
			return self::scanZipPack($packPath);
		}

		return [];
	}

	/**
	 * @return list<string>
	 */
	private static function scanScreensDir(string $root) : array{
		if(!is_dir($root)){
			return [];
		}
		$out = [];
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
		);
		foreach($iterator as $file){
			if(!$file instanceof \SplFileInfo || !$file->isFile() || !str_ends_with(strtolower($file->getFilename()), ".json")){
				continue;
			}
			$id = self::parseScreenFile($file->getPathname());
			if($id !== null){
				$out[] = $id;
			}
		}

		return $out;
	}

	/**
	 * @return list<string>
	 */
	private static function scanZipPack(string $zipPath) : array{
		$archive = new \ZipArchive();
		if($archive->open($zipPath) !== true){
			return [];
		}
		$out = [];
		for($i = 0; $i < $archive->numFiles; $i++){
			$name = $archive->getNameIndex($i);
			if($name === false || !str_starts_with($name, "ddui/screens/") || !str_ends_with(strtolower($name), ".json")){
				continue;
			}
			$raw = $archive->getFromIndex($i);
			if($raw === false){
				continue;
			}
			$id = self::parseScreenJson($raw);
			if($id !== null){
				$out[] = $id;
			}
		}
		$archive->close();

		return $out;
	}

	private static function parseScreenFile(string $path) : ?string{
		$raw = @file_get_contents($path);
		if($raw === false){
			return null;
		}

		return self::parseScreenJson($raw);
	}

	private static function parseScreenJson(string $json) : ?string{
		$data = json_decode($json, true);
		if(!is_array($data)){
			return null;
		}
		$ui = $data["minecraft:data_driven_ui_screen"] ?? null;
		if(!is_array($ui)){
			return null;
		}
		$desc = $ui["description"] ?? null;
		if(!is_array($desc)){
			return null;
		}
		$id = $desc["identifier"] ?? null;

		return is_string($id) && $id !== "" ? $id : null;
	}
}
