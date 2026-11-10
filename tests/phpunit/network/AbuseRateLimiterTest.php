<?php

declare(strict_types=1);

namespace pocketmine\network;

use PHPUnit\Framework\TestCase;

final class AbuseRateLimiterTest extends TestCase{

	public function testTryConsumeRespectsBurst() : void{
		$limiter = new AbuseRateLimiter("chat", 0.0, 2.0);
		self::assertTrue($limiter->tryConsume());
		self::assertTrue($limiter->tryConsume());
		self::assertFalse($limiter->tryConsume());
		self::assertSame("chat", $limiter->getName());
	}

	public function testShouldWarnRespectsCooldown() : void{
		$limiter = new AbuseRateLimiter("cmd", 1.0, 1.0, 60_000_000_000);
		self::assertTrue($limiter->shouldWarn());
		self::assertFalse($limiter->shouldWarn());
	}

	public function testRefillAllowsConsumeAfterWait() : void{
		$limiter = new AbuseRateLimiter("chat", 1000.0, 1.0);
		self::assertTrue($limiter->tryConsume());
		self::assertFalse($limiter->tryConsume());
		usleep(5_000);
		self::assertTrue($limiter->tryConsume());
	}
}
