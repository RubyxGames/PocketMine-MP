<?php

declare(strict_types=1);

namespace pocketmine\form\ddui;

use pocketmine\network\mcpe\protocol\types\cereal\DynamicValue;
use pocketmine\network\mcpe\protocol\types\ddui\DataStoreUpdate;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function str_contains;
use function strtolower;

/**
 * CustomForm DDUI showcase: all primary control types.
 */
final class DduiCustomFormDemo implements DduiForm{
	private bool $finished = false;

	public function getScreenId() : string{
		return DduiScreens::CUSTOM_FORM;
	}

	public function getRootPropertyName() : string{
		return DduiProperties::CUSTOM_FORM_ROOT;
	}

	public function buildInitialValue() : DynamicValue{
		return (new DduiLayoutBuilder())
			->addHeader("CustomForm controls")
			->addLabel("Edit controls and close the screen when done.")
			->addSpacer()
			->addToggle("Creative mode", false, "Sample toggle")
			->addTextField("Name", "Steve", "Sample text field")
			->addSlider("Volume", 50, 0, 100, 5, "Sample slider")
			->addDropdownItems("Difficulty", [
				["label" => "Peaceful", "value" => 0, "description" => "No hostile mobs"],
				["label" => "Easy", "value" => 1, "description" => "Reduced damage"],
				["label" => "Normal", "value" => 2, "description" => "Default"],
				["label" => "Hard", "value" => 3, "description" => "Increased challenge"],
			], 2, "Sample dropdown with per-item descriptions")
			->addDivider()
			->addButton("Done", "Close and return to hub")
			->withCloseButton()
			->buildCustomFormRoot("DDUI CustomForm (full)");
	}

	public function handleClientUpdate(Player $player, DduiSession $session, DataStoreUpdate $update) : void{
		if(DduiPathParser::isCloseButtonClick($update->getPath())){
			$this->finish($player, $session);
			return;
		}
		if(DduiPathParser::resolveCustomFormButtonSlot($update->getPath()) !== null){
			$this->finish($player, $session);
			return;
		}
		$player->sendMessage(TextFormat::DARK_GRAY . "CustomForm update: " . $update->getPath());
	}

	public function handleClose(Player $player, DduiSession $session, ?string $closeReason) : void{
		if($this->finished){
			return;
		}
		$player->sendMessage(TextFormat::GRAY . "CustomForm closed" . ($closeReason !== null && $closeReason !== "" ? " ($closeReason)" : ""));
		DduiTestMenu::returnToHubLater($player);
	}

	private function finish(Player $player, DduiSession $session) : void{
		if($this->finished){
			return;
		}
		$this->finished = true;
		$player->sendMessage(TextFormat::GREEN . "CustomForm: Done pressed");
		$player->getNetworkSession()->getDduiSessionManager()->close($session->getFormId(), true);
		DduiTestMenu::returnToHubLater($player);
	}
}
