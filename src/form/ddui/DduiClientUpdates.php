<?php

declare(strict_types=1);

namespace pocketmine\form\ddui;

use pocketmine\network\mcpe\protocol\types\ddui\DataStoreUpdate;
use pocketmine\network\mcpe\protocol\types\ddui\update\BoolDataStoreUpdateValue;
use pocketmine\network\mcpe\protocol\types\ddui\update\DataStoreUpdateValue;
use pocketmine\network\mcpe\protocol\types\ddui\update\DoubleDataStoreUpdateValue;
use pocketmine\network\mcpe\protocol\types\ddui\update\StringDataStoreUpdateValue;

/**
 * Builders for clientbound {@see DataStoreUpdate} operations (reactive DDUI).
 */
final class DduiClientUpdates{
	private function __construct(){
	}

	public static function forSession(
		DduiSession $session,
		string $path,
		DataStoreUpdateValue $data,
		int $updateCount = 1,
		int $pathUpdateCount = 1,
	) : DataStoreUpdate{
		return new DataStoreUpdate(
			$session->getDataStoreName(),
			$session->getClientProperty(),
			$path,
			$data,
			$updateCount,
			$pathUpdateCount
		);
	}

	public static function setString(DduiSession $session, string $path, string $value) : DataStoreUpdate{
		return self::forSession($session, $path, new StringDataStoreUpdateValue($value));
	}

	public static function setBool(DduiSession $session, string $path, bool $value) : DataStoreUpdate{
		return self::forSession($session, $path, new BoolDataStoreUpdateValue($value));
	}

	public static function setDouble(DduiSession $session, string $path, float $value) : DataStoreUpdate{
		return self::forSession($session, $path, new DoubleDataStoreUpdateValue($value));
	}
}
