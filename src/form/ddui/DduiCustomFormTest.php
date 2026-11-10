<?php

declare(strict_types=1);

namespace pocketmine\form\ddui;

use pocketmine\network\mcpe\protocol\types\cereal\DynamicValue;
use pocketmine\network\mcpe\protocol\types\ddui\DataStoreUpdate;
use pocketmine\player\Player;

/**
 * Minimal {@see DduiScreens::CUSTOM_FORM} (single label).
 */
final class DduiCustomFormTest implements DduiForm{

	public function getScreenId() : string{
		return DduiScreens::CUSTOM_FORM;
	}

	public function getRootPropertyName() : string{
		return DduiProperties::CUSTOM_FORM_ROOT;
	}

	public function buildInitialValue() : DynamicValue{
		return (new DduiLayoutBuilder())
			->addLabel("Minimal CustomForm — close with client back control.")
			->buildCustomFormRoot("DDUI CustomForm (minimal)");
	}

	public function handleClientUpdate(Player $player, DduiSession $session, DataStoreUpdate $update) : void{
	}

	public function handleClose(Player $player, DduiSession $session, ?string $closeReason) : void{
		$player->sendMessage("DDUI CustomForm (minimal) closed" . ($closeReason !== null && $closeReason !== "" ? " ($closeReason)" : ""));
		DduiTestMenu::returnToHubLater($player);
	}
}
