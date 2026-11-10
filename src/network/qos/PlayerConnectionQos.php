<?php

declare(strict_types=1);

namespace pocketmine\network\qos;

/**
 * Per-player ping EWMA and view-distance QoS state.
 */
final class PlayerConnectionQos{

	public const TIER_NONE = 0;
	public const TIER_SOFT = 1;
	public const TIER_HARD = 2;

	private float $ewmaPingMs = 0.0;
	private bool $hasPing = false;
	private int $tier = self::TIER_NONE;

	public function __construct(
		private int $desiredViewDistance = -1
	){}

	public function notePing(int $pingMs) : void{
		if($pingMs < 0){
			return;
		}
		if(!$this->hasPing){
			$this->ewmaPingMs = (float) $pingMs;
			$this->hasPing = true;
			return;
		}
		// alpha ~0.2 — react to spikes without flapping every sample
		$this->ewmaPingMs = (0.2 * $pingMs) + (0.8 * $this->ewmaPingMs);
	}

	public function hasPing() : bool{
		return $this->hasPing;
	}

	public function getEwmaPingMs() : float{
		return $this->ewmaPingMs;
	}

	public function setDesiredViewDistance(int $distance) : void{
		$this->desiredViewDistance = $distance;
	}

	public function getDesiredViewDistance() : int{
		return $this->desiredViewDistance;
	}

	public function getTier() : int{
		return $this->tier;
	}

	public function setTier(int $tier) : void{
		$this->tier = $tier;
	}
}
