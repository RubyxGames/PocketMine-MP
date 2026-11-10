<?php

declare(strict_types=1);

namespace pocketmine\resourcepacks\compression;

use pocketmine\resourcepacks\ResourcePackException;
use Symfony\Component\Filesystem\Path;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use function basename;
use function copy;
use function count;
use function file_get_contents;
use function file_put_contents;
use function filemtime;
use function filesize;
use function is_array;
use function is_dir;
use function is_file;
use function json_decode;
use function json_encode;
use function max;
use function microtime;
use function mkdir;
use function realpath;
use function rename;
use function rtrim;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

/**
 * Caches DEFLATE-recompressed resource pack zips next to the originals.
 * Also builds zip archives from folder packs (directory + manifest.json).
 * Invalidates when source mtime/size (or folder tree stamp) or compression level change.
 */
final class ResourcePackCompressionCache{

	public function __construct(
		private readonly string $cacheDir,
		private readonly ResourcePackCompressor $compressor,
		private readonly int $level,
		private readonly \Logger $logger,
	){
		if($this->level < 0 || $this->level > 9){
			throw new \InvalidArgumentException("Compression level must be 0..9");
		}
	}

	/**
	 * Returns a path suitable for {@see \pocketmine\resourcepacks\ZippedResourcePack}:
	 * compressed cache copy when beneficial, otherwise the original source.
	 *
	 * @throws ResourcePackException
	 */
	public function resolve(string $sourcePath) : string{
		if(!is_file($sourcePath)){
			throw new ResourcePackException("Source pack not found: $sourcePath");
		}

		$sourceReal = realpath($sourcePath) ?: $sourcePath;
		$sourceSize = filesize($sourcePath);
		$sourceMtime = filemtime($sourcePath);
		if($sourceSize === false || $sourceMtime === false){
			throw new ResourcePackException("Unable to stat source pack: $sourcePath");
		}

		$this->ensureCacheDir();

		$base = basename($sourcePath);
		$cacheZip = Path::join($this->cacheDir, $base);
		$metaPath = $cacheZip . ".meta.json";

		if($this->isCacheValid($metaPath, $cacheZip, $sourceReal, $sourceSize, $sourceMtime)){
			$meta = $this->readMeta($metaPath);
			if(($meta["skipped"] ?? false) === true){
				$this->logger->debug("Resource pack compression cache hit (skipped, original smaller): $base");
				return $sourcePath;
			}
			$this->logger->info("Resource pack compression cache hit: $base");
			return $cacheZip;
		}

		$start = microtime(true);
		$result = $this->compressor->compress($sourcePath, $cacheZip, $this->level);
		$elapsedMs = (int) round((microtime(true) - $start) * 1000);

		$this->writeMeta($metaPath, [
			"source_path" => $sourceReal,
			"source_mtime" => $sourceMtime,
			"source_size" => $sourceSize,
			"level" => $this->level,
			"skipped" => $result->skipped,
			"compressed_size" => $result->compressedBytes,
		]);

		if($result->skipped){
			if(is_file($cacheZip)){
				@unlink($cacheZip);
			}
			$this->logger->info("Resource pack compression skipped (no gain): $base ({$this->formatBytes($result->originalBytes)}, {$elapsedMs}ms)");
			return $sourcePath;
		}

		$ratio = $result->originalBytes > 0
			? (int) round(100.0 * (1.0 - ($result->compressedBytes / $result->originalBytes)))
			: 0;
		$this->logger->info(
			"Compressed resource pack $base: " .
			$this->formatBytes($result->originalBytes) . " -> " . $this->formatBytes($result->compressedBytes) .
			" (-{$ratio}%, {$elapsedMs}ms)"
		);

		return $result->destPath;
	}

