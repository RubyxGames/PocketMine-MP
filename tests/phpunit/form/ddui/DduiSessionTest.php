<?php

declare(strict_types=1);

namespace pocketmine\form\ddui;

use PHPUnit\Framework\TestCase;
use pocketmine\network\mcpe\protocol\ProtocolInfo;

final class DduiSessionTest extends TestCase{

	public function testMatchesProperty() : void{
		$form = new DduiMessageBoxForm();
		$property = DduiProperties::clientPropertyName(
			$form->getRootPropertyName(),
			ProtocolInfo::CURRENT_PROTOCOL,
			7
		);
		$session = new DduiSession(
			7,
			7,
			DduiScreens::MESSAGE_BOX,
			DduiProperties::dataStoreName(DduiScreens::MESSAGE_BOX),
			$property,
			$form,
			100
		);
		self::assertTrue($session->matchesProperty(
			DduiProperties::dataStoreName(DduiScreens::MESSAGE_BOX),
			$property
		));
		self::assertFalse($session->matchesProperty("other", $property));
		self::assertFalse($session->matchesProperty(
			DduiProperties::dataStoreName(DduiScreens::MESSAGE_BOX),
			"wrong"
		));
	}
}
