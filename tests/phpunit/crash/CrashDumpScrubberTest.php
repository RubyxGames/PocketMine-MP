<?php

declare(strict_types=1);

namespace pocketmine\crash;

use PHPUnit\Framework\TestCase;

final class CrashDumpScrubberTest extends TestCase{

	public function testScrubServerPropertiesRconPassword() : void{
		$input = "rcon.password=hunter2\nmotd=Hello\n";
		$out = CrashDumpScrubber::scrubServerProperties($input);
		self::assertStringContainsString("rcon.password=******", $out);
		self::assertStringNotContainsString("hunter2", $out);
		self::assertStringContainsString("motd=Hello", $out);
	}

	public function testScrubYamlSecrets() : void{
		$input = "password: super-secret\nhost: localhost\ntoken: abc123\n";
		$out = CrashDumpScrubber::scrubYaml($input);
		self::assertStringContainsString("password: ******", $out);
		self::assertStringContainsString("token: ******", $out);
		self::assertStringNotContainsString("super-secret", $out);
		self::assertStringNotContainsString("abc123", $out);
		self::assertStringContainsString("host: localhost", $out);
	}

	public function testScrubPhpInfo() : void{
		$input = "MYSQL_PASSWORD => hunter2\nPATH => /usr/bin\n";
		$out = CrashDumpScrubber::scrubPhpInfo($input);
		self::assertStringContainsString("MYSQL_PASSWORD => ******", $out);
		self::assertStringNotContainsString("hunter2", $out);
		self::assertStringContainsString("PATH => /usr/bin", $out);
	}

	public function testScrubArgv() : void{
		$out = CrashDumpScrubber::scrubArgv([
			"pocketmine",
			"--password=abc",
			"--port=19132",
			42,
		]);
		self::assertSame("******", $out[1]);
		self::assertSame("--port=19132", $out[2]);
		self::assertSame(42, $out[3]);
	}
}
