<?php

namespace pocketmine\world\worldedit\task\benchmark;

use pocketmine\world\worldedit\math\BlockVector;
use pocketmine\world\worldedit\math\OffGridBlockVector;
use pocketmine\world\worldedit\pattern\block\StaticBlock;
use pocketmine\world\worldedit\pattern\parser\PatternParser;
use pocketmine\world\worldedit\result\BenchmarkTaskResult;
use pocketmine\world\worldedit\selection\Cube;
use pocketmine\world\worldedit\task\editing\CopyTask;
use pocketmine\world\worldedit\task\editing\DynamicPasteTask;
use pocketmine\world\worldedit\task\editing\SetTask;
use pocketmine\world\worldedit\task\EditThreadExclusive;
use pocketmine\world\worldedit\task\ExecutableTask;
use pocketmine\world\worldedit\thread\output\TaskNotifyData;
use pocketmine\world\worldedit\utils\ExtendedBinaryStream;
use pocketmine\block\VanillaBlocks;
use pocketmine\world\World;

/**
 * @extends ExecutableTask<BenchmarkTaskResult>
 */
class BenchmarkExecutor extends ExecutableTask
{
	use EditThreadExclusive;

	/**
	 * @var array{string, int, float}[]
	 */
	private array $results = [];

	/**
	 * @param string $world
	 */
	public function __construct(private string $world)
	{
		parent::__construct();
	}

	protected function executeInternal(): BenchmarkTaskResult
	{
		$this->results = [];

		$pos = new OffGridBlockVector(0, World::Y_MIN, 0);

		//10x10 Chunk cube
		$testCube = new Cube($this->world, new BlockVector(0, World::Y_MIN, 0), new BlockVector(159, World::Y_MAX - 1, 159));

		//Task #1 - set static
		$start = microtime(true);
		$res = (new SetTask($testCube, StaticBlock::from(VanillaBlocks::STONE())))->executeInternal();
		$this->results[] = ["set static", $res->getAffected(), microtime(true) - $start];
		$this->sendOutputPacket(new TaskNotifyData(1));

		//Task #2 - set complex
		$start = microtime(true);
		//3D-Chess Pattern with stone and dirt
		$pattern = PatternParser::parseInternal("even;y(even;xz(stone).odd;xz(stone).dirt).even;xz(dirt).odd;xz(dirt).stone");
		$res = (new SetTask($testCube, $pattern))->executeInternal();
		$this->results[] = ["set complex", $res->getAffected(), microtime(true) - $start];
		$this->sendOutputPacket(new TaskNotifyData(2));

		//Task #3 - copy
		$start = microtime(true);
		$res = (new CopyTask($testCube, $pos))->executeInternal();
		$this->results[] = ["copy", $res->getAffected(), microtime(true) - $start];
		$this->sendOutputPacket(new TaskNotifyData(3));

		//Task #4 - paste
		$start = microtime(true);
		$res = (new DynamicPasteTask($this->world, $res->getSelection(), $pos))->executeInternal();
		$this->results[] = ["paste", $res->getAffected(), microtime(true) - $start];

		return new BenchmarkTaskResult($this->results);
	}

	public function attemptRecovery(): BenchmarkTaskResult
	{
		return new BenchmarkTaskResult($this->results);
	}

	public function putData(ExtendedBinaryStream $stream): void
	{
		$stream->putString($this->world);
	}

	public function parseData(ExtendedBinaryStream $stream): void
	{
		$this->world = $stream->getString();
	}

	public function getTaskName(): string
	{
		return "benchmark";
	}
}