<?php

declare(strict_types=1);

namespace pocketmine\form\ddui;

use pocketmine\network\mcpe\protocol\ProtocolInfo;

final class DduiProperties{
	public const MESSAGE_BOX_ROOT = "message_box_data";
	public const CUSTOM_FORM_ROOT = "custom_form_data";

	private function __construct(){
	}

	public static function dataStoreName(string $screenId) : string{
		$colon = strpos($screenId, ":");
		return $colon === false ? $screenId : substr($screenId, 0, $colon);
	}

	public static function clientPropertyName(string $rootProperty, int $protocolId, int $formId) : string{
		if($protocolId >= ProtocolInfo::PROTOCOL_1_26_20){
			return $rootProperty . "_" . $formId;
		}

		return $rootProperty;
	}
}