	/**
	 * Builds (or reuses) a zip for a folder resource pack and returns a path suitable for ZippedResourcePack.
	 * Requires manifest.json in the folder root. Does not mutate the source directory.
	 *
	 * @throws ResourcePackException
	 */
	public function resolveDirectory(string $sourceDir) : string{
		if(!is_dir($sourceDir)){
			throw new ResourcePackException("Source pack directory not found: $sourceDir");
		}
		$manifest = Path::join($sourceDir, "manifest.json");
		if(!is_file($manifest)){
			throw new ResourcePackException("Folder resource pack missing manifest.json: $sourceDir");
		}

		$sourceReal = realpath($sourceDir) ?: $sourceDir;
		$stamp = $this->directoryStamp($sourceReal);
		$this->ensureCacheDir();

		$base = basename($sourceReal);
		$cacheZip = Path::join($this->cacheDir, $base . ".folder.zip");
		$metaPath = $cacheZip . ".meta.json";

		if($this->isDirectoryCacheValid($metaPath, $cacheZip, $sourceReal, $stamp)){
			$this->logger->info("Resource pack folder cache hit: $base");
			return $cacheZip;
		}

		$start = microtime(true);
		$tempPath = tempnam(sys_get_temp_dir(), "rpdir_");
		if($tempPath === false){
			throw new ResourcePackException("Failed to allocate temp file for folder pack zip");
		}
		$tempZip = $tempPath . ".zip";
		@unlink($tempPath);
		if(is_file($tempZip)){
			@unlink($tempZip);
		}

		try{
			$this->zipDirectory($sourceReal, $tempZip);
			$compressedBytes = filesize($tempZip);
			if($compressedBytes === false || $compressedBytes <= 0){
				throw new ResourcePackException("Failed to build folder pack zip for $base");
			}
			if(is_file($cacheZip) && !@unlink($cacheZip)){
				throw new ResourcePackException("Failed to replace folder pack cache: $cacheZip");
			}
			if(!@rename($tempZip, $cacheZip)){
				if(!@copy($tempZip, $cacheZip)){
					throw new ResourcePackException("Failed to move folder pack zip to $cacheZip");
				}
				@unlink($tempZip);
			}
		}catch(ResourcePackException $e){
			@unlink($tempZip);
			throw $e;
		}catch(\Throwable $e){
			@unlink($tempZip);
			throw new ResourcePackException("Failed to zip folder pack $base: " . $e->getMessage(), 0, $e);
		}

		$this->writeMeta($metaPath, [
			"source_path" => $sourceReal,
			"source_kind" => "directory",
			"source_stamp" => $stamp,
			"level" => $this->level,
			"skipped" => false,
			"compressed_size" => $compressedBytes,
		]);

		$elapsedMs = (int) round((microtime(true) - $start) * 1000);
		$this->logger->info(
			"Built folder resource pack $base: " . $this->formatBytes($compressedBytes) . " ({$elapsedMs}ms)"
		);

		return $cacheZip;
	}

