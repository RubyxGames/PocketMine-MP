<?php

declare(strict_types=1);

namespace pocketmine\world\light;

use pocketmine\ServerConfigGroup;

/**
 * Controls whether block/sky light recalculation executes immediately or is coalesced
 * until the end of the world tick (default). Not full off-thread async light.
 */
final class LightUpdatePolicy{

	private static bool $deferUpdates = true;
	private static int $deferredExecuteCount = 0;
	private static int $immediateExecuteCount = 0;

	private function __construct(){}

	public static function configureFromConfig(ServerConfigGroup $config) : void{
		self::$deferUpdates = $config->getPropertyBool("world.light.defer-updates", true);
	}

	public static function shouldDeferUpdates() : bool{
		return self::$deferUpdates;
	}

	public static function recordDeferredExecute() : void{
		++self::$deferredExecuteCount;
	}

	public static function recordImmediateExecute() : void{
		++self::$immediateExecuteCount;
	}

	public static function getDeferredExecuteCount() : int{
		return self::$deferredExecuteCount;
	}

	public static function getImmediateExecuteCount() : int{
		return self::$immediateExecuteCount;
	}

	/**
	 * @internal tests
	 */
	public static function configure(bool $deferUpdates) : void{
		self::$deferUpdates = $deferUpdates;
	}
}
