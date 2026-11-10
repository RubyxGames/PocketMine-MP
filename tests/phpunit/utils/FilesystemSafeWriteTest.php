<?php

declare(strict_types=1);

namespace pocketmine\utils;

use PHPUnit\Framework\TestCase;
use function bin2hex;
use function file_get_contents;
use function is_dir;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;

final class FilesystemSafeWriteTest extends TestCase{

	private string $dir;

	protected function setUp() : void{
		$this->dir = sys_get_temp_dir() . "/pm-safe-write-" . bin2hex(random_bytes(4));
		mkdir($this->dir);
	}

	protected function tearDown() : void{
		if(is_dir($this->dir)){
			foreach(scandir($this->dir) ?: [] as $entry){
				if($entry === "." || $entry === ".."){
					continue;
				}
				@unlink($this->dir . "/" . $entry);
			}
			@rmdir($this->dir);
		}
	}

	public function testSafeFilePutContentsWritesAtomically() : void{
		$path = $this->dir . "/data.txt";
		Filesystem::safeFilePutContents($path, "hello");
		self::assertSame("hello", file_get_contents($path));

		Filesystem::safeFilePutContents($path, "world");
		self::assertSame("world", file_get_contents($path));
	}

	public function testSafeFilePutContentsRejectsMissingDirectory() : void{
		$this->expectException(\RuntimeException::class);
		Filesystem::safeFilePutContents($this->dir . "/missing/file.txt", "x");
	}

	public function testSafeFilePutContentsRejectsDirectoryTarget() : void{
		$subdir = $this->dir . "/subdir";
		mkdir($subdir);
		$this->expectException(\RuntimeException::class);
		Filesystem::safeFilePutContents($subdir, "x");
	}
}
