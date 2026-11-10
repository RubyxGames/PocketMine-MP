<?php

declare(strict_types=1);

namespace pocketmine\form;

use function gettype;
use function is_bool;

/**
 * Bedrock "modal" type: two buttons. Response is bool (button1=true) or null if closed.
 */
class ModalForm extends BaseForm{

	public function __construct(?callable $callable = null){
		parent::__construct($callable);
		$this->data["type"] = "modal";
		$this->data["title"] = "";
		$this->data["content"] = "";
		$this->data["button1"] = "";
		$this->data["button2"] = "";
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

	public function setButton1(string $text) : self{
		$this->data["button1"] = $text;
		return $this;
	}

	public function getButton1() : string{
		return (string) $this->data["button1"];
	}

	public function setButton2(string $text) : self{
		$this->data["button2"] = $text;
		return $this;
	}

	public function getButton2() : string{
		return (string) $this->data["button2"];
	}

	protected function processData(mixed &$data) : void{
		if($data !== null && !is_bool($data)){
			throw new FormValidationException("Expected bool or null response for ModalForm, got " . gettype($data));
		}
	}
}
