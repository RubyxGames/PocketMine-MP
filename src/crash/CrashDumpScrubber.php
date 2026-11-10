<?php

declare(strict_types=1);

namespace pocketmine\crash;

use function is_string;
use function preg_replace;
use function str_contains;
use function strtolower;

/**
 * Redacts secrets from crash dump extras (yml, phpinfo, argv).
 */
final class CrashDumpScrubber{

	private function __construct(){}

	public static function scrubServerProperties(string $contents) : string{
		$contents = preg_replace("#^rcon\\.password=(.*)$#m", "rcon.password=******", $contents) ?? $contents;
		return self::scrubKeyValueLines($contents);
	}

	public static function scrubYaml(string $contents) : string{
		return self::scrubKeyValueLines($contents);
	}

	public static function scrubPhpInfo(string $contents) : string{
		// phpinfo lines like "PASSWORD => secret" or env rows
		$scrubbed = preg_replace(
			'/^([^\r\n]*?(?:PASSWORD|SECRET|TOKEN|API[_-]?KEY|MYSQL|DATABASE|AUTH|PRIVATE[_-]?KEY)[^\r\n]*?=>\s*).+$/mi',
			'$1******',
			$contents
		);
		return $scrubbed ?? $contents;
	}

	/**
	 * @param mixed[] $argv
	 * @return mixed[]
	 */
	public static function scrubArgv(array $argv) : array{
		$out = [];
		foreach($argv as $k => $v){
			if(!is_string($v)){
				$out[$k] = $v;
				continue;
			}
			$lower = strtolower($v);
			if(str_contains($lower, "password") || str_contains($lower, "secret") || str_contains($lower, "token=")){
				$out[$k] = "******";
			}else{
				$out[$k] = $v;
			}
		}
		return $out;
	}

	private static function scrubKeyValueLines(string $contents) : string{
		// YAML/properties style: key: value or key=value (keep indentation)
		$scrubbed = preg_replace(
			'/^([ \t]*[^#\r\n]*?(?:password|secret|token|api[_-]?key|auth(?:entication)?|mysql(?:[_-]?(?:password|user|host))?|database(?:[_-]?password)?|cdn(?:[_-]?url)?|private[_-]?key|encryption[_-]?key|key_file)[^:\r\n]*?\s*[:=]\s*).+$/mi',
			'$1******',
			$contents
		);
		return $scrubbed ?? $contents;
	}
}
