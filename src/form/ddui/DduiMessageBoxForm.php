<?php

declare(strict_types=1);

namespace pocketmine\form\ddui;

use pocketmine\form\FormValidationException;
use pocketmine\form\ModalForm;
use pocketmine\network\mcpe\protocol\types\cereal\DynamicValue;
use pocketmine\network\mcpe\protocol\types\ddui\DataStoreUpdate;
use pocketmine\event\player\DduiFormResponseEvent;
use pocketmine\player\Player;
use function is_bool;

/**
 * Minimal DDUI vertical slice: vanilla {@see DduiScreens::MESSAGE_BOX}.
 * Response: true = button1, false = button2, null = dismissed without a button.
 */
final class DduiMessageBoxForm implements DduiForm{
	/** @var (callable(Player, ?bool): void)|null */
	private $callable;

	private bool $handled = false;

	private string $button1Tooltip = "";
	private string $button2Tooltip = "";

	/**
	 * @phpstan-param (callable(Player, ?bool): void)|null $callable
	 */
	public function __construct(
		private string $title = "",
		private string $body = "",
		private string $button1 = "",
		private string $button2 = "",
		?callable $callable = null,
	){
		$this->callable = $callable;
	}

	public function setTitle(string $title) : self{
		$this->title = $title;
		return $this;
	}

	public function setBody(string $body) : self{
		$this->body = $body;
		return $this;
	}

	public function setButton1(string $text, string $tooltip = "") : self{
		$this->button1 = $text;
		$this->button1Tooltip = $tooltip;
		return $this;
	}

	public function setButton2(string $text, string $tooltip = "") : self{
		$this->button2 = $text;
		$this->button2Tooltip = $tooltip;
		return $this;
	}

	public function getScreenId() : string{
		return DduiScreens::MESSAGE_BOX;
	}

	public function getRootPropertyName() : string{
		return DduiProperties::MESSAGE_BOX_ROOT;
	}

	public function buildInitialValue() : DynamicValue{
		return DduiCereal::map([
			"title" => DduiCereal::string($this->title),
			"body" => DduiCereal::string($this->body),
			"button1" => DduiCereal::messageBoxButton($this->button1, $this->button1Tooltip),
			"button2" => DduiCereal::messageBoxButton($this->button2, $this->button2Tooltip),
		]);
	}

	public function handleClientUpdate(Player $player, DduiSession $session, DataStoreUpdate $update) : void{
		$response = DduiPathParser::resolveMessageBoxButton($update->getPath());
		if($response === null){
			return;
		}
		$mgr = $player->getNetworkSession()->getDduiSessionManager();
		$this->dispatchResponse($player, $session->getFormId(), $response);
		$mgr->close($session->getFormId(), true);
	}

	public function handleClose(Player $player, DduiSession $session, ?string $closeReason) : void{
		if($this->handled){
			return;
		}
		$choice = DduiPathParser::resolveMessageBoxButtonFromCloseReason($closeReason);
		$this->dispatchResponse($player, $session->getFormId(), $choice);
	}

	private function dispatchResponse(Player $player, int $formId, ?bool $response) : void{
		if($this->handled){
			return;
		}
		$this->handled = true;
		$ev = new DduiFormResponseEvent($player, $formId, $this, $response);
		$ev->call();
		if($ev->isCancelled()){
			return;
		}
		if($this->callable !== null){
			($this->callable)($player, $response);
		}
	}

	public function send(Player $player) : void{
		$player->sendDduiForm($this);
	}

	public function toModalFallback() : ModalForm{
		return (new ModalForm(function(Player $p, $data) : void{
			if($this->callable === null){
				return;
			}
			if($data !== null && !is_bool($data)){
				throw new FormValidationException("Expected bool or null for ModalForm fallback");
			}
			($this->callable)($p, $data);
		}))
			->setTitle($this->title)
			->setContent($this->body)
			->setButton1($this->button1 !== "" ? $this->button1 : "OK")
			->setButton2($this->button2 !== "" ? $this->button2 : "Cancel");
	}
}
