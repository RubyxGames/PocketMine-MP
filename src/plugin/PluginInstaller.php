<?php

declare(strict_types=1);

namespace pocketmine\plugin;

use pocketmine\Server;
use pocketmine\utils\Filesystem;
use pocketmine\utils\Internet;
use Symfony\Component\Filesystem\Path;
use function basename;
use function file_put_contents;
use function is_dir;
use function is_file;
use function mkdir;
use function parse_url;
use function preg_match;
use function strlen;
use function str_contains;
use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function sys_get_temp_dir;
use function tempnam;
use function trim;
use function unlink;
use const PHP_URL_PATH;
use const PHP_URL_SCHEME;

/**
 * Downloads a plugin .phar or .zip into the plugins directory.
 * Callers may load/enable via PluginManager::loadPlugins($path) without a full restart.
 */
final class PluginInstaller{

	public function __construct(
		private Server $server
	){}

	/**
	 * @throws \InvalidArgumentException
	 * @throws \RuntimeException
	 */
	public function installFromUrl(string $url, int $maxMb = 32) : string{
		$url = trim($url);
		$scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
		if($scheme !== "https"){
			throw new \InvalidArgumentException("Only HTTPS URLs are allowed");
		}

		$maxBytes = $maxMb * 1024 * 1024;
		$error = null;
		$result = Internet::getURL($url, 30, [], $error);
		if($result === null){
			throw new \RuntimeException("Download failed: " . ($error ?? "unknown error"));
		}
		$body = $result->getBody();
		if(strlen($body) === 0){
			throw new \RuntimeException("Download returned an empty body");
		}
		if(strlen($body) > $maxBytes){
			throw new \RuntimeException("Download exceeds max size of {$maxMb} MB");
		}

		$pathPart = (string) parse_url($url, PHP_URL_PATH);
		$base = basename($pathPart !== "" ? $pathPart : "plugin");
		$pluginsDir = $this->server->getPluginPath();

		if($this->looksLikePhar($body, $base)){
			$name = str_ends_with(strtolower($base), ".phar") ? $base : ($base . ".phar");
			$name = $this->sanitizeFileName($name);
			$dest = Path::join($pluginsDir, $name);
			if(is_file($dest) || is_dir($dest)){
				throw new \RuntimeException("Target already exists: $dest");
			}
			try{
				Filesystem::safeFilePutContents($dest, $body);
			}catch(\RuntimeException $e){
				throw new \RuntimeException("Failed to write $dest: " . $e->getMessage(), 0, $e);
			}
			return $dest;
		}

		if($this->looksLikeZip($body, $base)){
			return $this->installZipFolderPlugin($body, $pluginsDir);
		}

		throw new \InvalidArgumentException("URL content is neither a .phar nor a zip with plugin.yml");
	}

	private function looksLikePhar(string $body, string $base) : bool{
		if(str_ends_with(strtolower($base), ".phar")){
			return true;
		}
		return str_starts_with($body, "<?php") && str_contains($body, "__HALT_COMPILER");
	}

	private function looksLikeZip(string $body, string $base) : bool{
		if(str_ends_with(strtolower($base), ".zip")){
			return true;
		}
		return str_starts_with($body, "PK");
	}

	/**
	 * @throws \RuntimeException
	 */
	private function installZipFolderPlugin(string $body, string $pluginsDir) : string{
		$tmp = tempnam(sys_get_temp_dir(), "pmplugin");
		if($tmp === false){
			throw new \RuntimeException("Failed to create temp file");
		}
		try{
			file_put_contents($tmp, $body);
			$zip = new \ZipArchive();
			if($zip->open($tmp) !== true){
				throw new \RuntimeException("Downloaded file is not a valid zip archive");
			}
			try{
				$pluginYml = $this->findPluginYmlInZip($zip);
				if($pluginYml === null){
					throw new \RuntimeException("Zip does not contain plugin.yml");
				}
				$description = new PluginDescription($pluginYml);
				$folderName = $this->sanitizeFileName($description->getName());
				$dest = Path::join($pluginsDir, $folderName);
				if(is_file($dest) || is_dir($dest)){
					throw new \RuntimeException("Target already exists: $dest");
				}
				if(!mkdir($dest) && !is_dir($dest)){
					throw new \RuntimeException("Failed to create $dest");
				}
				if(!$zip->extractTo($dest)){
					throw new \RuntimeException("Failed to extract zip to $dest");
				}
				// If zip had a single root folder, flatten is not required when plugin.yml is at dest root;
				// if plugin.yml ended up nested, move is complex — require root-level layout.
				if(!is_file(Path::join($dest, "plugin.yml"))){
					throw new \RuntimeException("Zip must contain plugin.yml at the archive root (got nested layout)");
				}
				if(!is_dir(Path::join($dest, "src"))){
					throw new \RuntimeException("Zip must contain a src/ directory next to plugin.yml");
				}
				return $dest;
			}finally{
				$zip->close();
			}
		}finally{
			@unlink($tmp);
		}
	}

	private function findPluginYmlInZip(\ZipArchive $zip) : ?string{
		$root = $zip->getFromName("plugin.yml");
		if($root !== false && $root !== ""){
			return $root;
		}
		return null;
	}

	private function sanitizeFileName(string $name) : string{
		$name = basename(str_replace(["\\", "/"], "", $name));
		if($name === "" || $name === "." || $name === ".." || preg_match('/[^\w.\-]/', $name) === 1){
			throw new \InvalidArgumentException("Invalid plugin file/folder name \"$name\"");
		}
		return $name;
	}
}
