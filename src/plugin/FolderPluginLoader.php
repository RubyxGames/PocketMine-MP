<?php

declare(strict_types=1);

namespace pocketmine\plugin;

use pocketmine\thread\ThreadSafeClassLoader;
use function file_exists;
use function file_get_contents;
use function is_dir;

/**
 * Loads development-style plugins from a directory containing plugin.yml and src/.
 * Replaces the need for DevTools' FolderPluginLoader at runtime.
 */
class FolderPluginLoader implements PluginLoader{

	public function __construct(
		private ThreadSafeClassLoader $loader
	){}

	public function canLoadPlugin(string $path) : bool{
		return is_dir($path) && file_exists($path . "/plugin.yml") && file_exists($path . "/src/");
	}

	public function loadPlugin(string $file) : void{
		$description = $this->getPluginDescription($file);
		if($description !== null){
			$this->loader->addPath($description->getSrcNamespacePrefix(), "$file/src");
		}
	}

	public function getPluginDescription(string $file) : ?PluginDescription{
		if(!$this->canLoadPlugin($file)){
			return null;
		}
		$yaml = @file_get_contents($file . "/plugin.yml");
		if($yaml === false || $yaml === ""){
			return null;
		}
		return new PluginDescription($yaml);
	}

	public function getAccessProtocol() : string{
		return "";
	}
}
