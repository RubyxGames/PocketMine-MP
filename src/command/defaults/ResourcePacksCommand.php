<?php

declare(strict_types=1);

namespace pocketmine\command\defaults;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\form\ddui\DduiThemeRegistry;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\utils\TextFormat;
use function count;
use function strtolower;

class ResourcePacksCommand extends VanillaCommand{

	public function __construct(){
		parent::__construct(
			"resourcepacks",
			"Reload resource packs from disk (players must reconnect to get updates)",
			"/resourcepacks reload"
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_RESOURCEPACKS);
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		if(count($args) < 1 || strtolower($args[0]) !== "reload"){
			throw new InvalidCommandSyntaxException();
		}

		$manager = $sender->getServer()->getResourcePackManager();
		$manager->reload();
		DduiThemeRegistry::reloadFromPacks($manager, $sender->getServer()->getLogger());
		Command::broadcastCommandMessage(
			$sender,
			"Reloaded resource packs (" . count($manager->getResourceStack()) . " global). Online players must reconnect to receive changes."
		);
		$sender->sendMessage(TextFormat::YELLOW . "Note: mid-session pack swap is not supported by Bedrock.");
		return true;
	}
}
