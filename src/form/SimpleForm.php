<?php

declare(strict_types=1);

namespace pocketmine\form;

use function count;
use function gettype;
use function is_int;

/**
 * Bedrock "form" type: title, content, buttons (optional path/url images).
 * Response is button index (int) or null if closed.
 */
class SimpleForm extends BaseForm{
	public const IMAGE_TYPE_PATH = 0;
	public const IMAGE_TYPE_URL = 1;

	/**
	 * @var mixed[][]
	 * @phpstan-var list<array{text: string, image?: array{type: string, data: string}}>
	 */
	private array $buttons = [];

	public function __construct(?callable $callable = null){
		parent::__construct($callable);
		$this->data["type"] = "form";
		$this->data["title"] = "";
		$this->data["content"] = "";
		$this->data["buttons"] = [];
	}

	public function setTitle(string $title) : self{
		$this->data["title"] = $title;
		return $this;
	}

	public function getTitle() : string{
		return (string) $this->data["title"];
	}

	public function setContent(string $content) : self{
		$this->data["content"] = $content;
		return $this;
	}

	public function getContent() : string{
		return (string) $this->data["content"];
	}

	public function addButton(string $text, int $imageType = -1, string $imagePath = "") : self{
		$button = ["text" => $text];
		if($imageType !== -1 && $imagePath !== ""){
			$button["image"] = [
				"type" => $imageType === self::IMAGE_TYPE_PATH ? "path" : "url",
				"data" => $imagePath
			];
		}
		$this->buttons[] = $button;
		$this->data["buttons"] = $this->buttons;
		return $this;
	}

	protected function processData(mixed &$data) : void{
		if($data === null){
			return;
		}
		if(!is_int($data)){
			throw new FormValidationException("Expected int or null response for SimpleForm, got " . gettype($data));
		}
		if($data < 0 || $data >= count($this->buttons)){
			throw new FormValidationException("Button index $data out of range");
		}
	}
}
