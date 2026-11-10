<?php

declare(strict_types=1);

namespace pocketmine\player;

use PHPUnit\Framework\TestCase;

final class MovementGuardTest extends TestCase{

	public function testConfigureThresholds() : void{
		MovementGuard::configure(true, 20.0, 8.0);
		self::assertTrue(MovementGuard::isEnabled());
		self::assertSame(20.0, MovementGuard::getMaxBlocksPerMove());
		self::assertSame(8.0, MovementGuard::getMaxBlocksPerTick());
		self::assertSame(400.0, MovementGuard::getMaxBlocksPerMoveSquared());
		self::assertSame(64.0, MovementGuard::getMaxBlocksPerTickSquared());

		MovementGuard::configure(false, 15.0, 10.0);
		self::assertFalse(MovementGuard::isEnabled());
	}
}
