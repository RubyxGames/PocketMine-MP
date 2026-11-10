<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\utils;

use pocketmine\thread\Thread;
use function count;
use function disk_free_space;
use function disk_total_space;
use function exec;
use function fclose;
use function file;
use function file_get_contents;
use function function_exists;
use function getmypid;
use function getmyuid;
use function hexdec;
use function is_dir;
use function is_file;
use function max;
use function memory_get_usage;
use function microtime;
use function posix_kill;
use function preg_match;
use function preg_split;
use function proc_close;
use function proc_open;
use function str_starts_with;
use function stream_get_contents;
use function strtok;
use function strrpos;
use function substr;
use function sys_getloadavg;
use function trim;

final class Process{

	private function __construct(){
		//NOOP
	}

	/**
	 * @return int[]
	 * @phpstan-return array{int,int,int}
	 */
	public static function getAdvancedMemoryUsage(){
		$reserved = memory_get_usage();
		$VmSize = null;
		$VmRSS = null;
		if(Utils::getOS() === Utils::OS_LINUX || Utils::getOS() === Utils::OS_ANDROID){
			$status = @file_get_contents("/proc/self/status");
			if($status === false) throw new AssumptionFailedError("/proc/self/status should always be accessible");

			// the numbers found here should never be bigger than PHP_INT_MAX, so we expect them to always be castable to int
			if(preg_match("/VmRSS:[ \t]+([0-9]+) kB/", $status, $matches) > 0){
				$VmRSS = ((int) $matches[1]) * 1024;
			}

			if(preg_match("/VmSize:[ \t]+([0-9]+) kB/", $status, $matches) > 0){
				$VmSize = ((int) $matches[1]) * 1024;
			}
		}

		//TODO: more OS

		if($VmRSS === null){
			$VmRSS = memory_get_usage();
		}

		if($VmSize === null){
			$VmSize = memory_get_usage(true);
		}

		return [$reserved, $VmRSS, $VmSize];
	}

	public static function getMemoryUsage() : int{
		return self::getAdvancedMemoryUsage()[1];
	}

	/**
	 * Disk usage for the filesystem containing $path.
	 *
	 * @return array{used: int, total: int, free: int}|null bytes; null if unavailable
	 */
	public static function getDiskUsage(string $path = ".") : ?array{
		if($path === "" || (!is_dir($path) && !is_file($path))){
			$path = ".";
		}
		$total = @disk_total_space($path);
		$free = @disk_free_space($path);
		if($total === false || $free === false || $total <= 0.0){
			return null;
		}
		$totalBytes = (int) $total;
		$freeBytes = (int) $free;
		return [
			"used" => max(0, $totalBytes - $freeBytes),
			"total" => $totalBytes,
			"free" => max(0, $freeBytes)
		];
	}

	/**
	 * System RAM from /proc/meminfo (Linux).
	 *
	 * @return array{used: int, total: int, available: int}|null bytes; null if unavailable
	 */
	public static function getSystemMemoryUsage() : ?array{
		if(Utils::getOS() !== Utils::OS_LINUX && Utils::getOS() !== Utils::OS_ANDROID){
			return null;
		}
		$meminfo = @file_get_contents("/proc/meminfo");
		if($meminfo === false){
			return null;
		}
		$total = null;
		$available = null;
		if(preg_match("/^MemTotal:[ \t]+([0-9]+) kB/m", $meminfo, $m) > 0){
			$total = ((int) $m[1]) * 1024;
		}
		if(preg_match("/^MemAvailable:[ \t]+([0-9]+) kB/m", $meminfo, $m) > 0){
			$available = ((int) $m[1]) * 1024;
		}
		if($total === null || $available === null || $total <= 0){
			return null;
		}
		return [
			"used" => max(0, $total - $available),
			"total" => $total,
			"available" => $available
		];
	}

