<?php

declare(strict_types=1);

namespace pocketmine\sessionrecord;

use pocketmine\player\Player;

/**
 * One active recording for a connected player.
 */
final class ActiveSessionRecording{

	private int $lastMoveSampleTick = -1;

	/** @var array{x: float, y: float, z: float, yaw: float, pitch: float, world: string}|null */
	private ?array $lastWrittenMove = null;

	public function __construct(
		private readonly Player $player,
		private readonly JsonlSessionSink $sink,
		private readonly int $moveSampleTicks
	){}

	public function getPlayer() : Player{
		return $this->player;
	}

	public function getSink() : JsonlSessionSink{
		return $this->sink;
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	public function record(string $type, array $payload = []) : void{
		$this->sink->write($type, $payload);
	}

	public function sampleMoveIfDue(int $tickCounter) : void{
		if($this->moveSampleTicks <= 0){
			return;
		}
		if($this->lastMoveSampleTick >= 0 && ($tickCounter - $this->lastMoveSampleTick) < $this->moveSampleTicks){
			return;
		}
		if(!$this->player->isConnected()){
			return;
		}
		$this->lastMoveSampleTick = $tickCounter;
		$loc = $this->player->getLocation();
		$sample = [
			"x" => round($loc->x, 3),
			"y" => round($loc->y, 3),
			"z" => round($loc->z, 3),
			"yaw" => round($loc->yaw, 2),
			"pitch" => round($loc->pitch, 2),
			"world" => $loc->getWorld()->getFolderName(),
		];
		if($this->lastWrittenMove !== null && $this->lastWrittenMove === $sample){
			return;
		}
		$this->lastWrittenMove = $sample;
		$this->record("move", $sample);
	}
}
