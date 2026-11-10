<?php

declare(strict_types=1);

namespace pocketmine\form\ddui;

use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\types\cereal\DynamicValue;
use pocketmine\network\mcpe\protocol\types\ddui\DataStoreUpdate;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function preg_match;
use function sprintf;
use function str_contains;
use function strtolower;

/**
 * DDUI hub ({@see DduiScreens::CUSTOM_FORM}) — replaces JSON SimpleForm in {@see DduiTestMenu}.
 */
final class DduiTestHubForm implements DduiForm{
	private const SLOT_MESSAGE_BOX = 3;
	private const SLOT_MESSAGE_BOX_LONG = 4;
	private const SLOT_CUSTOM_FULL = 5;
	private const SLOT_CUSTOM_MINIMAL = 6;

	public function __construct(
		private string $statusLine,
	){}

	public static function forPlayer(Player $player) : self{
		$protocol = $player->getNetworkSession()->getProtocolId();
		$dduiOn = DduiSupport::playerSupports($player);
		$status = sprintf(
			"Protocol %d (target %d). DDUI: %s",
			$protocol,
			ProtocolInfo::CURRENT_PROTOCOL,
			$dduiOn ? "on" : "off"
		);
		return new self($status);
	}

	public function getScreenId() : string{
		return DduiScreens::CUSTOM_FORM;
	}

	public function getRootPropertyName() : string{
		return DduiProperties::CUSTOM_FORM_ROOT;
	}

	public function buildInitialValue() : DynamicValue{
		return (new DduiLayoutBuilder())
			->addHeader("DDUI test hub")
			->addLabel($this->statusLine)
			->addLabel("Screens: minecraft:message_box, minecraft:custom_form")
			->addButton("MessageBox", "Two-button dialog")
			->addButton("MessageBox (long body)", "Tooltips on buttons")
			->addButton("CustomForm — full controls", "label, toggle, text, slider, dropdown")
			->addButton("CustomForm — minimal", "Single label row")
			->buildCustomFormRoot("DDUI tests");
	}

	public function handleClientUpdate(Player $player, DduiSession $session, DataStoreUpdate $update) : void{
		$path = strtolower($update->getPath());
		if(!str_contains($path, "onclick") || !str_contains($path, "button")){
			return;
		}
		if(preg_match('/(?:^|\.)(\d+)\.button/', $path, $matches) !== 1){
			return;
		}
		$slot = (int) $matches[1];
		$mgr = $player->getNetworkSession()->getDduiSessionManager();
		$mgr->close($session->getFormId(), true);

		match($slot){
			self::SLOT_MESSAGE_BOX => DduiTestMenu::openMessageBox($player),
			self::SLOT_MESSAGE_BOX_LONG => DduiTestMenu::openMessageBoxLong($player),
			self::SLOT_CUSTOM_FULL => DduiTestMenu::openCustomFormFull($player),
			self::SLOT_CUSTOM_MINIMAL => DduiTestMenu::openCustomFormMinimal($player),
			default => $player->sendMessage(TextFormat::GRAY . "Hub: unknown button slot $slot"),
		};
	}

	public function handleClose(Player $player, DduiSession $session, ?string $closeReason) : void{
		$player->sendMessage(TextFormat::GRAY . "DDUI hub closed" . ($closeReason !== null && $closeReason !== "" ? " ($closeReason)" : ""));
	}
}
