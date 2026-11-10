<?php

namespace pocketmine\world\worldedit\task\expanding;

use BadMethodCallException;
use pocketmine\world\worldedit\math\BlockVector;
use pocketmine\world\worldedit\pattern\block\StaticBlock;
use pocketmine\world\worldedit\task\CancelException;
use pocketmine\world\worldedit\task\editing\EditTaskHandler;
use pocketmine\world\worldedit\utils\ConfigManager;
use pocketmine\world\worldedit\utils\ExtendedBinaryStream;
use pocketmine\world\worldedit\world\HeightMapCache;
use pocketmine\block\Block;
use pocketmine\math\Facing;
use pocketmine\world\World;
use SplPriorityQueue;

class FillTask extends ExpandingTask
{
	/**
	 * @param string      $world
	 * @param BlockVector $start
	 * @param int         $direction
	 * @param StaticBlock $block
	 */
	public function __construct(string $world, BlockVector $start, private int $direction, private StaticBlock $block)
	{
		parent::__construct($world, $start);
	}

	/**
	 * @param EditTaskHandler     $handler
	 * @param ManagedChunkHandler $loader
	 * @throws CancelException
	 */
	public function executeEdit(EditTaskHandler $handler, ManagedChunkHandler $loader): void
	{
		$ignore = HeightMapCache::getIgnore();
		if (($k = array_search($this->block->getTypeId(), $ignore, true)) !== false) {
			unset($ignore[$k]);
		}

		$queue = new SplPriorityQueue();
		$scheduled = [];
		$id = $this->block->get();
		$startX = $this->start->x;
		$startY = $this->start->y;
		$startZ = $this->start->z;
		$validate = match ($this->direction) {
			Facing::DOWN => static function (BlockVector $pos) use ($startY) {
				return $pos->y <= $startY;
			},
			Facing::UP => static function (BlockVector $pos) use ($startY) {
				return $pos->y >= $startY;
			},
			Facing::NORTH => static function (BlockVector $pos) use ($startZ) {
				return $pos->z <= $startZ;
			},
			Facing::SOUTH => static function (BlockVector $pos) use ($startZ) {
				return $pos->z >= $startZ;
			},
			Facing::WEST => static function (BlockVector $pos) use ($startX) {
				return $pos->x <= $startX;
			},
			Facing::EAST => static function (BlockVector $pos) use ($startX) {
				return $pos->x >= $startX;
			},
			default => throw new BadMethodCallException("Invalid direction")
		};
		$limit = ConfigManager::getFillDistance();

		$queue->setExtractFlags(SplPriorityQueue::EXTR_BOTH);
		$queue->insert(World::blockHash($startX, $startY, $startZ), 0);
		while (!$queue->isEmpty()) {
			/** @var array{data: int, priority: int} $current */
			$current = $queue->extract();
			if (-$current["priority"] > $limit) {
				break;
			}
			World::getBlockXYZ($current["data"], $x, $y, $z);
			$chunk = World::chunkHash($x >> 4, $z >> 4);
			$this->updateProgress(-$current["priority"], $limit);
			$loader->checkRuntimeChunk($chunk);
			if (!in_array($handler->getResultingBlock($x, $y, $z) >> Block::INTERNAL_STATE_DATA_BITS, $ignore, true)) {
				$loader->checkUnload($handler, $chunk);
				continue;
			}
			$handler->changeBlock($x, $y, $z, $id);
			foreach (Facing::ALL as $facing) {
				$side = (new BlockVector($x, $y, $z))->getSide($facing);
				if ($validate($side) && !isset($scheduled[$hash = $side->getBlockHash()])) {
					$scheduled[$hash] = true;
					$loader->registerRequestedChunks($side->getChunkHash());
					$queue->insert($hash, $facing === Facing::DOWN || $facing === Facing::UP ? $current["priority"] : $current["priority"] - 1);
				}
			}
			$loader->checkUnload($handler, $chunk);
		}
	}

	public function getTaskName(): string
	{
		return "fill";
	}

	public function putData(ExtendedBinaryStream $stream): void
	{
		parent::putData($stream);
		$stream->putByte($this->direction);
		$stream->putInt($this->block->get());
	}

	public function parseData(ExtendedBinaryStream $stream): void
	{
		parent::parseData($stream);
		$this->direction = $stream->getByte();
		$this->block = new StaticBlock($stream->getInt());
	}
}