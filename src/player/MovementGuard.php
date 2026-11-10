<?php

declare(strict_types=1);

namespace pocketmine\player;

use pocketmine\ServerConfigGroup;
use function max;

/**
 * Configurable movement safety thresholds (rubberband / revert). Not a full anti-cheat.
 */
final class MovementGuard{

	private static bool $enabled = true;
	private static float $maxBlocksPerMove = 15.0;
	private static float $maxBlocksPerTick = 10.0;
	private static bool $sendPredictionCorrection = true;

	private function __construct(){}

	public static function configureFromConfig(ServerConfigGroup $config) : void{
		self::$enabled = $config->getPropertyBool("player.movement.enabled", true);
		self::$maxBlocksPerMove = max(1.0, (float) $config->getProperty("player.movement.max-blocks-per-move", 15));
		self::$maxBlocksPerTick = max(1.0, (float) $config->getProperty("player.movement.max-blocks-per-tick", 10.0));
		self::$sendPredictionCorrection = $config->getPropertyBool("player.movement.send-prediction-correction", true);
	}

	public static function isEnabled() : bool{
		return self::$enabled;
	}

	public static function shouldSendPredictionCorrection() : bool{
		return self::$sendPredictionCorrection;
	}

	public static function getMaxBlocksPerMove() : float{
		return self::$maxBlocksPerMove;
	}

	public static function getMaxBlocksPerTick() : float{
		return self::$maxBlocksPerTick;
	}

	/** Squared distance threshold for a single auth-input movement packet. */
	public static function getMaxBlocksPerMoveSquared() : float{
		$m = self::$maxBlocksPerMove;
		return $m * $m;
	}

	/** Squared horizontal distance threshold for one processed movement tick. */
	public static function getMaxBlocksPerTickSquared() : float{
		$m = self::$maxBlocksPerTick;
		return $m * $m;
	}

	/**
	 * @internal tests
	 */
	/**
	 * @internal tests
	 */
	public static function configure(bool $enabled, float $maxPerMove, float $maxPerTick, bool $sendPredictionCorrection = true) : void{
		self::$enabled = $enabled;
		self::$maxBlocksPerMove = max(1.0, $maxPerMove);
		self::$maxBlocksPerTick = max(1.0, $maxPerTick);
		self::$sendPredictionCorrection = $sendPredictionCorrection;
	}
}
