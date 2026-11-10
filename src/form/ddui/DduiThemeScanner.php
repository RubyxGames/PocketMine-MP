<?php

declare(strict_types=1);

namespace pocketmine\form\ddui;

use Ahc\Json\Comment as CommentedJsonDecoder;
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

final class DduiThemeScanner{
	private function __construct(){
	}

	/**
	 * @return list<DduiThemeStyle>
	 */
	public static function scan(ResourcePackManager $manager, \Logger $logger) : array{
		$base = $manager->getPath();
		$config = new \pocketmine\utils\Config(Path::join($base, "resource_packs.yml"), \pocketmine\utils\Config::YAML, []);
		$stack = $config->get("resource_stack", []);
		if(!is_array($stack)){
			return [];
		}
		$found = [];
		$seenIds = [];
		foreach(Utils::promoteKeys($stack) as $entry){
			$packName = self::packNameFromEntry($entry);
			if($packName === null){
				continue;
			}
			$packPath = Path::join($base, $packName);
			foreach(self::scanPackRoot($packPath, $packName) as $style){
				$id = strtolower($style->getStyleId());
				if(isset($seenIds[$id])){
					$logger->debug("DDUI theme $id duplicated in {$style->getPackName()} (already from {$seenIds[$id]})");
					continue;
				}
				$seenIds[$id] = $style->getPackName();
				$found[] = $style;
			}
		}
		if(count($found) > 0){
			$logger->info("DDUI: discovered " . count($found) . " style(s) in resource packs");
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
	 * @return list<DduiThemeStyle>
	 */
	private static function scanPackRoot(string $packPath, string $packName) : array{
		if(is_dir($packPath)){
			return self::scanStylingDir(Path::join($packPath, "ddui", "styling"), $packName, $packPath);
		}
		if(is_file($packPath) && str_ends_with(strtolower($packPath), ".zip")){
			return self::scanZipPack($packPath, $packName);
		}

		return [];
	}

	/**
	 * @return list<DduiThemeStyle>
	 */
	private static function scanStylingDir(string $stylingDir, string $packName, string $pathRoot) : array{
		if(!is_dir($stylingDir)){
			return [];
		}
		$out = [];
		foreach(glob($stylingDir . "/*.json") ?: [] as $file){
			$styleId = self::parseStyleFile($file);
			if($styleId === null){
				continue;
			}
			$out[] = new DduiThemeStyle($styleId, $packName, Path::makeRelative($file, $pathRoot));
		}

		return $out;
	}

	/**
	 * @return list<DduiThemeStyle>
	 */
	private static function scanZipPack(string $zipPath, string $packName) : array{
		$archive = new \ZipArchive();
		if($archive->open($zipPath) !== true){
			return [];
		}
		$out = [];
		for($i = 0; $i < $archive->numFiles; $i++){
			$name = $archive->getNameIndex($i);
			if($name === false || !str_starts_with($name, "ddui/styling/") || !str_ends_with(strtolower($name), ".json")){
				continue;
			}
			$raw = $archive->getFromIndex($i);
			if($raw === false){
				continue;
			}
			$styleId = self::parseStyleJson($raw);
			if($styleId !== null){
				$out[] = new DduiThemeStyle($styleId, $packName, $name);
			}
		}
		$archive->close();

		return $out;
	}

	private static function parseStyleFile(string $path) : ?string{
		$raw = @file_get_contents($path);
		if($raw === false){
			return null;
		}

		return self::parseStyleJson($raw);
	}

	private static function parseStyleJson(string $json) : ?string{
		$data = json_decode($json, true);
		if(!is_array($data)){
			try{
				$decoded = (new CommentedJsonDecoder())->decode($json);
				$data = json_decode(json_encode($decoded, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
			}catch(\JsonException){
				return null;
			}
		}
		if(!is_array($data)){
			return null;
		}
		$ui = $data["minecraft:ui-style"] ?? null;
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
