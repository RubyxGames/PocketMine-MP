<?php

declare(strict_types=1);

namespace pocketmine\form\ddui;

use pocketmine\network\mcpe\protocol\types\cereal\DynamicValue;
use pocketmine\network\mcpe\protocol\types\cereal\DynamicValueMap;

/**
 * Builds {@see DduiScreens::CUSTOM_FORM} layout trees (indexed slots + length).
 *
 * Each layout slot is a flat property bag (Nukkit-MOT / client wire shape), not wrapped in a type key.
 *
 * @phpstan-type DropdownItem array{label: string, value?: int, description?: string}
 */
final class DduiLayoutBuilder{
	/** @var DynamicValueMap[] */
	private array $slots = [];

	private ?DynamicValueMap $closeButton = null;

	private ?string $rootStyleId = null;

	public function withRootStyleId(string $styleId) : self{
		if($styleId !== ""){
			$this->rootStyleId = $styleId;
		}

		return $this;
	}

	public function applyThemePreset(?string $alias) : self{
		$styleId = DduiThemeRegistry::resolveStyleId($alias);
		return $this->withRootStyleId($styleId);
	}

	public function addLabel(string $text) : self{
		$this->slots[] = DduiCereal::map(DduiCereal::withElementDefaults([
			"text" => DduiCereal::string($text),
			"label_visible" => DduiCereal::bool(true),
		]));
		return $this;
	}

	public function addHeader(string $text) : self{
		$this->slots[] = DduiCereal::map(DduiCereal::withElementDefaults([
			"text" => DduiCereal::string($text),
			"header_visible" => DduiCereal::bool(true),
		]));
		return $this;
	}

	public function addSpacer(bool $visible = true) : self{
		$this->slots[] = DduiCereal::map(DduiCereal::withElementDefaults([
			"spacer_visible" => DduiCereal::bool($visible),
		]));
		return $this;
	}

	public function addDivider(bool $visible = true) : self{
		$this->slots[] = DduiCereal::map(DduiCereal::withElementDefaults([
			"divider_visible" => DduiCereal::bool($visible),
		]));
		return $this;
	}

	public function addButton(string $label, string $tooltip = "") : self{
		$fields = DduiCereal::withElementDefaults([
			"label" => DduiCereal::string($label),
			"onClick" => DduiCereal::long(0),
			"button_visible" => DduiCereal::bool(true),
		]);
		if($tooltip !== ""){
			$fields["tooltip"] = DduiCereal::string($tooltip);
		}
		$this->slots[] = DduiCereal::map($fields);
		return $this;
	}

	/**
	 * Root-level close action (sibling of {@see buildCustomFormRoot} `title` / `layout`), not a layout slot.
	 */
	public function withCloseButton(string $label = "Close", bool $visible = true) : self{
		$this->closeButton = DduiCereal::map(DduiCereal::withElementDefaults([
			"label" => DduiCereal::string($label),
			"onClick" => DduiCereal::long(0),
			"button_visible" => DduiCereal::bool($visible),
		]));
		return $this;
	}

	public function addToggle(string $label, bool $value = false, string $description = "") : self{
		return $this->addTooltipToggle($label, $value, "", $description);
	}

	/** PostSystem / JSON custom_form parity: label + optional tooltip on toggle rows. */
	public function addTooltipToggle(string $label, bool $value = false, string $tooltip = "", string $description = "") : self{
		$fields = DduiCereal::withElementDefaults([
			"label" => DduiCereal::string($label),
			"toggled" => DduiCereal::bool($value),
			"toggle_visible" => DduiCereal::bool(true),
		]);
		if($tooltip !== ""){
			$fields["tooltip"] = DduiCereal::string($tooltip);
		}
		if($description !== ""){
			$fields["description"] = DduiCereal::string($description);
		}
		$this->slots[] = DduiCereal::map($fields);
		return $this;
	}

	public function addTextField(string $label, string $text = "", string $description = "") : self{
		$fields = DduiCereal::withElementDefaults([
			"label" => DduiCereal::string($label),
			"text" => DduiCereal::string($text),
			"textfield_visible" => DduiCereal::bool(true),
		]);
		if($description !== ""){
			$fields["description"] = DduiCereal::string($description);
		}
		$this->slots[] = DduiCereal::map($fields);
		return $this;
	}

	public function addSlider(string $label, int $value, int $min, int $max, int $step = 1, string $description = "") : self{
		$fields = DduiCereal::withElementDefaults([
			"label" => DduiCereal::string($label),
			"value" => DduiCereal::long($value),
			"minValue" => DduiCereal::long($min),
			"maxValue" => DduiCereal::long($max),
			"step" => DduiCereal::long($step),
			"slider_visible" => DduiCereal::bool(true),
		]);
		if($description !== ""){
			$fields["description"] = DduiCereal::string($description);
		}
		$this->slots[] = DduiCereal::map($fields);
		return $this;
	}

	/**
	 * @param list<string> $optionLabels
	 */
	public function addDropdown(string $label, array $optionLabels, int $selectedIndex = 0, string $description = "") : self{
		$items = [];
		foreach($optionLabels as $i => $optionLabel){
			$items[] = ["label" => $optionLabel, "value" => $i, "description" => ""];
		}
		$selectedValue = $items[$selectedIndex]["value"] ?? 0;
		return $this->addDropdownItems($label, $items, $selectedValue, $description);
	}

	/**
	 * @param list<DropdownItem> $items
	 */
	public function addDropdownItems(string $label, array $items, int $selectedValue = 0, string $description = "") : self{
		$itemFields = [];
		foreach($items as $i => $item){
			$value = $item["value"] ?? $i;
			$itemFields[(string) $i] = DduiCereal::map([
				"label" => DduiCereal::string($item["label"]),
				"description" => DduiCereal::string($item["description"] ?? ""),
				"value" => DduiCereal::long($value),
			]);
		}
		$itemFields["length"] = DduiCereal::long(count($items));

		$fields = DduiCereal::withElementDefaults([
			"label" => DduiCereal::string($label),
			"value" => DduiCereal::long($selectedValue),
			"items" => DduiCereal::map($itemFields),
			"dropdown_visible" => DduiCereal::bool(true),
		]);
		if($description !== ""){
			$fields["description"] = DduiCereal::string($description);
		}
		$this->slots[] = DduiCereal::map($fields);
		return $this;
	}

	public function buildLayoutMap() : DynamicValueMap{
		$layout = [];
		foreach($this->slots as $i => $slot){
			$layout[(string) $i] = $slot;
		}
		$layout["length"] = DduiCereal::long(count($this->slots));
		return DduiCereal::map($layout);
	}

	public function buildCustomFormRoot(string $title) : DynamicValue{
		$root = [
			"title" => DduiCereal::string($title),
			"layout" => $this->buildLayoutMap(),
		];
		if($this->closeButton !== null){
			$root["closeButton"] = $this->closeButton;
		}
		if($this->rootStyleId !== null && $this->rootStyleId !== ""){
			$root["styleId"] = DduiCereal::string($this->rootStyleId);
		}
		return DduiCereal::map($root);
	}
}
