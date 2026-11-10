<?php

declare(strict_types=1);

namespace pocketmine\form\ddui;

use pocketmine\network\mcpe\protocol\types\cereal\DynamicValue;
use pocketmine\network\mcpe\protocol\types\ddui\DataStoreUpdate;
use pocketmine\player\Player;

/**
 * CustomForm DDUI assembled from {@see DduiLayoutBuilder}.
 *
 * @phpstan-type ClientUpdateHandler \Closure(Player, DduiSession, DataStoreUpdate): void
 * @phpstan-type CloseHandler \Closure(Player, DduiSession, ?string): void
 */
final class DduiBuiltCustomForm implements DduiForm{
	/**
	 * @param ClientUpdateHandler|null $onClientUpdate
	 * @param CloseHandler|null        $onClose
	 */
	public function __construct(
		private string $title,
		private DduiLayoutBuilder $layout,
		private ?\Closure $onClientUpdate = null,
		private ?\Closure $onClose = null,
	){}

	public static function create(string $title) : self{
		return new self($title, new DduiLayoutBuilder());
	}

	public function layout() : DduiLayoutBuilder{
		return $this->layout;
	}

	public function addHeader(string $text) : self{
		$this->layout->addHeader($text);
		return $this;
	}

	public function addLabel(string $text) : self{
		$this->layout->addLabel($text);
		return $this;
	}

	public function addButton(string $label, string $tooltip = "") : self{
		$this->layout->addButton($label, $tooltip);
		return $this;
	}

	public function applyThemePreset(?string $alias) : self{
		$this->layout->applyThemePreset($alias);
		return $this;
	}

	public function addSpacer(bool $visible = true) : self{
		$this->layout->addSpacer($visible);
		return $this;
	}

	public function addDivider(bool $visible = true) : self{
		$this->layout->addDivider($visible);
		return $this;
	}

	public function withCloseButton(string $label = "Close", bool $visible = true) : self{
		$this->layout->withCloseButton($label, $visible);
		return $this;
	}

	public function onClientUpdate(\Closure $handler) : self{
		$this->onClientUpdate = $handler;
		return $this;
	}

	public function onClose(\Closure $handler) : self{
		$this->onClose = $handler;
		return $this;
	}

	public function getScreenId() : string{
		return DduiScreens::CUSTOM_FORM;
	}

	public function getRootPropertyName() : string{
		return DduiProperties::CUSTOM_FORM_ROOT;
	}

	public function buildInitialValue() : DynamicValue{
		return $this->layout->buildCustomFormRoot($this->title);
	}

	public function handleClientUpdate(Player $player, DduiSession $session, DataStoreUpdate $update) : void{
		if($this->onClientUpdate !== null){
			($this->onClientUpdate)($player, $session, $update);
		}
	}

	public function handleClose(Player $player, DduiSession $session, ?string $closeReason) : void{
		if($this->onClose !== null){
			($this->onClose)($player, $session, $closeReason);
		}
	}
}
