<?php

declare(strict_types=1);

namespace pocketmine\plugin;

use Symfony\Component\Filesystem\Path;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function preg_match;
use function trim;

/**
 * Persists an integer data schema version under plugin_data/<name>/data-version.
 */
final class PluginDataVersion{

	public static function pathFor(PluginBase $plugin) : string{
		return Path::join($plugin->getDataFolder(), "data-version");
	}

	public static function read(PluginBase $plugin) : ?int{
		$path = self::pathFor($plugin);
		if(!file_exists($path)){
			return null;
		}
		$raw = @file_get_contents($path);
		if($raw === false){
			return null;
		}
		$raw = trim($raw);
		if($raw === "" || preg_match('/^-?\d+$/', $raw) !== 1){
			throw new \RuntimeException("Corrupt data-version file for plugin " . $plugin->getName());
		}
		return (int) $raw;
	}

	public static function write(PluginBase $plugin, int $version) : void{
		$folder = $plugin->getDataFolder();
		if(!is_dir($folder) && !@mkdir($folder, 0777, true) && !is_dir($folder)){
			throw new \RuntimeException("Failed to create data folder for " . $plugin->getName());
		}
		if(file_put_contents(self::pathFor($plugin), (string) $version . "\n") === false){
			throw new \RuntimeException("Failed to write data-version for " . $plugin->getName());
		}
	}

	/**
	 * @phpstan-param \Closure(int $from, int $to) : void $migrate
	 */
	public static function ensure(PluginBase $plugin, int $codeVersion, \Closure $migrate) : void{
		if($codeVersion <= 0){
			return;
		}
		$stored = self::read($plugin);
		if($stored === null){
			self::write($plugin, $codeVersion);
			return;
		}
		if($stored > $codeVersion){
			throw new \RuntimeException(
				"Plugin " . $plugin->getName() . " data-version $stored is newer than code version $codeVersion"
			);
		}
		if($stored < $codeVersion){
			$migrate($stored, $codeVersion);
			self::write($plugin, $codeVersion);
		}
	}
}
