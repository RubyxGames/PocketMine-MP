<?php

declare(strict_types=1);

namespace pocketmine\form;

use function array_values;
use function count;
use function gettype;
use function is_array;

/**
 * Bedrock "custom_form" type: input, toggle, slider, dropdown, label.
 * Response is list of values matching element order, or null if closed.
 */
class CustomForm extends BaseForm{

	/**
	 * @var mixed[]
	 * @phpstan-var list<array<string, mixed>>
	 */
	private array $elements = [];

	public function __construct(?callable $callable = null){
		parent::__construct($callable);
		$this->data["type"] = "custom_form";
		$this->data["title"] = "";
		$this->data["content"] = [];
	}

	public function setTitle(string $title) : self{
		$this->data["title"] = $title;
		return $this;
	}

	public function getTitle() : string{
		return (string) $this->data["title"];
	}

	public function addLabel(string $text) : self{
		$this->elements[] = ["type" => "label", "text" => $text];
		$this->data["content"] = $this->elements;
		return $this;
	}

	public function addInput(string $text, string $placeholder = "", string $defaultValue = "") : self{
		$this->elements[] = [
			"type" => "input",
			"text" => $text,
			"placeholder" => $placeholder,
			"default" => $defaultValue
		];
		$this->data["content"] = $this->elements;
		return $this;
	}

	public function addToggle(string $text, bool $defaultValue = false) : self{
		$this->elements[] = [
			"type" => "toggle",
			"text" => $text,
			"default" => $defaultValue
		];
		$this->data["content"] = $this->elements;
		return $this;
	}

	public function addSlider(string $text, float $min, float $max, float $step = 1.0, float $defaultValue = 0.0) : self{
		$this->elements[] = [
			"type" => "slider",
			"text" => $text,
			"min" => $min,
			"max" => $max,
			"step" => $step,
			"default" => $defaultValue
		];
		$this->data["content"] = $this->elements;
		return $this;
	}

	/**
	 * @param string[] $options
	 * @phpstan-param list<string> $options
	 */
	public function addDropdown(string $text, array $options, int $defaultIndex = 0) : self{
		$this->elements[] = [
			"type" => "dropdown",
			"text" => $text,
			"options" => array_values($options),
			"default" => $defaultIndex
		];
		$this->data["content"] = $this->elements;
		return $this;
	}

	/**
	 * @param string[] $steps
	 * @phpstan-param list<string> $steps
	 */
	public function addStepSlider(string $text, array $steps, int $defaultIndex = 0) : self{
		$this->elements[] = [
			"type" => "step_slider",
			"text" => $text,
			"steps" => array_values($steps),
			"default" => (string) $defaultIndex
		];
		$this->data["content"] = $this->elements;
		return $this;
	}

	protected function processData(mixed &$data) : void{
		if($data === null){
			return;
		}
		if(!is_array($data)){
			throw new FormValidationException("Expected array or null response for CustomForm, got " . gettype($data));
		}
		if(count($data) > count($this->elements)){
			throw new FormValidationException("Too many values in CustomForm response");
		}
	}
}
