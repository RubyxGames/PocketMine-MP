<?php

declare(strict_types=1);

namespace pocketmine\form\ddui;

use pocketmine\network\mcpe\protocol\types\cereal\DynamicValue;
use pocketmine\network\mcpe\protocol\types\ddui\DataStoreUpdate;
use pocketmine\network\mcpe\protocol\types\ddui\update\BoolDataStoreUpdateValue;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskHandler;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use function count;
use function sprintf;

/**
 * Live-updating CustomForm (MS DDUI intro Example 3 pattern).
 */
final class DduiCustomFormReactiveDemo implements DduiForm, DduiFormOnOpen{
	private const SLOT_STATUS = 0;
	private const SLOT_ACTION = 2;
	private const SLOT_MESSAGE = 4;
	private const SLOT_LOCK = 5;

	private ?TaskHandler $pulseTask = null;

	private int $pulseCount = 0;

	private int $actionCount = 0;

	private bool $lockButton = false;

	public function getScreenId() : string{
		return DduiScreens::CUSTOM_FORM;
	}

	public function getRootPropertyName() : string{
		return DduiProperties::CUSTOM_FORM_ROOT;
	}

	public function buildInitialValue() : DynamicValue{
		return (new DduiLayoutBuilder())
			->addHeader("Reactive DDUI")
			->addLabel("Scanning...")
			->addSpacer()
			->addButton("Ping", "Server-side counter")
			->addDivider()
			->addLabel("Press Ping to update this line")
			->addToggle("Lock Ping button", false, "Disables the Ping control via push updates")
			->withCloseButton()
			->buildCustomFormRoot("DDUI CustomForm (reactive)");
	}

	public function onDduiOpen(Player $player, DduiSession $session) : void{
		$this->pulseCount = 0;
		$mgr = $player->getNetworkSession()->getDduiSessionManager();
		$sessionId = $session->getFormId();
		$this->pulseTask = Server::getInstance()->getCorePluginScheduler()->scheduleRepeatingTask(new ClosureTask(function() use ($player, $mgr, $sessionId) : void{
			$active = $mgr->getActiveSession();
			if($active === null || $active->getFormId() !== $sessionId){
				$this->cancelPulse();
				return;
			}
			$this->pulseCount++;
			$entityCount = count($player->getWorld()->getEntities());
			$mgr->pushClientUpdates([
				DduiClientUpdates::setString($active, "layout." . self::SLOT_STATUS . ".text", sprintf(
					"Pulse #%d — %d entities in world",
					$this->pulseCount,
					$entityCount
				)),
			]);
		}), 40);
	}

	public function handleClientUpdate(Player $player, DduiSession $session, DataStoreUpdate $update) : void{
		$path = $update->getPath();
		if(DduiPathParser::isCloseButtonClick($path)){
			$player->getNetworkSession()->getDduiSessionManager()->close($session->getFormId(), true);
			return;
		}
		$slot = DduiPathParser::resolveCustomFormButtonSlot($path);
		if($slot === self::SLOT_ACTION){
			if($this->lockButton){
				$player->sendMessage(TextFormat::YELLOW . "Ping is locked");
				return;
			}
			$this->actionCount++;
			$mgr = $player->getNetworkSession()->getDduiSessionManager();
			$mgr->pushClientUpdates([
				DduiClientUpdates::setString($session, "layout." . self::SLOT_MESSAGE . ".text", "Ping count: " . $this->actionCount),
			]);
			$player->sendMessage(TextFormat::GREEN . "Reactive DDUI: ping " . $this->actionCount);
			return;
		}
		if(DduiPathParser::isLayoutTogglePath($path, self::SLOT_LOCK)){
			$data = $update->getData();
			if($data instanceof BoolDataStoreUpdateValue){
				$this->lockButton = $data->getValue();
				$mgr = $player->getNetworkSession()->getDduiSessionManager();
				$mgr->pushClientUpdates([
					DduiClientUpdates::forSession($session, "layout." . self::SLOT_ACTION . ".disabled", new BoolDataStoreUpdateValue($this->lockButton)),
				]);
			}
		}
	}

	public function handleClose(Player $player, DduiSession $session, ?string $closeReason) : void{
		$this->cancelPulse();
		$player->sendMessage(TextFormat::GRAY . "Reactive form closed" . ($closeReason !== null && $closeReason !== "" ? " ($closeReason)" : ""));
		DduiTestMenu::returnToHubLater($player);
	}

	private function cancelPulse() : void{
		if($this->pulseTask !== null){
			$this->pulseTask->cancel();
			$this->pulseTask = null;
		}
	}
}
