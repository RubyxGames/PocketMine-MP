<?php

declare(strict_types=1);

namespace pocketmine\form\ddui;

use function preg_match;
use function str_contains;
use function strtolower;

final class DduiPathParser{
	private const LAYOUT_TOGGLE_PATTERN = '/(?:^|\.)layout\.(\d+)\.toggled/';
	private function __construct(){
	}

	public static function resolveMessageBoxButton(string $path) : ?bool{
		$normalized = strtolower($path);
		if($normalized === ""){
			return null;
		}
		if(str_contains($normalized, "button1") && str_contains($normalized, "onclick")){
			return true;
		}
		if(str_contains($normalized, "button2") && str_contains($normalized, "onclick")){
			return false;
		}
		return null;
	}

	public static function resolveMessageBoxButtonFromCloseReason(?string $closeReason) : ?bool{
		if($closeReason === null || $closeReason === ""){
			return null;
		}
		$reason = strtolower($closeReason);
		if(str_contains($reason, "button1") || str_contains($reason, "button_1")){
			return true;
		}
		if(str_contains($reason, "button2") || str_contains($reason, "button_2")){
			return false;
		}
		return null;
	}

	public static function resolveCustomFormButtonSlot(string $path) : ?int{
		$normalized = strtolower($path);
		if(!str_contains($normalized, "onclick")){
			return null;
		}
		if(preg_match('/(?:^|\.)(\d+)\.onclick/', $normalized, $matches) === 1){
			return (int) $matches[1];
		}
		if(preg_match('/(?:^|\.)(\d+)\.button\.onclick/', $normalized, $matches) === 1){
			return (int) $matches[1];
		}
		return null;
	}

	public static function isCloseButtonClick(string $path) : bool{
		$normalized = strtolower($path);
		return str_contains($normalized, "closebutton") && str_contains($normalized, "onclick");
	}

	public static function resolveLayoutToggleSlot(string $path) : ?int{
		$normalized = strtolower($path);
		if(!str_contains($normalized, "toggled")){
			return null;
		}
		if(preg_match(self::LAYOUT_TOGGLE_PATTERN, $normalized, $matches) !== 1){
			return null;
		}
		return (int) $matches[1];
	}

	public static function isLayoutTogglePath(string $path, int $slot) : bool{
		$normalized = strtolower($path);
		if(preg_match(self::LAYOUT_TOGGLE_PATTERN, $normalized, $matches) !== 1){
			return false;
		}
		return (int) $matches[1] === $slot;
	}
}
