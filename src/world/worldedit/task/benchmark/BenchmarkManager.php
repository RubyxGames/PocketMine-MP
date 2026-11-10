<?php

namespace pocketmine\world\worldedit\task\benchmark;

use Closure;
use pocketmine\world\worldedit\WorldEdit;
use pocketmine\world\worldedit\result\BenchmarkTaskResult;
use pocketmine\world\worldedit\session\Session;
use pocketmine\world\worldedit\utils\MixedUtils;
use pocketmine\scheduler\TaskHandler;
use pocketmine\Server;
use pocketmine\utils\Utils;
use pocketmine\world\WorldCreationOptions;
use UnexpectedValueException;

class BenchmarkManager
{
	private static bool $running = false;

	/**
	 * @param Session $session
	 * @param Closure $closure
	 * @param bool    $deleteWorldAfter Useful when testing core functions
	 */
	public static function start(Session $session, Closure $closure, bool $deleteWorldAfter = true): void
	{
		if (self::$running) {
			throw new UnexpectedValueException("Benchmark is already running");
		}
		Utils::validateCallableSignature(static function (float $tpsAvg, float $tpsMin, float $loadAvg, float $loadMax, int $tasks, float $time, array $results): void { }, $closure);

		self::$running = true;

		$autoSave = MixedUtils::setAutoSave(PHP_INT_MAX);
		$benchmark = new BenchmarkTask();
		$task = WorldEdit::getInstance()->getScheduler()->scheduleRepeatingTask($benchmark, 1);

		$name = "WorldEdit-Benchmark-" . time();
		Server::getInstance()->getWorldManager()->generateWorld($name, WorldCreationOptions::create(), false);
		$session->runTask(new BenchmarkExecutor($name))->then(static function (BenchmarkTaskResult $results) use ($benchmark, $task, $closure, $autoSave, $deleteWorldAfter, $name): void {
			$results = $results->getResults();
			$task->cancel();
			$time = array_sum(array_map(static function (array $dat): float {
				return $dat[2];
			}, $results));
			$closure($benchmark->getTpsTotal(), $benchmark->getTpsMin(), $benchmark->getLoadTotal(), $benchmark->getLoadMax(), count($results), $time, $results);

			if ($deleteWorldAfter) {
				$world = Server::getInstance()->getWorldManager()->getWorldByName($name);
				if ($world === null) {
					WorldEdit::getInstance()->getLogger()->critical("Couldn't clean after benchmark, world " . $name . " doesn't exist");
				} else {
					$path = $world->getProvider()->getPath();
					Server::getInstance()->getWorldManager()->unloadWorld($world);
					MixedUtils::deleteDir($path);
				}
			}

			$validate = MixedUtils::setAutoSave($autoSave);
			if ($validate !== PHP_INT_MAX) {
				WorldEdit::getInstance()->getLogger()->warning("World auto save interval was changed during benchmark, results may be inaccurate");
			}
			self::$running = false;
		})->update(static function (int $progress) use ($session): void {
			$session->sendMessage("benchmark-progress", ["{done}" => (string) $progress, "{total}" => "4"]);
		});
	}

	/**
	 * @return bool
	 */
	public static function isRunning(): bool
	{
		return self::$running;
	}
}