	/**
	 * @return array{files: int, bytes: int, mtime: int}
	 */
	private function directoryStamp(string $sourceReal) : array{
		$files = 0;
		$bytes = 0;
		$mtime = 0;
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($sourceReal, RecursiveDirectoryIterator::SKIP_DOTS)
		);
		/** @var SplFileInfo $file */
		foreach($iterator as $file){
			if(!$file->isFile()){
				continue;
			}
			$files++;
			$bytes += (int) $file->getSize();
			$mtime = max($mtime, (int) $file->getMTime());
		}
		return ["files" => $files, "bytes" => $bytes, "mtime" => $mtime];
	}

	/**
	 * @param array{files: int, bytes: int, mtime: int} $stamp
	 */
	private function isDirectoryCacheValid(string $metaPath, string $cacheZip, string $sourceReal, array $stamp) : bool{
		if(!is_file($metaPath) || !is_file($cacheZip)){
			return false;
		}
		try{
			$meta = $this->readMeta($metaPath);
		}catch(\JsonException|\RuntimeException){
			return false;
		}
		if(($meta["source_kind"] ?? null) !== "directory"){
			return false;
		}
		if(($meta["source_path"] ?? null) !== $sourceReal){
			return false;
		}
		if((int) ($meta["level"] ?? -1) !== $this->level){
			return false;
		}
		$cached = $meta["source_stamp"] ?? null;
		if(!is_array($cached)){
			return false;
		}
		if((int) ($cached["files"] ?? -1) !== $stamp["files"]
			|| (int) ($cached["bytes"] ?? -1) !== $stamp["bytes"]
			|| (int) ($cached["mtime"] ?? -1) !== $stamp["mtime"]){
			return false;
		}
		return filesize($cacheZip) === (int) ($meta["compressed_size"] ?? -1);
	}

	private function zipDirectory(string $sourceReal, string $destZip) : void{
		$zip = new \ZipArchive();
		$createResult = $zip->open($destZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
		if($createResult !== true){
			throw new ResourcePackException("Encountered ZipArchive error code $createResult while creating $destZip");
		}

		$prefixLen = strlen($sourceReal) + 1;
		try{
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($sourceReal, RecursiveDirectoryIterator::SKIP_DOTS)
			);
			/** @var SplFileInfo $file */
			foreach($iterator as $file){
				$full = $file->getPathname();
				$relative = str_replace("\\", "/", substr($full, $prefixLen));
				if($relative === "" || str_starts_with($relative, ".compressed/")){
					continue;
				}
				if($file->isDir()){
					$zip->addEmptyDir(rtrim($relative, "/"));
					continue;
				}
				if(!$file->isFile()){
					continue;
				}
				if(!$zip->addFile($full, $relative)){
					throw new ResourcePackException("Failed to add file to folder pack zip: $relative");
				}
				$method = $file->getSize() > 0 ? \ZipArchive::CM_DEFLATE : \ZipArchive::CM_STORE;
				if(!$zip->setCompressionName($relative, $method, $this->level)){
					$zip->setCompressionName($relative, $method);
				}
			}
		}finally{
			$zip->close();
		}

		if(!is_file($destZip)){
			throw new ResourcePackException("Folder pack zip was not created");
		}
	}

	private function ensureCacheDir() : void{
		if(is_dir($this->cacheDir)){
			return;
		}
		if(!@mkdir($this->cacheDir, 0777, true) && !is_dir($this->cacheDir)){
			throw new ResourcePackException("Failed to create compression cache directory: {$this->cacheDir}");
		}
	}

	/**
	 * @param array<string, mixed> $meta
	 */
	private function isCacheValid(string $metaPath, string $cacheZip, string $sourceReal, int $sourceSize, int $sourceMtime) : bool{
		if(!is_file($metaPath)){
			return false;
		}
		try{
			$meta = $this->readMeta($metaPath);
		}catch(\JsonException|\RuntimeException){
			return false;
		}

		if(($meta["source_path"] ?? null) !== $sourceReal){
			return false;
		}
		if((int) ($meta["source_size"] ?? -1) !== $sourceSize){
			return false;
		}
		if((int) ($meta["source_mtime"] ?? -1) !== $sourceMtime){
			return false;
		}
		if((int) ($meta["level"] ?? -1) !== $this->level){
			return false;
		}

		$skipped = ($meta["skipped"] ?? false) === true;
		if($skipped){
			return true;
		}

		return is_file($cacheZip) && filesize($cacheZip) === (int) ($meta["compressed_size"] ?? -1);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function readMeta(string $metaPath) : array{
		$raw = file_get_contents($metaPath);
		if($raw === false){
			throw new \RuntimeException("Cannot read $metaPath");
		}
		$data = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
		if(!is_array($data)){
			throw new \RuntimeException("Invalid meta JSON in $metaPath");
		}
		/** @var array<string, mixed> $data */
		return $data;
	}

	/**
	 * @param array<string, mixed> $meta
	 */
	private function writeMeta(string $metaPath, array $meta) : void{
		$json = json_encode($meta, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
		if(file_put_contents($metaPath, $json . "\n") === false){
			throw new ResourcePackException("Failed to write compression meta: $metaPath");
		}
	}

	private function formatBytes(int $bytes) : string{
		$units = ["B", "KB", "MB", "GB"];
		$n = (float) $bytes;
		$i = 0;
		while($n >= 1024.0 && $i < count($units) - 1){
			$n /= 1024.0;
			$i++;
		}
		return ($i === 0 ? (string) $bytes : sprintf("%.1f", $n)) . $units[$i];
	}
}