	/**
	 * Approximate process CPU usage since the previous call (0–100+; can exceed 100 on multi-core).
	 * First call returns 0.0 and starts sampling.
	 */
	public static function getProcessCpuUsagePercent() : float{
		static $lastWall = 0.0;
		static $lastCpu = 0.0;

		$cpuSeconds = self::readProcessCpuSeconds();
		$wall = microtime(true);
		if($cpuSeconds === null){
			return 0.0;
		}
		if($lastWall <= 0.0){
			$lastWall = $wall;
			$lastCpu = $cpuSeconds;
			return 0.0;
		}
		$wallDelta = $wall - $lastWall;
		$cpuDelta = $cpuSeconds - $lastCpu;
		$lastWall = $wall;
		$lastCpu = $cpuSeconds;
		if($wallDelta <= 0.0){
			return 0.0;
		}
		return max(0.0, ($cpuDelta / $wallDelta) * 100.0);
	}

	/**
	 * 1-minute load average as a rough system busyness indicator (cores-normalized percent).
	 */
	public static function getSystemLoadPercent() : ?float{
		$load = @sys_getloadavg();
		if($load === false || !isset($load[0])){
			return null;
		}
		$cores = max(1, Utils::getCoreCount(true));
		return max(0.0, ($load[0] / $cores) * 100.0);
	}

	/**
	 * Host/VPS CPU busy percent (0–100) from /proc/stat since the previous call.
	 * First call returns 0.0 and starts sampling.
	 */
	public static function getSystemCpuUsagePercent() : ?float{
		static $lastIdle = 0.0;
		static $lastTotal = 0.0;

		$sample = self::readSystemCpuTimes();
		if($sample === null){
			return null;
		}
		[$idle, $total] = $sample;
		if($lastTotal <= 0.0){
			$lastIdle = $idle;
			$lastTotal = $total;
			return 0.0;
		}
		$idleDelta = $idle - $lastIdle;
		$totalDelta = $total - $lastTotal;
		$lastIdle = $idle;
		$lastTotal = $total;
		if($totalDelta <= 0.0){
			return 0.0;
		}
		$busy = 1.0 - ($idleDelta / $totalDelta);
		return max(0.0, min(100.0, $busy * 100.0));
	}

	/**
	 * @return array{0: float, 1: float}|null idle, total jiffies
	 */
	private static function readSystemCpuTimes() : ?array{
		if(Utils::getOS() !== Utils::OS_LINUX && Utils::getOS() !== Utils::OS_ANDROID){
			return null;
		}
		$stat = @file_get_contents("/proc/stat");
		if($stat === false){
			return null;
		}
		$line = strtok($stat, "\n");
		if($line === false || !str_starts_with($line, "cpu ")){
			return null;
		}
		$parts = preg_split("/\s+/", trim($line));
		if($parts === false || count($parts) < 5){
			return null;
		}
		// user nice system idle iowait irq softirq steal ...
		$nums = [];
		for($i = 1; $i < count($parts); $i++){
			$nums[] = (float) $parts[$i];
		}
		$idle = ($nums[3] ?? 0.0) + ($nums[4] ?? 0.0); // idle + iowait
		$total = 0.0;
		foreach($nums as $n){
			$total += $n;
		}
		return [$idle, $total];
	}

	private static function readProcessCpuSeconds() : ?float{
		if(Utils::getOS() !== Utils::OS_LINUX && Utils::getOS() !== Utils::OS_ANDROID){
			return null;
		}
		$stat = @file_get_contents("/proc/self/stat");
		if($stat === false){
			return null;
		}
		// pid (comm) state ... utime stime — fields 14 and 15 (1-based) after comm which may contain spaces
		$closeParen = strrpos($stat, ")");
		if($closeParen === false){
			return null;
		}
		$rest = trim(substr($stat, $closeParen + 1));
		$parts = preg_split("/\s+/", $rest);
		if($parts === false || count($parts) < 13){
			return null;
		}
		$utime = (float) $parts[11];
		$stime = (float) $parts[12];
		// Linux USER_HZ is almost always 100
		return ($utime + $stime) / 100.0;
	}

