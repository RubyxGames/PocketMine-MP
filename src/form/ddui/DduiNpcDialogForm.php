<?php

declare(strict_types=1);

namespace pocketmine\form\ddui;

use pocketmine\network\mcpe\protocol\types\cereal\DynamicValue;
use pocketmine\network\mcpe\protocol\types\ddui\DataStoreUpdate;
use pocketmine\network\mcpe\protocol\types\ddui\update\BoolDataStoreUpdateValue;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function array_slice;

/**
 * Тест: окно диалога с NPC — реплики и ветвление по кнопкам (DDUI push).
 */
final class DduiNpcDialogForm implements DduiForm{
	private const SLOT_BODY = 1;
	private const SLOT_LOG1 = 3;
	private const SLOT_LOG2 = 4;
	private const SLOT_BTN0 = 6;
	private const SLOT_BTN1 = 7;
	private const SLOT_BTN2 = 8;

	/** @var list<string> */
	private array $history = [];

	private string $nodeId = "intro";

	public function getScreenId() : string{
		return DduiScreens::CUSTOM_FORM;
	}

	public function getRootPropertyName() : string{
		return DduiProperties::CUSTOM_FORM_ROOT;
	}

	public function buildInitialValue() : DynamicValue{
		$builder = (new DduiLayoutBuilder())
			->addHeader("Странник Элдрик")
			->addLabel($this->nodeLine("intro"))
			->addSpacer()
			->addLabel("…")
			->addLabel(" ")
			->addDivider();
		foreach($this->nodeOptions("intro") as $label){
			$builder->addButton($label);
		}
		return $builder->withCloseButton("Уйти")->buildCustomFormRoot("Диалог");
	}

	public function handleClientUpdate(Player $player, DduiSession $session, DataStoreUpdate $update) : void{
		if(DduiPathParser::isCloseButtonClick($update->getPath())){
			$this->confirmLeave($player);
			return;
		}
		$slot = DduiPathParser::resolveCustomFormButtonSlot($update->getPath());
		if($slot === null || $slot < self::SLOT_BTN0 || $slot > self::SLOT_BTN2){
			return;
		}
		$choiceIndex = $slot - self::SLOT_BTN0;
		$this->choose($player, $session, $choiceIndex);
	}

	public function handleClose(Player $player, DduiSession $session, ?string $closeReason) : void{
		$player->sendMessage(TextFormat::GRAY . "Диалог с Элдриком завершён.");
		DduiTestMenu::returnToHubLater($player);
	}

	private function choose(Player $player, DduiSession $session, int $choiceIndex) : void{
		$options = $this->nodeOptions($this->nodeId);
		if(!isset($options[$choiceIndex])){
			return;
		}
		$label = $options[$choiceIndex];
		$this->history[] = TextFormat::DARK_GRAY . "Ты: " . TextFormat::RESET . $label;
		$next = $this->transition($this->nodeId, $choiceIndex);
		if($next === null){
			$player->sendMessage(TextFormat::YELLOW . "Элдрик: До встречи!");
			$player->getNetworkSession()->getDduiSessionManager()->close($session->getFormId(), true);
			return;
		}
		$this->nodeId = $next;
		$this->history[] = TextFormat::GRAY . "Элдрик: " . TextFormat::WHITE . $this->nodeLine($this->nodeId);
		$this->pushDialogUi($player, $session);
	}

	private function pushDialogUi(Player $player, DduiSession $session) : void{
		$mgr = $player->getNetworkSession()->getDduiSessionManager();
		$log = $this->formatLog();
		$updates = [
			DduiClientUpdates::setString($session, "layout." . self::SLOT_BODY . ".text", $this->nodeLine($this->nodeId)),
			DduiClientUpdates::setString($session, "layout." . self::SLOT_LOG1 . ".text", $log[0] ?? " "),
			DduiClientUpdates::setString($session, "layout." . self::SLOT_LOG2 . ".text", $log[1] ?? " "),
		];
		$options = $this->nodeOptions($this->nodeId);
		for($i = 0; $i < 3; $i++){
			$slot = self::SLOT_BTN0 + $i;
			$visible = isset($options[$i]);
			$updates[] = DduiClientUpdates::setString($session, "layout." . $slot . ".label", $options[$i] ?? "—");
			$updates[] = DduiClientUpdates::setBool($session, "layout." . $slot . ".button_visible", $visible);
			$updates[] = DduiClientUpdates::forSession($session, "layout." . $slot . ".disabled", new BoolDataStoreUpdateValue(!$visible));
		}
		$mgr->pushClientUpdates($updates);
	}

	/**
	 * @return list<string>
	 */
	private function formatLog() : array{
		$tail = array_slice($this->history, -2);
		return [
			$tail[0] ?? " ",
			$tail[1] ?? " ",
		];
	}

	private function confirmLeave(Player $player) : void{
		$box = new DduiMessageBoxForm(
			"Завершить диалог?",
			"Элдрик ещё хотел кое-что рассказать.",
			"Уйти",
			"Остаться",
			static function(Player $p, ?bool $choice) : void{
				if($choice === true){
					$p->getNetworkSession()->getDduiSessionManager()->closeAll();
					DduiTestMenu::returnToHubLater($p);
					return;
				}
				DduiTestMenu::openNpcDialog($p);
			}
		);
		$player->sendDduiForm($box);
	}

	private function nodeLine(string $nodeId) : string{
		return match($nodeId){
			"intro" => "Привет, путник. Рубиновая сеть шумит — нужна помощь?",
			"quest" => "На востоке замечены зомби. Принеси доклад, и я открою склад.",
			"trade" => "Торговая лавка скоро откроется. Пока могу только совет дать.",
			"quest_accept" => "Отлично! Вернись, когда будет тишина в лесу.",
			default => "…",
		};
	}

	/**
	 * @return list<string>
	 */
	private function nodeOptions(string $nodeId) : array{
		return match($nodeId){
			"intro" => ["Про задание", "Про торговлю", "До встречи"],
			"quest" => ["Берусь", "Пока рано", "Назад"],
			"trade" => ["Понятно", "Назад"],
			"quest_accept" => ["Спасибо"],
			default => [],
		};
	}

	private function transition(string $nodeId, int $choiceIndex) : ?string{
		return match($nodeId){
			"intro" => match($choiceIndex){
				0 => "quest",
				1 => "trade",
				default => null,
			},
			"quest" => match($choiceIndex){
				0 => "quest_accept",
				1 => "intro",
				default => "intro",
			},
			"trade" => match($choiceIndex){
				0 => "intro",
				1 => "intro",
				default => "intro",
			},
			"quest_accept" => null,
			default => null,
		};
	}
}

