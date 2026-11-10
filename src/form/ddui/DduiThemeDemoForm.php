<?php

declare(strict_types=1);

namespace pocketmine\form\ddui;

use pocketmine\network\mcpe\protocol\types\cereal\DynamicValue;
use pocketmine\network\mcpe\protocol\types\ddui\DataStoreUpdate;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function count;
use function implode;

final class DduiThemeDemoForm implements DduiForm, DduiThemeResolvable{
	public function __construct(
		private ?string $themeAlias = null,
	){
	}

	public function resolveThemeAlias(Player $player) : ?string{
		return $this->themeAlias;
	}

	public function getScreenId() : string{
		return DduiScreens::CUSTOM_FORM;
	}

	public function getRootPropertyName() : string{
		return DduiProperties::CUSTOM_FORM_ROOT;
	}

	public function buildInitialValue() : DynamicValue{
		$preset = DduiThemeRegistry::resolvePreset($this->themeAlias);
		$styleId = $preset->getStyleId();
		$styleLine = $styleId !== "" ? $styleId : "(vanilla Ore UI)";
		$discovered = [];
		foreach(DduiThemeRegistry::getDiscoveredStyles() as $s){
			$discovered[] = $s->getStyleId() . " @" . $s->getPackName();
		}
		$discList = count($discovered) > 0 ? implode("\n", $discovered) : "(none in resource_stack)";
		$builder = (new DduiLayoutBuilder())
			->applyThemePreset($this->themeAlias)
			->addHeader("DDUI Theme")
			->addLabel("Preset: " . $preset->getAlias())
			->addLabel("styleId: " . $styleLine)
			->addDivider()
			->addLabel("Discovered styles from RP:\n" . $discList)
			->addSpacer()
			->addLabel("Buttons reopen this screen with another preset.");

		foreach(DduiThemeRegistry::getPresets() as $p){
			$builder->addButton($p->getAlias(), $p->getStyleId() !== "" ? $p->getStyleId() : "Ore default");
		}

		return $builder
			->withCloseButton("Back to /ddui menu")
			->buildCustomFormRoot("DDUI theme demo");
	}

	public function handleClientUpdate(Player $player, DduiSession $session, DataStoreUpdate $update) : void{
		$btn = DduiPathParser::resolveCustomFormButtonSlot($update->getPath());
		if(DduiPathParser::isCloseButtonClick($update->getPath())){
			$player->getNetworkSession()->getDduiSessionManager()->close($session->getFormId(), true);
			return;
		}
		if($btn === null){
			return;
		}
		$idx = $btn - 7;
		$presets = DduiThemeRegistry::getPresets();
		if(!isset($presets[$idx])){
			return;
		}
		$alias = $presets[$idx]->getAlias();
		$player->sendMessage(TextFormat::GRAY . "DDUI theme: " . TextFormat::YELLOW . $alias);
		$player->sendDduiForm(new self($alias));
	}

	public function handleClose(Player $player, DduiSession $session, ?string $closeReason) : void{
		DduiTestMenu::returnToHubLater($player);
	}
}
