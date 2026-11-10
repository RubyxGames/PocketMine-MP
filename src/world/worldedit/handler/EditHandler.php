<?php

namespace pocketmine\world\worldedit\handler;

use pocketmine\world\worldedit\result\TaskResult;
use pocketmine\world\worldedit\result\TaskResultPromise;
use pocketmine\world\worldedit\session\Session;
use pocketmine\world\worldedit\session\SessionIdentifier;
use pocketmine\world\worldedit\task\ExecutableTask;
use pocketmine\world\worldedit\thread\input\TaskInputData;
use pocketmine\world\worldedit\thread\MainThreadTaskScheduler;
use pocketmine\world\worldedit\thread\output\TaskNotifyData;
use UnexpectedValueException;

class EditHandler
{
	/**
	 * @var TaskResultPromise<TaskResult>[]
	 */
	private static array $promises = [];
	/**
	 * @var Session[]
	 */
	private static array $executors = [];

	/**
	 * @param Session $executor
	 * @param int     $task
	 */
	public static function affiliateTask(Session $executor, int $task): void
	{
		self::$executors[$task] = $executor;
	}

	/**
	 * @template T of TaskResult
	 * @param ExecutableTask<T> $task
	 * @return TaskResultPromise<T>
	 */
	public static function runTask(ExecutableTask $task): TaskResultPromise
	{
		/** @phpstan-var TaskResultPromise<T> $promise */
		$promise = new TaskResultPromise();
		self::$promises[$task->getTaskId()] = $promise;
		//TODO: Add a config option for this absolutely arbitrary value
		if ($task->calculateEffectiveComplexity() > 31250 && $task->canExecuteOnMainThread()) { //half this value when pattern complexity is added
			TaskInputData::fromTask($task);
		} else {
			MainThreadTaskScheduler::getInstance()->enqueueTask($task);
		}
		return $promise;
	}

	/**
	 * @param int    $taskId
	 * @param string $data
	 */
	public static function callback(int $taskId, string $data): void
	{
		if (!isset(self::$promises[$taskId])) {
			throw new UnexpectedValueException("Task with id " . $taskId . " not found");
		}
		self::$promises[$taskId]->applyRawPayload($data);
		unset(self::$promises[$taskId], self::$executors[$taskId]);
	}

	/**
	 * @param TaskNotifyData $result
	 */
	public static function notify(TaskNotifyData $result): void
	{
		if (!isset(self::$promises[$result->getTaskId()])) {
			throw new UnexpectedValueException("Task with id " . $result->getTaskId() . " not found");
		}
		self::$promises[$result->getTaskId()]->notify($result->getPayload());
	}

	/**
	 * @param int $task
	 * @return SessionIdentifier
	 */
	public static function getExecutor(int $task): SessionIdentifier
	{
		if (!isset(self::$executors[$task])) {
			return SessionIdentifier::internal("WorldEdit");
		}
		return self::$executors[$task]->getIdentifier();
	}
}