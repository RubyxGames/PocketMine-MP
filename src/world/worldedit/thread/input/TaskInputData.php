<?php

namespace pocketmine\world\worldedit\thread\input;

use pocketmine\world\worldedit\result\TaskResult;
use pocketmine\world\worldedit\task\ExecutableTask;
use pocketmine\world\worldedit\thread\ThreadData;
use pocketmine\world\worldedit\utils\ExtendedBinaryStream;

class TaskInputData extends InputData
{
	/**
	 * @var ExecutableTask<TaskResult>
	 */
	private ExecutableTask $task;

	/**
	 * @param ExecutableTask<TaskResult> $task
	 */
	public static function fromTask(ExecutableTask $task): void
	{
		$data = new self();
		$data->task = $task;
		$data->send();
	}

	public function handle(): void
	{
		ThreadData::addTask($this->task);
	}

	public function putData(ExtendedBinaryStream $stream): void
	{
		$stream->putString($this->task->fastSerialize());
	}

	public function parseData(ExtendedBinaryStream $stream): void
	{
		$this->task = ExecutableTask::fastDeserialize($stream->getString());
	}
}