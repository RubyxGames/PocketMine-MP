<?php

namespace pocketmine\world\worldedit\thread;

use pocketmine\world\worldedit\handler\EditHandler;
use pocketmine\world\worldedit\result\TaskResult;
use pocketmine\world\worldedit\task\ExecutableTask;
use pocketmine\world\worldedit\thread\input\TaskInputData;
use pocketmine\Server;
use pocketmine\utils\SingletonTrait;
use SplQueue;

class MainThreadTaskScheduler
{
	use SingletonTrait;

	/**
	 * @var SplQueue<ExecutableTask<TaskResult>>
	 */
	private SplQueue $queue;
	private int $lastTick = 0;

	public function __construct()
	{
		$this->queue = new SplQueue();
	}

	public function tick(): void
	{
		if (!$this->queue->isEmpty()) {
			$this->executeTask($this->queue->dequeue());
		}
	}

	/**
	 * @param ExecutableTask<TaskResult> $task
	 */
	public function enqueueTask(ExecutableTask $task): void
	{
		if ($this->queue->isEmpty() && Server::getInstance()->getTick() !== $this->lastTick) {
			$this->executeTask($task);
		} else {
			$this->queue->enqueue($task);
		}
	}

	/**
	 * @param ExecutableTask<TaskResult> $task
	 */
	private function executeTask(ExecutableTask $task): void
	{
		if (!$task->canExecuteOnMainThread()) {
			EditThread::getInstance()->debug("Task " . $task->getTaskId() . " has unloaded chunks, moving to edit thread");
			TaskInputData::fromTask($task);
			return;
		}
		EditHandler::callback($task->getTaskId(), $task->runInternal()->getRawPayload());
		$this->lastTick = Server::getInstance()->getTick();
	}
}