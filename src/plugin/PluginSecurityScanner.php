<?php

declare(strict_types=1);

namespace pocketmine\plugin;

use Phar;
use RecursiveIteratorIterator;
use ZipArchive;
use function array_map;
use function explode;
use function file_get_contents;
use function function_exists;
use function in_array;
use function ini_get;
use function is_array;
use function is_dir;
use function is_file;
use function preg_match;
use function scandir;
use function str_contains;
use function str_ends_with;
use function strtolower;
use function token_get_all;
use function trim;
use const T_COMMENT;
use const T_CONSTANT_ENCAPSED_STRING;
use const T_DOC_COMMENT;
use const T_ENCAPSED_AND_WHITESPACE;
use const T_INLINE_HTML;

/**
 * Best-effort static scan for dangerous PHP calls in plugin sources.
 * Not a sandbox — use php.ini disable_functions for real hardening.
 * See resources/php.ini.recommended.
 */
final class PluginSecurityScanner{

	/**
	 * Always-flagged sinks (assert omitted — too common / often compile-time).
	 * Shell backticks omitted: false positives on markdown/SQL `` `ident` `` in comments.
	 */
	private const PATTERN = '/\b(eval|shell_exec|passthru|proc_open|pcntl_exec|system|exec|popen|create_function)\s*\(|preg_replace\s*\(\s*[\'\"][^\'\"]*\/[^\'\"\n]*e[^\'\"\n]*[\'\"]/i';

	/** Extra sinks only when security-scan-mode=fail. */
	private const FAIL_EXTRA_PATTERN = '/\bassert\s*\(/i';

	/** Dangerous functions checked at boot for NOTICE (runtime availability). */
	public const DANGEROUS_RUNTIME_FUNCTIONS = [
		"eval", // language construct — checked separately
		"exec",
		"shell_exec",
		"passthru",
		"proc_open",
		"popen",
		"pcntl_exec",
		"system",
		"create_function",
	];

	private function __construct(){}

	/**
	 * @return list<string> human-readable findings (empty if clean)
	 */
	public static function scanPath(string $path, bool $strict = false) : array{
		$findings = [];
		if(is_file($path)){
			$lower = strtolower($path);
			if(str_ends_with($lower, ".phar")){
				self::scanPhar($path, $findings, $strict);
				return $findings;
			}
			if(str_ends_with($lower, ".zip")){
				self::scanZip($path, $findings, $strict);
				return $findings;
			}
			if(str_ends_with($path, ".php")){
				self::scanFile($path, $findings, $strict);
			}
			return $findings;
		}
		if(!is_dir($path)){
			return $findings;
		}
		self::scanDir($path, $findings, $strict);
		return $findings;
	}

	/**
	 * @return list<string> names of dangerous functions still callable
	 */
	public static function listEnabledDangerousFunctions() : array{
		$enabled = [];
		foreach(self::DANGEROUS_RUNTIME_FUNCTIONS as $fn){
			if($fn === "eval"){
				// eval is a language construct; treat as available unless disabled via disable_functions listing.
				$disabled = array_map("strtolower", array_map("trim", explode(",", (string) ini_get("disable_functions"))));
				if(!in_array("eval", $disabled, true)){
					$enabled[] = "eval";
				}
				continue;
			}
			if(function_exists($fn)){
				$enabled[] = $fn;
			}
		}
		return $enabled;
	}

	/**
	 * Ops hints beyond disable_functions (not a process sandbox — PHP cannot isolate plugins).
	 *
	 * @return list<string>
	 */
	public static function listMissingHardeningHints() : array{
		$hints = [];
		$ob = (string) ini_get("open_basedir");
		if($ob === ""){
			$hints[] = "open_basedir unset (see resources/php.ini.recommended)";
		}
		return $hints;
	}

	/**
	 * @param list<string> $findings
	 */
	private static function scanDir(string $dir, array &$findings, bool $strict) : void{
		foreach(scandir($dir) ?: [] as $entry){
			if($entry === "." || $entry === ".."){
				continue;
			}
			$full = $dir . "/" . $entry;
			if(is_dir($full)){
				if(
					$entry === "vendor" ||
					$entry === ".git" ||
					$entry === "tests" ||
					$entry === "test" ||
					$entry === "phpunit"
				){
					continue;
				}
				self::scanDir($full, $findings, $strict);
			}elseif(is_file($full) && str_ends_with($entry, ".php")){
				self::scanFile($full, $findings, $strict);
			}
		}
	}

	/**
	 * @param list<string> $findings
	 */
	private static function scanPhar(string $path, array &$findings, bool $strict) : void{
		try{
			$phar = new Phar($path);
		}catch(\Throwable){
			// Unreadable / corrupt archive — skip (load will fail separately).
			return;
		}
		try{
			$iterator = new RecursiveIteratorIterator($phar);
			foreach($iterator as $file){
				$name = $file->getFilename();
				if(!str_ends_with(strtolower($name), ".php")){
					continue;
				}
				$inner = $file->getPathname();
				if(str_contains($inner, "/tests/") || str_contains($inner, "\\tests\\")){
					continue;
				}
				self::scanFileContents($inner, @file_get_contents($inner) ?: "", $findings, $strict);
			}
		}catch(\Throwable){
			// best-effort
		}
	}

	/**
	 * @param list<string> $findings
	 */
	private static function scanZip(string $path, array &$findings, bool $strict) : void{
		$zip = new ZipArchive();
		if($zip->open($path) !== true){
			return;
		}
		try{
			for($i = 0; $i < $zip->numFiles; $i++){
				$stat = $zip->statIndex($i);
				if($stat === false){
					continue;
				}
				$name = $stat["name"];
				if(!str_ends_with(strtolower($name), ".php")){
					continue;
				}
				if(str_contains($name, "/tests/") || str_contains($name, "\\tests\\") || str_contains($name, "/vendor/")){
					continue;
				}
				$contents = $zip->getFromIndex($i);
				if($contents === false || $contents === ""){
					continue;
				}
				self::scanFileContents($path . "#" . $name, $contents, $findings, $strict);
			}
		}finally{
			$zip->close();
		}
	}

	/**
	 * @param list<string> $findings
	 */
	private static function scanFile(string $file, array &$findings, bool $strict) : void{
		// Extra guard for nested test paths (e.g. src/.../tests/...)
		if(str_contains($file, "/tests/") || str_contains($file, "\\tests\\")){
			return;
		}
		$contents = @file_get_contents($file);
		if($contents === false || $contents === ""){
			return;
		}
		self::scanFileContents($file, $contents, $findings, $strict);
	}

	/**
	 * @param list<string> $findings
	 */
	private static function scanFileContents(string $label, string $contents, array &$findings, bool $strict) : void{
		// Prefer token scan when available (fewer comment/string false positives than raw regex alone).
		if(function_exists("token_get_all")){
			try{
				$tokens = token_get_all($contents);
				$code = "";
				foreach($tokens as $token){
					if(is_array($token)){
						if(in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_INLINE_HTML], true)){
							continue;
						}
						if($token[0] === T_CONSTANT_ENCAPSED_STRING || $token[0] === T_ENCAPSED_AND_WHITESPACE){
							continue;
						}
						$code .= $token[1];
					}else{
						$code .= $token;
					}
				}
				$contents = $code !== "" ? $code : $contents;
			}catch(\Throwable){
				// fall through to raw scan
			}
		}
		if(preg_match(self::PATTERN, $contents) === 1){
			$findings[] = $label;
			return;
		}
		if($strict && preg_match(self::FAIL_EXTRA_PATTERN, $contents) === 1){
			$findings[] = $label;
		}
	}
}
