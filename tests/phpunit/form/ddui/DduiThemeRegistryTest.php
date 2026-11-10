<?php

declare(strict_types=1);

namespace pocketmine\form\ddui;

use PHPUnit\Framework\TestCase;

final class DduiThemeRegistryTest extends TestCase{

	public function testResolveStyleIdUsesInstallDefaultsWhenNotBootstrapped() : void{
		self::assertSame("", DduiThemeRegistry::resolveStyleId("rubyx:classic"));
	}
}
