<?php

declare(strict_types=1);

namespace pocketmine\form\ddui;

use pocketmine\network\mcpe\protocol\types\cereal\DynamicValue;
use pocketmine\network\mcpe\protocol\types\ddui\DataStoreUpdate;
use pocketmine\network\mcpe\protocol\types\ddui\update\BoolDataStoreUpdateValue;
use pocketmine\network\mcpe\protocol\types\ddui\update\StringDataStoreUpdateValue;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function count;
use function preg_match;

/**
 * DDUI port of PostSystem chat (contacts + history + compose), inspired by DbDGame/PostSystem.zip.
 */
final class DduiPostSystemChatForm implements DduiForm, DduiFormOnOpen{
	private const SLOT_STATUS = 1;
	private const SLOT_HISTORY = 2;
	private const SLOT_CONTACT0 = 4;
	private const CONTACT_COUNT = 5;
	private const SLOT_COMPOSE = 10;
	private const SLOT_SEND = 11;
	private const SLOT_REFRESH = 12;

	private string $selectedId = "";

	private string $draft = "";

	/** @var list<array{id: string, name: string, unread: bool, msgs: list<mixed>}> */
	private array $contacts = [];

	public function getScreenId() : string{
		return DduiScreens::CUSTOM_FORM;
	}

	public function getRootPropertyName() : string{
		return DduiProperties::CUSTOM_FORM_ROOT;
	}

	public function buildInitialValue() : DynamicValue{
		$this->contacts = [];
		$this->selectedId = "";
		$builder = (new DduiLayoutBuilder())
			->addHeader("MineChat")
			->addLabel("Pick a contact")
			->addLabel("No messages yet.\nType below and press Send.")
			->addDivider();
		for($i = 0; $i < self::CONTACT_COUNT; $i++){
			$builder->addToggle("…", false);
		}
		return $builder
			->addSpacer()
			->addTextField("Message", "", "Type a message...")
			->addButton("Send", "Send to open chat")
			->addButton("Refresh", "Reload contacts and messages")
			->withCloseButton()
			->buildCustomFormRoot("MineChat");
	}

	public function onDduiOpen(Player $player, DduiSession $session) : void{
		$this->reloadContacts($player);
		if($this->selectedId === "" && count($this->contacts) > 0){
			$this->selectedId = $this->contacts[0]["id"];
		}
		PostSystemChatStore::markRead($player, $this->selectedId);
		$this->pushUi($player, $session);
	}

	public function handleClientUpdate(Player $player, DduiSession $session, DataStoreUpdate $update) : void{
		$path = $update->getPath();
		if(DduiPathParser::isCloseButtonClick($path)){
			$player->getNetworkSession()->getDduiSessionManager()->close($session->getFormId(), true);
			return;
		}
		if(preg_match('/layout\.(\d+)\.text$/i', $path, $m) === 1 && (int) $m[1] === self::SLOT_COMPOSE){
			$data = $update->getData();
			if($data instanceof StringDataStoreUpdateValue){
				$this->draft = $data->getValue();
			}
			return;
		}
		$toggleSlot = DduiPathParser::resolveLayoutToggleSlot($path);
		if($toggleSlot !== null && $toggleSlot >= self::SLOT_CONTACT0 && $toggleSlot < self::SLOT_CONTACT0 + self::CONTACT_COUNT){
			$index = $toggleSlot - self::SLOT_CONTACT0;
			$data = $update->getData();
			if($data instanceof BoolDataStoreUpdateValue && $data->getValue() && isset($this->contacts[$index])){
				$this->selectedId = $this->contacts[$index]["id"];
				PostSystemChatStore::markRead($player, $this->selectedId);
				$this->pushUi($player, $session);
			}
			return;
		}
		$btn = DduiPathParser::resolveCustomFormButtonSlot($path);
		if($btn === self::SLOT_REFRESH){
			$this->reloadContacts($player);
			$this->pushUi($player, $session);
			return;
		}
		if($btn === self::SLOT_SEND){
			$this->onSend($player, $session);
		}
	}

	public function handleClose(Player $player, DduiSession $session, ?string $closeReason) : void{
		$player->sendMessage(TextFormat::GRAY . "MineChat closed.");
		DduiTestMenu::returnToHubLater($player);
	}

	private function onSend(Player $player, DduiSession $session) : void{
		if($this->selectedId === ""){
			$player->sendMessage(TextFormat::RED . "No contact selected.");
			return;
		}
		$text = PostSystemChatStore::cleanText($this->draft);
		if(strtolower($text) === "!clear"){
			PostSystemChatStore::clearHistoryForMe($player, $this->selectedId);
			$this->draft = "";
			$this->reloadContacts($player);
			$this->pushUi($player, $session);
			$player->sendMessage(TextFormat::YELLOW . "Conversation hidden in your view (PostSystem !clear).");
			return;
		}
		if($text === ""){
			return;
		}
		PostSystemChatStore::sendMessage($player, $this->selectedId, $text);
		$this->draft = "";
		$this->reloadContacts($player);
		$this->pushUi($player, $session);
	}

	private function reloadContacts(Player $player) : void{
		$this->contacts = PostSystemChatStore::getContacts($player, $this->selectedId !== "" ? $this->selectedId : null);
		if($this->selectedId !== "" && !$this->hasContact($this->selectedId) && count($this->contacts) > 0){
			$this->selectedId = $this->contacts[0]["id"];
		}
	}

	private function hasContact(string $id) : bool{
		foreach($this->contacts as $c){
			if($c["id"] === $id){
				return true;
			}
		}
		return false;
	}

	private function selectedContact() : ?array{
		foreach($this->contacts as $c){
			if($c["id"] === $this->selectedId){
				return $c;
			}
		}
		return $this->contacts[0] ?? null;
	}

	private function pushUi(Player $player, DduiSession $session) : void{
		$mgr = $player->getNetworkSession()->getDduiSessionManager();
		$active = $this->selectedContact();
		$name = $active["name"] ?? "—";
		$history = $active !== null
			? PostSystemChatStore::renderChat($player, $active["id"], $active["name"])
			: "No contacts.";
		$updates = [
			DduiClientUpdates::setString($session, "layout." . self::SLOT_STATUS . ".text", "Chatting: " . $name),
			DduiClientUpdates::setString($session, "layout." . self::SLOT_HISTORY . ".text", $history),
			DduiClientUpdates::setString($session, "layout." . self::SLOT_COMPOSE . ".text", $this->draft),
		];
		for($i = 0; $i < self::CONTACT_COUNT; $i++){
			$slot = self::SLOT_CONTACT0 + $i;
			$contact = $this->contacts[$i] ?? null;
			$visible = $contact !== null;
			$isOpen = $visible && $contact["id"] === $this->selectedId;
			$label = $visible
				? PostSystemChatStore::contactToggleLabel($contact, $isOpen, $i)
				: "—";
			$updates[] = DduiClientUpdates::setString($session, "layout." . $slot . ".label", $label);
			$updates[] = DduiClientUpdates::setBool($session, "layout." . $slot . ".toggled", $isOpen);
			$updates[] = DduiClientUpdates::setBool($session, "layout." . $slot . ".toggle_visible", $visible);
			$updates[] = DduiClientUpdates::forSession($session, "layout." . $slot . ".disabled", new BoolDataStoreUpdateValue(!$visible));
		}
		$mgr->pushClientUpdates($updates);
	}
}
