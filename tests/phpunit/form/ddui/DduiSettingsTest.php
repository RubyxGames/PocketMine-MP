<?php

declare(strict_types=1);

namespace pocketmine\form\ddui;

use PHPUnit\Framework\TestCase;
use pocketmine\ServerConfigGroup;
use pocketmine\utils\Config;

final class DduiSettingsTest extends TestCase{

	public function testConfigureFromConfig() : void{
		$tmp = sys_get_temp_dir() . "/ddui_settings_test.json";
		$yml = new Config($tmp, Config::JSON, [
			"ddui" => [
				"enabled" => false,
				"replace-delay-ticks" => 40,
				"session-timeout-ticks" => 100,
				"max-inbound-updates-per-tick" => 8,
				"max-outbound-updates-per-tick" => 16,
			],
		]);
		$props = new Config(sys_get_temp_dir() . "/ddui_props", Config::PROPERTIES, []);
		$group = new ServerConfigGroup($yml, $props);
		DduiSettings::configureFromConfig($group);
		self::assertFalse(DduiSettings::isEnabled());
		self::assertSame(40, DduiSettings::getReplaceDelayTicks());
		self::assertSame(100, DduiSettings::getSessionTimeoutTicks());
		self::assertSame(8, DduiSettings::getMaxInboundUpdatesPerTick());
		self::assertSame(16, DduiSettings::getMaxOutboundUpdatesPerTick());
	}
}
