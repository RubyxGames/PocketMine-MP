<?php

declare(strict_types=1);

namespace pocketmine\command\defaults;

use pocketmine\command\CommandSender;
use pocketmine\form\ddui\DduiTestMenu;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class DduiCommand extends VanillaCommand{

	public function __construct(){
		parent::__construct(
			"ddui",
			"Open the DDUI test hub (MessageBox + CustomForm)",
			"/ddui"
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_DDUI);
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		if(!$sender instanceof Player){
			$sender->sendMessage(TextFormat::RED . "Run this command in-game.");
			return true;
		}

		DduiTestMenu::send($sender);
		return true;
	}
}
