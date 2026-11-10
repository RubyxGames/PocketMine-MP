<?php

declare(strict_types=1);

namespace pocketmine\form\ddui;

use pocketmine\network\mcpe\protocol\types\cereal\DynamicValue;
use pocketmine\network\mcpe\protocol\types\ddui\DataStoreUpdate;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

/** Static domain payload for `minechat:postsystem_chat` RP spike (/ddui). */
final class DduiPostSystemChatSpikeForm implements DduiForm, DduiFormOnOpen{

	public function getScreenId() : string{
		return DduiScreens::MINECHAT_POSTSYSTEM_CHAT;
	}

	public function getRootPropertyName() : string{
		return DduiPostSystemChatProperties::ROOT;
	}

	public function buildInitialValue() : DynamicValue{
		return DduiCereal::map([
			"chrome" => DduiCereal::map([
				"brandTitle" => DduiCereal::string("MINECHAT"),
				"chatHeader" => DduiCereal::string("CHAT | Spike"),
				"partnerId" => DduiCereal::string("spike:test"),
			]),
			"history" => DduiCereal::map([
				"body" => DduiCereal::string("PostSystem DDUI screen spike.\nIf you see MINECHAT layout, RP screen is wired."),
				"visible" => DduiCereal::bool(true),
			]),
			"compose" => DduiCereal::map([
				"text" => DduiCereal::string(""),
				"placeholder" => DduiCereal::string("Type a message..."),
				"maxLength" => DduiCereal::long(100),
			]),
			"contacts" => DduiCereal::map(["length" => DduiCereal::long(0)]),
			"actions" => DduiCereal::map([
				"send" => DduiCereal::map(["onClick" => DduiCereal::long(0), "visible" => DduiCereal::bool(true)]),
				"refresh" => DduiCereal::map(["onClick" => DduiCereal::long(0), "visible" => DduiCereal::bool(true)]),
			]),
		]);
	}

	public function onDduiOpen(Player $player, DduiSession $session) : void{
		$player->sendMessage(TextFormat::GRAY . "Screen: " . DduiScreens::MINECHAT_POSTSYSTEM_CHAT);
	}

	public function handleClientUpdate(Player $player, DduiSession $session, DataStoreUpdate $update) : void{
		// spike
	}

	public function handleClose(Player $player, DduiSession $session, ?string $closeReason) : void{
		$player->sendMessage(TextFormat::GRAY . "PostSystem screen closed.");
		DduiTestMenu::returnToHubLater($player);
	}
}
