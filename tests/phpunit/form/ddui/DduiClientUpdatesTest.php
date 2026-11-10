<?php

declare(strict_types=1);

namespace pocketmine\form\ddui;

use PHPUnit\Framework\TestCase;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\types\ddui\update\StringDataStoreUpdateValue;

final class DduiClientUpdatesTest extends TestCase{

	public function testSetStringTargetsSessionProperty() : void{
		$form = new DduiMessageBoxForm("T", "B", "A", "B");
		$property = DduiProperties::clientPropertyName(
			$form->getRootPropertyName(),
			ProtocolInfo::CURRENT_PROTOCOL,
			1
		);
		$session = new DduiSession(
			1,
			1,
			$form->getScreenId(),
			DduiProperties::dataStoreName($form->getScreenId()),
			$property,
			$form,
			0
		);
		$update = DduiClientUpdates::setString($session, "body", "hello");
		self::assertSame(DduiProperties::dataStoreName(DduiScreens::MESSAGE_BOX), $update->getName());
		self::assertSame($property, $update->getProperty());
		self::assertSame("body", $update->getPath());
		self::assertInstanceOf(StringDataStoreUpdateValue::class, $update->getData());
		self::assertSame("hello", $update->getData()->getValue());
	}
}
