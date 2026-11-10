<?php

declare(strict_types=1);

namespace pocketmine\form;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class SimpleFormTest extends TestCase{

	public function testSerializeButtonsAndImages() : void{
		$form = (new SimpleForm())
			->setTitle("T")
			->setContent("C")
			->addButton("plain")
			->addButton("path", SimpleForm::IMAGE_TYPE_PATH, "textures/ui/check");

		$json = $form->jsonSerialize();
		self::assertSame("form", $json["type"]);
		self::assertSame("T", $json["title"]);
		self::assertSame("C", $json["content"]);
		self::assertCount(2, $json["buttons"]);
		self::assertSame(["text" => "plain"], $json["buttons"][0]);
		self::assertSame([
			"text" => "path",
			"image" => ["type" => "path", "data" => "textures/ui/check"],
		], $json["buttons"][1]);
	}

	public function testValidateAcceptsButtonIndexAndNull() : void{
		$form = (new SimpleForm())->addButton("a")->addButton("b");
		$nullData = null;
		$this->process($form, $nullData);
		$data = 1;
		$this->process($form, $data);
		self::assertSame(1, $data);
	}

	public function testValidateRejectsOutOfRangeAndWrongType() : void{
		$form = (new SimpleForm())->addButton("a");

		$this->expectException(FormValidationException::class);
		$data = 1;
		$this->process($form, $data);
	}

	public function testValidateRejectsNonInt() : void{
		$form = (new SimpleForm())->addButton("a");
		$this->expectException(FormValidationException::class);
		$data = "0";
		$this->process($form, $data);
	}

	private function process(SimpleForm $form, mixed &$data) : void{
		$method = new ReflectionMethod(SimpleForm::class, "processData");
		$method->invokeArgs($form, [&$data]);
	}
}
