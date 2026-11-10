<?php

declare(strict_types=1);

namespace pocketmine\network;

use function hrtime;
use function min;

/**
 * Soft token-bucket limiter (deny without kick). Used for chat/command abuse limits.
 */
final class AbuseRateLimiter{

	private float $tokens;
	private int $lastUpdateNs;
	private int $lastWarnNs = 0;

	public function __construct(
		private string $name,
		private float $refillPerSecond,
		private float $burst,
		private int $warnCooldownNs = 2_000_000_000,
	){
		$this->tokens = $this->burst;
		$this->lastUpdateNs = hrtime(true);
	}

	/**
	 * @return bool true if the action is allowed
	 */
	public function tryConsume(float $amount = 1.0) : bool{
		$this->refill();
		if($this->tokens < $amount){
			return false;
		}
		$this->tokens -= $amount;
		return true;
	}

	/**
	 * Whether a user-facing warning should be emitted (rate-limits warn spam).
	 */
	public function shouldWarn() : bool{
		$now = hrtime(true);
		if(($now - $this->lastWarnNs) < $this->warnCooldownNs){
			return false;
		}
		$this->lastWarnNs = $now;
		return true;
	}

	public function getName() : string{
		return $this->name;
	}

	private function refill() : void{
		$now = hrtime(true);
		$elapsedSec = ($now - $this->lastUpdateNs) / 1_000_000_000;
		if($elapsedSec <= 0){
			return;
		}
		$this->tokens = min($this->burst, $this->tokens + ($this->refillPerSecond * $elapsedSec));
		$this->lastUpdateNs = $now;
	}
}