	/**
	 * @return int[]
	 */
	public static function getRealMemoryUsage() : array{
		$stack = 0;
		$heap = 0;

		if(Utils::getOS() === Utils::OS_LINUX || Utils::getOS() === Utils::OS_ANDROID){
			$mappings = @file("/proc/self/maps");
			if($mappings === false) throw new AssumptionFailedError("/proc/self/maps should always be accessible");
			foreach($mappings as $line){
				if(preg_match("#([a-z0-9]+)\\-([a-z0-9]+) [rwxp\\-]{4} [a-z0-9]+ [^\\[]*\\[([a-zA-z0-9]+)\\]#", trim($line), $matches) > 0){
					if(str_starts_with($matches[3], "heap")){
						$heap += (int) hexdec($matches[2]) - (int) hexdec($matches[1]);
					}elseif(str_starts_with($matches[3], "stack")){
						$stack += (int) hexdec($matches[2]) - (int) hexdec($matches[1]);
					}
				}
			}
		}

		return [$heap, $stack];
	}

	public static function getThreadCount() : int{
		if(Utils::getOS() === Utils::OS_LINUX || Utils::getOS() === Utils::OS_ANDROID){
			$status = @file_get_contents("/proc/self/status");
			if($status === false) throw new AssumptionFailedError("/proc/self/status should always be accessible");
			if(preg_match("/Threads:[ \t]+([0-9]+)/", $status, $matches) > 0){
				return (int) $matches[1];
			}
		}

		//TODO: more OS

		return Thread::getRunningCount() + 1; //pmmpthread doesn't count the main thread
	}

	/**
	 * @param bool $subprocesses @deprecated
	 */
	public static function kill(int $pid, bool $subprocesses = false) : void{
		$logger = \GlobalLogger::get();
		if($logger instanceof MainLogger){
			$logger->syncFlushBuffer();
		}
		switch(Utils::getOS()){
			case Utils::OS_WINDOWS:
				exec("taskkill.exe /F " . ($subprocesses ? "/T " : "") . "/PID $pid > NUL 2> NUL");
				break;
			case Utils::OS_MACOS:
			case Utils::OS_LINUX:
			default:
				if($subprocesses){
					$pid = -$pid;
				}
				if(function_exists("posix_kill")){
					posix_kill($pid, 9); //SIGKILL
				}else{
					exec("kill -9 $pid > /dev/null 2>&1");
				}
		}
	}

	/**
	 * @param string      $command Command to execute
	 * @param string|null $stdout  Reference parameter to write stdout to
	 * @param string|null $stderr  Reference parameter to write stderr to
	 * @phpstan-param-out string $stdout
	 * @phpstan-param-out string $stderr
	 *
	 * @return int process exit code
	 */
	public static function execute(string $command, ?string &$stdout = null, ?string &$stderr = null) : int{
		$process = proc_open($command, [
			["pipe", "r"],
			["pipe", "w"],
			["pipe", "w"]
		], $pipes);

		if($process === false){
			$stderr = "Failed to open process";
			$stdout = "";

			return -1;
		}

		$out = stream_get_contents($pipes[1]);
		if($out === false){
			throw new AssumptionFailedError("Presume this can't happen for proc_open ... ???");
		}
		$stdout = $out;

		$err = stream_get_contents($pipes[2]);
		if($err === false){
			throw new AssumptionFailedError("Presume this can't happen for proc_open ... ???");
		}
		$stderr = $err;

		foreach($pipes as $p){
			fclose($p);
		}

		return proc_close($process);
	}

	public static function pid() : int{
		$result = getmypid();
		if($result === false){
			throw new \LogicException("getmypid() doesn't work on this platform");
		}
		return $result;
	}

	public static function uid() : int{
		$result = getmyuid();
		if($result === false){
			throw new \LogicException("getmyuid() doesn't work on this platform");
		}
		return $result;
	}
}
