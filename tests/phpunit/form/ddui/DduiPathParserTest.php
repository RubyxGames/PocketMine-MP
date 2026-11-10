<?php

declare(strict_types=1);

namespace pocketmine\form\ddui;

use PHPUnit\Framework\TestCase;

final class DduiPathParserTest extends TestCase{

	public function testMessageBoxButtonPaths() : void{
		self::assertTrue(DduiPathParser::resolveMessageBoxButton("button1.onClick"));
		self::assertTrue(DduiPathParser::resolveMessageBoxButton("MessageBox.button1.onClick"));
		self::assertFalse(DduiPathParser::resolveMessageBoxButton("button2.onClick"));
		self::assertNull(DduiPathParser::resolveMessageBoxButton("body"));
		self::assertNull(DduiPathParser::resolveMessageBoxButton(""));
	}

	public function testMessageBoxCloseReason() : void{
		self::assertTrue(DduiPathParser::resolveMessageBoxButtonFromCloseReason("button1"));
		self::assertFalse(DduiPathParser::resolveMessageBoxButtonFromCloseReason("button_2"));
		self::assertNull(DduiPathParser::resolveMessageBoxButtonFromCloseReason(null));
	}

	public function testCustomFormButtonSlot() : void{
		self::assertSame(3, DduiPathParser::resolveCustomFormButtonSlot("layout.3.onClick"));
		self::assertSame(3, DduiPathParser::resolveCustomFormButtonSlot("3.button.onClick"));
		self::assertSame(0, DduiPathParser::resolveCustomFormButtonSlot("layout.0.button.onclick"));
		self::assertNull(DduiPathParser::resolveCustomFormButtonSlot("0.toggle.toggled"));
	}

	public function testCloseButtonAndTogglePaths() : void{
		self::assertTrue(DduiPathParser::isCloseButtonClick("closeButton.onClick"));
		self::assertFalse(DduiPathParser::isCloseButtonClick("layout.1.onClick"));
		self::assertTrue(DduiPathParser::isLayoutTogglePath("layout.5.toggled", 5));
		self::assertFalse(DduiPathParser::isLayoutTogglePath("layout.4.toggled", 5));
		self::assertSame(5, DduiPathParser::resolveLayoutToggleSlot("layout.5.toggled"));
	}
}
