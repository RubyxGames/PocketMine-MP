<?php

declare(strict_types=1);

namespace pocketmine\form\ddui;

use pocketmine\network\mcpe\protocol\types\cereal\DynamicValue;
use pocketmine\network\mcpe\protocol\types\cereal\DynamicValueBool;
use pocketmine\network\mcpe\protocol\types\cereal\DynamicValueLong;
use pocketmine\network\mcpe\protocol\types\cereal\DynamicValueMap;
use pocketmine\network\mcpe\protocol\types\cereal\DynamicValueString;

/**
 * Helpers for building cereal DynamicValue trees used in DataStoreChange.
 */
final class DduiCereal{
	private function __construct(){
	}

	public static function string(string $value) : DynamicValueString{
		return new DynamicValueString($value);
	}

	public static function bool(bool $value) : DynamicValueBool{
		return new DynamicValueBool($value);
	}

	public static function long(int $value) : DynamicValueLong{
		return new DynamicValueLong($value);
	}

	/**
	 * @param array<string, DynamicValue|null> $fields
	 */
	public static function map(array $fields) : DynamicValueMap{
		return new DynamicValueMap($fields);
	}

	public static function messageBoxButton(string $label, string $tooltip = "") : DynamicValueMap{
		return self::formButton($label, $tooltip);
	}

	public static function formButton(string $label, string $tooltip = "") : DynamicValueMap{
		$fields = self::withElementDefaults([
			"label" => self::string($label),
			"onClick" => self::long(0),
		]);
		if($tooltip !== ""){
			$fields["tooltip"] = self::string($tooltip);
		}

		return self::map($fields);
	}

	/**
	 * @param array<string, DynamicValue|null> $fields
	 * @return array<string, DynamicValue|null>
	 */
	public static function withElementDefaults(array $fields) : array{
		$fields["visible"] ??= self::bool(true);
		$fields["disabled"] ??= self::bool(false);
		return $fields;
	}
}
