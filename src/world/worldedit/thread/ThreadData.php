<?php

namespace pocketmine\world\worldedit\thread;

use pocketmine\world\worldedit\result\TaskResult;
use pocketmine\world\worldedit\session\SessionIdentifier;
use pocketmine\world\worldedit\task\ExecutableTask;
use RuntimeException;

/**
 * @internal
 */
class ThreadData
{
	/**
	 * @var ExecutableTask<TaskResult>[]
	 */
	private static array $tasks = [];
	private static bool $stop = false;
	private static ?SessionIdentifier $reason = null;

	/**
	 * @return ExecutableTask<TaskResult>|null
	 */
	public static function getNextTask(): ?ExecutableTask
	{
		return array_shift(self::$tasks);
	}

	/**
	 * @return int
	 */
	public static function getQueueLength(): int
	{
		return count(self::$tasks);
	}

	/**
	 * @param ExecutableTask<TaskResult> $task
	 */
	public static function addTask(ExecutableTask $task): void
	{
		self::$tasks[] = $task;
	}

	public static function requirePause(SessionIdentifier $identifier): void
	{
		self::$stop = true;
		self::$reason = $identifier;
	}

	/**
	 * @return bool
	 */
	public static function requiresCancel(): bool
	{
		return self::$stop;
	}

	public static function clear(): void
	{
		self::$stop = false;
		self::$reason = null;
	}

	/**
	 * @return SessionIdentifier
	 */
	public static function getCancelReason(): SessionIdentifier
	{
		if (self::$reason === null) {
			throw new RuntimeException("No reason for cancel");
		}
		return self::$reason;
	}
}