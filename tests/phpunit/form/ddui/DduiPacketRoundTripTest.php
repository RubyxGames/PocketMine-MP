<?php

declare(strict_types=1);

namespace pocketmine\form\ddui;

use PHPUnit\Framework\TestCase;
use pocketmine\network\mcpe\protocol\ClientboundDataStorePacket;
use pocketmine\network\mcpe\protocol\ClientboundDataDrivenUIShowScreenPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\types\ddui\DataStoreChange;

final class DduiPacketRoundTripTest extends TestCase{

	public function testMessageBoxOpenPacketsBuild() : void{
		$form = (new DduiMessageBoxForm("T", "B", "Yes", "No"))
			->setButton1("Yes")
			->setButton2("No");
		$property = DduiProperties::clientPropertyName(
			$form->getRootPropertyName(),
			ProtocolInfo::CURRENT_PROTOCOL,
			42
		);
		$change = new DataStoreChange(
			DduiProperties::dataStoreName($form->getScreenId()),
			$property,
			1,
			$form->buildInitialValue()
		);
		$store = ClientboundDataStorePacket::create([$change]);
		$show = ClientboundDataDrivenUIShowScreenPacket::create($form->getScreenId(), 42, 42);

		self::assertCount(1, $store->values);
		self::assertInstanceOf(DataStoreChange::class, $store->values[0]);
		self::assertSame(42, $show->getFormId());
		self::assertSame(DduiScreens::MESSAGE_BOX, $show->getScreenId());
		self::assertSame(42, $show->getDataInstanceId());
	}
}
