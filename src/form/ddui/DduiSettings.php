<?php

declare(strict_types=1);

namespace pocketmine\form\ddui;

use pocketmine\ServerConfigGroup;

/**
 * DDUI runtime settings (pocketmine.yml → {@see DduiSettings::configureFromConfig}).
 */
final class DduiSettings{
	private static bool $enabled = true;
	private static int $replaceDelayTicks = 20;
	private static int $sessionTimeoutTicks = 6000;
	private static int $maxInboundUpdatesPerTick = 32;
	private static int $maxOutboundUpdatesPerTick = 64;

	private function __construct(){
	}

	public static function configureFromConfig(ServerConfigGroup $config) : void{
		self::$enabled = $config->getPropertyBool("ddui.enabled", true);
		self::$replaceDelayTicks = max(0, $config->getPropertyInt("ddui.replace-delay-ticks", 20));
		self::$sessionTimeoutTicks = max(0, $config->getPropertyInt("ddui.session-timeout-ticks", 6000));
		self::$maxInboundUpdatesPerTick = max(1, $config->getPropertyInt("ddui.max-inbound-updates-per-tick", 32));
		self::$maxOutboundUpdatesPerTick = max(1, $config->getPropertyInt("ddui.max-outbound-updates-per-tick", 64));
	}

	public static function isEnabled() : bool{
		return self::$enabled;
	}

	public static function getReplaceDelayTicks() : int{
		return self::$replaceDelayTicks;
	}

	public static function getSessionTimeoutTicks() : int{
		return self::$sessionTimeoutTicks;
	}

	public static function getMaxInboundUpdatesPerTick() : int{
		return self::$maxInboundUpdatesPerTick;
	}

	public static function getMaxOutboundUpdatesPerTick() : int{
		return self::$maxOutboundUpdatesPerTick;
	}
}
