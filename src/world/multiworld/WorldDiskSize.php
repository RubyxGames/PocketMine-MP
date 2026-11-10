<?php

declare(strict_types=1);

namespace pocketmine\world\multiworld;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use UnexpectedValueException;
use function is_dir;
use function sprintf;

/**
 * Best-effort world folder size for MultiWorld /mw list (sync, ops-facing).
 */
final class WorldDiskSize{

	private function __construct(){}

	public static function directoryBytes(string $path) : int{
		if(!is_dir($path)){
			return 0;
		}
		$total = 0;
		try{
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS),
				RecursiveIteratorIterator::LEAVES_ONLY,
				RecursiveIteratorIterator::CATCH_GET_CHILD
			);
			foreach($iterator as $file){
				try{
					if($file->isFile()){
						$total += (int) $file->getSize();
					}
				}catch(UnexpectedValueException){
					// skip unreadable entries
				}
			}
		}catch(UnexpectedValueException){
			return $total;
		}
		return $total;
	}

	public static function formatBytes(int $bytes) : string{
		if($bytes < 1024){
			return $bytes . " B";
		}
		if($bytes < 1024 * 1024){
			return sprintf("%.1f KB", $bytes / 1024);
		}
		if($bytes < 1024 * 1024 * 1024){
			return sprintf("%.1f MB", $bytes / (1024 * 1024));
		}
		return sprintf("%.1f GB", $bytes / (1024 * 1024 * 1024));
	}

	public static function formatDirectory(string $path) : string{
		return self::formatBytes(self::directoryBytes($path));
	}
}
