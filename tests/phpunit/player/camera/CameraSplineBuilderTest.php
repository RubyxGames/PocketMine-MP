<?php

declare(strict_types=1);

namespace pocketmine\player\camera;

use PHPUnit\Framework\TestCase;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\camera\CameraSetInstructionEaseType;

final class CameraSplineBuilderTest extends TestCase{

	public function testBuildNamed() : void{
		$def = CameraSplineBuilder::create()
			->time(2.5)
			->ease(CameraSetInstructionEaseType::LINEAR)
			->point(new Vector3(0, 64, 0))
			->point(new Vector3(10, 70, 10))
			->id("intro")
			->buildNamed("intro");
		self::assertSame("intro", $def->getName());
		self::assertSame(2.5, $def->getInstruction()->getTotalTime());
		self::assertCount(2, $def->getInstruction()->getCurve());
	}
}
