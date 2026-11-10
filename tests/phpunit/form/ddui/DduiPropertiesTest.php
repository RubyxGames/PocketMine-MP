<?php

declare(strict_types=1);

namespace pocketmine\form\ddui;

use PHPUnit\Framework\TestCase;
use pocketmine\network\mcpe\protocol\ProtocolInfo;

final class DduiPropertiesTest extends TestCase{

	public function testDataStoreNameFromScreenId() : void{
		self::assertSame("minecraft", DduiProperties::dataStoreName(DduiScreens::MESSAGE_BOX));
	}

	public function testClientPropertySuffixOn12620() : void{
		self::assertSame(
			"message_box_data_7",
			DduiProperties::clientPropertyName(DduiProperties::MESSAGE_BOX_ROOT, ProtocolInfo::PROTOCOL_1_26_60, 7)
		);
	}

	public function testClientPropertyLegacy() : void{
		self::assertSame(
			"message_box_data",
			DduiProperties::clientPropertyName(DduiProperties::MESSAGE_BOX_ROOT, ProtocolInfo::PROTOCOL_1_26_10, 7)
		);
	}
}
