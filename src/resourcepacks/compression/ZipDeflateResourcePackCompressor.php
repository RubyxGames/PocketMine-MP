<?php

declare(strict_types=1);

namespace pocketmine\resourcepacks\compression;

use pocketmine\resourcepacks\ResourcePackException;
use function basename;
use function copy;
use function dirname;
use function filesize;
use function is_dir;
use function is_file;
use function mkdir;
use function rename;
use function rtrim;
use function str_ends_with;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * Re-packs a resource pack zip with maximum DEFLATE compression for faster client downloads.
 * Does not mutate the source archive.
 */
final class ZipDeflateResourcePackCompressor implements ResourcePackCompressor{

	public function compress(string $sourceZip, string $destZip, int $level = 9) : CompressionResult{
		if($level < 0 || $level > 9){
			throw new ResourcePackException("Compression level must be 0..9, got $level");
		}
		if(!is_file($sourceZip)){
			throw new ResourcePackException("Source zip not found: $sourceZip");
		}
		$originalBytes = filesize($sourceZip);
		if($originalBytes === false || $originalBytes <= 0){
			throw new ResourcePackException("Unable to read source zip size: $sourceZip");
		}

		$tempPath = tempnam(sys_get_temp_dir(), "rpzip_");
		if($tempPath === false){
			throw new ResourcePackException("Failed to allocate temp file for compression");
		}
		// ZipArchive needs a .zip-ish name on some platforms; rewrite to .tmp.zip
		$tempZip = $tempPath . ".zip";
		@unlink($tempPath);
		if(is_file($tempZip)){
			@unlink($tempZip);
		}

		try{
			$this->repack($sourceZip, $tempZip, $level);

			$compressedBytes = filesize($tempZip);
			if($compressedBytes === false){
				throw new ResourcePackException("Failed to stat compressed zip");
			}

			if($compressedBytes >= $originalBytes){
				@unlink($tempZip);
				return new CompressionResult($sourceZip, $originalBytes, $originalBytes, true);
			}

			$destDir = dirname($destZip);
			if(!is_dir($destDir) && !@mkdir($destDir, 0777, true) && !is_dir($destDir)){
				throw new ResourcePackException("Failed to create destination directory: $destDir");
			}
			if(is_file($destZip) && !@unlink($destZip)){
				throw new ResourcePackException("Failed to replace existing compressed pack: $destZip");
			}
			if(!@rename($tempZip, $destZip)){
				// cross-device fallback
				if(!@copy($tempZip, $destZip)){
					throw new ResourcePackException("Failed to move compressed pack to $destZip");
				}
				@unlink($tempZip);
			}

			return new CompressionResult($destZip, $originalBytes, $compressedBytes, false);
		}catch(ResourcePackException $e){
			@unlink($tempZip);
			throw $e;
		}catch(\Throwable $e){
			@unlink($tempZip);
			throw new ResourcePackException("Compression failed for " . basename($sourceZip) . ": " . $e->getMessage(), 0, $e);
		}
	}

	private function repack(string $sourceZip, string $destZip, int $level) : void{
		$src = new \ZipArchive();
		$openResult = $src->open($sourceZip);
		if($openResult !== true){
			throw new ResourcePackException("Encountered ZipArchive error code $openResult while opening $sourceZip");
		}

		$dst = new \ZipArchive();
		$createResult = $dst->open($destZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
		if($createResult !== true){
			$src->close();
			throw new ResourcePackException("Encountered ZipArchive error code $createResult while creating $destZip");
		}

		try{
			for($i = 0; $i < $src->numFiles; ++$i){
				$stat = $src->statIndex($i);
				if($stat === false){
					continue;
				}
				$name = $stat["name"];
				// Skip directory entries (trailing slash)
				if(str_ends_with($name, "/")){
					$dst->addEmptyDir(rtrim($name, "/"));
					continue;
				}
				$content = $src->getFromIndex($i);
				if($content === false){
					throw new ResourcePackException("Failed to read zip entry: $name");
				}
				if(!$dst->addFromString($name, $content)){
					throw new ResourcePackException("Failed to write zip entry: $name");
				}
				// Prefer DEFLATE; empty files may stay STORE
				$method = ($content === "") ? \ZipArchive::CM_STORE : \ZipArchive::CM_DEFLATE;
				if(!$dst->setCompressionName($name, $method, $level)){
					// Older libzip: setCompressionName without level
					$dst->setCompressionName($name, $method);
				}
			}
		}finally{
			$dst->close();
			$src->close();
		}

		if(!is_file($destZip)){
			throw new ResourcePackException("Compressed zip was not created");
		}
	}
}
