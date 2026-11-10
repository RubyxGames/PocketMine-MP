<?php

namespace pocketmine\world\worldedit\task\editing;

use Generator;
use pocketmine\world\worldedit\WorldEdit;
use pocketmine\world\worldedit\result\CountingTaskResult;
use pocketmine\world\worldedit\result\TaskResult;
use pocketmine\world\worldedit\selection\constructor\ShapeConstructor;
use pocketmine\world\worldedit\selection\identifier\SelectionIdentifier;
use pocketmine\world\worldedit\selection\identifier\SelectionSerializer;
use pocketmine\world\worldedit\selection\NonSavingBlockListSelection;
use pocketmine\world\worldedit\selection\SelectionContext;
use pocketmine\world\worldedit\task\ExecutableTask;
use pocketmine\world\worldedit\thread\EditThread;
use pocketmine\world\worldedit\utils\ExtendedBinaryStream;

/**
 * @extends ExecutableTask<CountingTaskResult>
 */
class CountTask extends ExecutableTask implements ChunkedTask
{
	/**
	 * @var int[]
	 */
	private array $counted = [];
	private SelectionContext $context;

	/**
	 * @param SelectionIdentifier   $selection
	 * @param SelectionContext|null $context
	 */
	public function __construct(private SelectionIdentifier $selection, ?SelectionContext $context = null)
	{
		$this->context = $context ?? SelectionContext::full();
		parent::__construct();
	}

	/**
	 * @return CountingTaskResult
	 */
	protected function executeInternal(): CountingTaskResult
	{
		$chunkHandler = new SingleChunkHandler($this->selection->asSelection()->getWorldName());
		WorldEdit::getEnv()->initChunkHandler($chunkHandler);
		$editHandler = new EditTaskHandler($this->selection->asSelection()->getWorldName(), new NonSavingBlockListSelection());
		WorldEdit::getEnv()->executeChunkedTask($this, $chunkHandler, $editHandler, $this->selection->asSelection()->getNeededChunks());

		EditThread::getInstance()->debug("Task " . $this->getTaskName() . ":" . $this->getTaskId() . " was executed successful, counting " . $editHandler->getReadBlockCount() . " blocks");
		return $this->toTaskResult();
	}

	protected function toTaskResult(): CountingTaskResult
	{
		return new CountingTaskResult($this->counted);
	}

	public function attemptRecovery(): TaskResult
	{
		return $this->toTaskResult();
	}

	public function calculateEffectiveComplexity(): int
	{
		return $this->selection->asSelection()->getSize()->volume();
	}

	public function canExecuteOnMainThread(): bool
	{
		return (new SingleChunkHandler($this->selection->asSelection()->getWorldName()))->checkLoaded($this->selection->asSelection());
	}

	/**
	 * @return string
	 */
	public function getTaskName(): string
	{
		return "count";
	}

	/**
	 * @param EditTaskHandler $handler
	 * @return Generator<ShapeConstructor>
	 */
	public function prepareConstructors(EditTaskHandler $handler): Generator
	{
		yield from $this->selection->asSelection()->asShapeConstructors(function (int $x, int $y, int $z) use ($handler): void {
			$id = $handler->getBlock($x, $y, $z);
			if (isset($this->counted[$id])) {
				$this->counted[$id]++;
			} else {
				$this->counted[$id] = 1;
			}
		}, $this->context);
	}

	public function putData(ExtendedBinaryStream $stream): void
	{
		$stream->putString(SelectionSerializer::fastSerialize($this->selection));
		$stream->putString($this->context->fastSerialize());
	}

	public function parseData(ExtendedBinaryStream $stream): void
	{
		$this->selection = SelectionSerializer::fastDeserialize($stream->getString());
		$this->context = SelectionContext::fastDeserialize($stream->getString());
	}
}