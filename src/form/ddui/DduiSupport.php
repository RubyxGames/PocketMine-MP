<?php

declare(strict_types=1);

namespace pocketmine\form\ddui;

use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\player\Player;

final class DduiSupport{
	/** Runtime override — when false, DDUI is off regardless of pocketmine.yml. */
	public static bool $forceDisable = false;

	private function __construct(){
	}

	public static function isProtocolSupported(int $protocolId) : bool{
		return $protocolId >= ProtocolInfo::PROTOCOL_1_26_0;
	}

	public static function supportsScreenClosed(int $protocolId) : bool{
		return $protocolId >= ProtocolInfo::PROTOCOL_1_26_10;
	}

	public static function supportsDataInstanceId(int $protocolId) : bool{
		return $protocolId >= ProtocolInfo::PROTOCOL_1_26_20;
	}

	public static function playerSupports(Player $player) : bool{
		if(self::$forceDisable || !DduiSettings::isEnabled()){
			return false;
		}

		return self::isProtocolSupported($player->getNetworkSession()->getProtocolId());
	}

	public static function playerSupportsScreenClosed(Player $player) : bool{
		return self::playerSupports($player)
			&& self::supportsScreenClosed($player->getNetworkSession()->getProtocolId());
	}

	public static function playerSupportsDataInstanceId(Player $player) : bool{
		return self::playerSupports($player)
			&& self::supportsDataInstanceId($player->getNetworkSession()->getProtocolId());
	}

	/**
	 * @return 'unsupported'|'base'|'with_screen_close'|'full'
	 */
	public static function protocolTier(int $protocolId) : string{
		if(!self::isProtocolSupported($protocolId)){
			return "unsupported";
		}
		if(self::supportsDataInstanceId($protocolId)){
			return "full";
		}
		if(self::supportsScreenClosed($protocolId)){
			return "with_screen_close";
		}

		return "base";
	}
}
