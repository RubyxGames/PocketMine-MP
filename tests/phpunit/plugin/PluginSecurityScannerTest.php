<?php

declare(strict_types=1);

namespace pocketmine\plugin;

use Phar;
use PHPUnit\Framework\TestCase;
use ZipArchive;
use function bin2hex;
use function file_put_contents;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;

final class PluginSecurityScannerTest extends TestCase{

	private string $dir;

	protected function setUp() : void{
		$this->dir = sys_get_temp_dir() . "/pm-secscan-" . bin2hex(random_bytes(4));
		mkdir($this->dir);
	}

	protected function tearDown() : void{
		@unlink($this->dir . "/bad.php");
		@unlink($this->dir . "/ok.php");
		@unlink($this->dir . "/tick.php");
		@unlink($this->dir . "/assert_only.php");
		@unlink($this->dir . "/bad.phar");
		@unlink($this->dir . "/bad.zip");
		@rmdir($this->dir);
	}

	public function testDetectsDangerousCalls() : void{
		file_put_contents($this->dir . "/ok.php", "<?php echo 1;\n");
		file_put_contents($this->dir . "/bad.php", "<?php shell_exec('id');\n");
		$findings = PluginSecurityScanner::scanPath($this->dir);
		self::assertCount(1, $findings);
		self::assertStringContainsString("bad.php", $findings[0]);
	}

	public function testDetectsCreateFunction() : void{
		file_put_contents($this->dir . "/tick.php", "<?php create_function('','');\n");
		$findings = PluginSecurityScanner::scanPath($this->dir . "/tick.php");
		self::assertCount(1, $findings);
	}

	public function testAssertOnlyInStrictMode() : void{
		file_put_contents($this->dir . "/assert_only.php", "<?php assert(true);\n");
		self::assertSame([], PluginSecurityScanner::scanPath($this->dir . "/assert_only.php", false));
		$strict = PluginSecurityScanner::scanPath($this->dir . "/assert_only.php", true);
		self::assertCount(1, $strict);
	}

	public function testScansPharInterior() : void{
		if(!class_exists(Phar::class) || !Phar::canWrite()){
			self::markTestSkipped("phar write unavailable");
		}
		$pharPath = $this->dir . "/bad.phar";
		$phar = new Phar($pharPath);
		$phar->addFromString("src/Evil.php", "<?php shell_exec('id');\n");
		unset($phar);
		$findings = PluginSecurityScanner::scanPath($pharPath);
		self::assertNotSame([], $findings);
		self::assertStringContainsString("Evil.php", $findings[0]);
	}

	public function testScansZipInterior() : void{
		$zipPath = $this->dir . "/bad.zip";
		$zip = new ZipArchive();
		self::assertTrue($zip->open($zipPath, ZipArchive::CREATE));
		$zip->addFromString("src/Evil.php", "<?php eval('1');\n");
		$zip->close();
		$findings = PluginSecurityScanner::scanPath($zipPath);
		self::assertCount(1, $findings);
		self::assertStringContainsString("Evil.php", $findings[0]);
	}
}
