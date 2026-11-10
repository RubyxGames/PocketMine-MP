<?php

declare(strict_types=1);

namespace pocketmine\form\ddui;

use PHPUnit\Framework\TestCase;
use pocketmine\network\mcpe\protocol\types\cereal\DynamicValueMap;

final class DduiLayoutBuilderTest extends TestCase{

	public function testLayoutSlotsAreFlatNotWrapped() : void{
		$root = (new DduiLayoutBuilder())
			->addLabel("Hello")
			->addButton("OK")
			->buildCustomFormRoot("T");

		self::assertInstanceOf(DynamicValueMap::class, $root);
		$fields = $root->getValue();
		$layout = $fields["layout"];
		self::assertInstanceOf(DynamicValueMap::class, $layout);
		$slot0 = $layout->getValue()["0"];
		self::assertInstanceOf(DynamicValueMap::class, $slot0);
		self::assertArrayHasKey("text", $slot0->getValue());
		self::assertArrayNotHasKey("label", $slot0->getValue());

		$slot1 = $layout->getValue()["1"];
		self::assertInstanceOf(DynamicValueMap::class, $slot1);
		self::assertArrayHasKey("onClick", $slot1->getValue());
		self::assertArrayNotHasKey("button", $slot1->getValue());
	}

	public function testSpacerDividerAndCloseButtonOnRoot() : void{
		$root = (new DduiLayoutBuilder())
			->addSpacer()
			->addDivider()
			->withCloseButton("Dismiss")
			->buildCustomFormRoot("T");
		$fields = $root->getValue();
		$layout = $fields["layout"]->getValue();
		self::assertArrayHasKey("spacer_visible", $layout["0"]->getValue());
		self::assertArrayHasKey("divider_visible", $layout["1"]->getValue());
		self::assertArrayHasKey("closeButton", $fields);
		self::assertArrayHasKey("onClick", $fields["closeButton"]->getValue());
	}

	public function testRootStyleIdField() : void{
		$root = (new DduiLayoutBuilder())
			->withRootStyleId("minechat:postsystem")
			->addLabel("x")
			->buildCustomFormRoot("T");
		$fields = $root->getValue();
		self::assertArrayHasKey("styleId", $fields);
	}
}
