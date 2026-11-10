<?php

declare(strict_types=1);

namespace pocketmine\form\ddui;

use pocketmine\form\SimpleForm;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use function sprintf;

/**
 * DDUI test flows ({@see DduiCommand}).
 *
 * The picker menu uses JSON SimpleForm only as a reliable button list; every entry opens a DDUI screen.
 */
final class DduiTestMenu{
	private function __construct(){
	}

	public static function returnToHubLater(Player $player) : void{
		$delay = max(1, DduiSettings::getReplaceDelayTicks());
		$target = $player;
		Server::getInstance()->getCorePluginScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($target) : void{
			if(!$target->isConnected()){
				return;
			}
			self::send($target);
		}), $delay);
	}

	public static function send(Player $player) : void{
		$dduiOn = DduiSupport::playerSupports($player);
		$protocol = $player->getNetworkSession()->getProtocolId();

		$status = sprintf(
			"Protocol: %d (server %d)%s%sDDUI: %s%s%sTests: message_box, custom_form, chat/dialog demos",
			$protocol,
			ProtocolInfo::CURRENT_PROTOCOL,
			TextFormat::EOL,
			TextFormat::EOL,
			$dduiOn ? TextFormat::GREEN . "ready" : TextFormat::RED . "unavailable",
			TextFormat::EOL,
			TextFormat::EOL
		);

		$form = new SimpleForm(function(Player $p, $data) use ($dduiOn) : void{
			if($data === null){
				return;
			}
			if(!$dduiOn){
				$p->sendMessage(TextFormat::RED . "DDUI is disabled or unsupported on this client.");
				return;
			}
			match((int) $data){
				0 => self::openMessageBox($p),
				1 => self::openMessageBoxLong($p),
				2 => self::openCustomFormFull($p),
				3 => self::openCustomFormMinimal($p),
				4 => self::openDduiHubScreen($p),
				5 => self::openCustomFormReactive($p),
				6 => self::openNpcDialog($p),
				7 => self::openChatWindow($p),
				8 => self::openThemeDemo($p),
				9 => self::openPostSystemScreenSpike($p),
				default => null,
			};
		});
		$form->setTitle("DDUI tests");
		$form->setContent($status . "Choose a screen:");
		$form->addButton("minecraft:message_box");
		$form->addButton("minecraft:message_box (long + tooltips)");
		$form->addButton("minecraft:custom_form (all controls)");
		$form->addButton("minecraft:custom_form (minimal)");
		$form->addButton("minecraft:custom_form (DDUI hub layout)");
		$form->addButton("minecraft:custom_form (reactive live)");
		$form->addButton("Диалог с NPC (ветвление)");
		$form->addButton("MineChat DDUI prototype");
		$form->addButton("DDUI theme presets (RP styling)");
		$form->addButton("minechat:postsystem_chat (RP spike)");
		$player->sendForm($form);
	}

	public static function openMessageBox(Player $player) : void{
		$box = new DduiMessageBoxForm(
			"DDUI MessageBox",
			"Tap a button. Result is echoed in chat.",
			"Confirm",
			"Cancel",
			static function(Player $p, ?bool $choice) : void{
				$p->sendMessage(match($choice){
					true => TextFormat::GREEN . "MessageBox: Confirm",
					false => TextFormat::YELLOW . "MessageBox: Cancel",
					default => TextFormat::GRAY . "MessageBox: dismissed",
				});
				self::returnToHubLater($p);
			}
		);
		$player->sendDduiForm($box);
	}

	public static function openMessageBoxLong(Player $player) : void{
		$body = "Lorem ipsum dolor sit amet, consectetur adipiscing elit. "
			. "DDUI should render multi-line body text.";
		$box = (new DduiMessageBoxForm(
			"Long body test",
			$body,
			"Got it",
			"Close",
			static function(Player $p, ?bool $choice) : void{
				$p->sendMessage("Long MessageBox choice: " . ($choice === null ? "null" : ($choice ? "true" : "false")));
				self::returnToHubLater($p);
			}
		))
			->setButton1("Got it", "Acknowledge")
			->setButton2("Close", "Dismiss dialog");
		$player->sendDduiForm($box);
	}

	public static function openCustomFormFull(Player $player) : void{
		$player->sendDduiForm(new DduiCustomFormDemo());
	}

	public static function openCustomFormMinimal(Player $player) : void{
		$player->sendDduiForm(new DduiCustomFormTest());
	}

	public static function openDduiHubScreen(Player $player) : void{
		$player->sendDduiForm(DduiTestHubForm::forPlayer($player));
	}

	public static function openCustomFormReactive(Player $player) : void{
		$player->sendDduiForm(new DduiCustomFormReactiveDemo());
	}

	public static function openNpcDialog(Player $player) : void{
		$player->sendDduiForm(new DduiNpcDialogForm());
	}

	public static function openChatWindow(Player $player) : void{
		$player->sendDduiForm(new DduiPostSystemChatForm());
	}

	public static function openThemeDemo(Player $player) : void{
		$player->sendDduiForm(new DduiThemeDemoForm(DduiThemeRegistry::getDefaultAlias()));
	}

	public static function openPostSystemScreenSpike(Player $player) : void{
		$player->sendDduiForm(new DduiPostSystemChatSpikeForm());
	}
}
