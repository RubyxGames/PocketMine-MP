<?php

declare(strict_types=1);

namespace pocketmine\command\defaults;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\utils\TextFormat;
use pocketmine\world\WorldException;
use function count;

class SpawnCommand extends VanillaCommand{

	public function __construct(){
		parent::__construct(
			"spawn",
			"Teleport to the default world spawn",
			"/spawn [player]"
		);
		$this->setPermissions([
			DefaultPermissionNames::COMMAND_SPAWN_SELF,
			DefaultPermissionNames::COMMAND_SPAWN_OTHER
		]);
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		if(count($args) > 1){
			throw new InvalidCommandSyntaxException();
		}

		$target = $this->fetchPermittedPlayerTarget(
			$sender,
			$args[0] ?? null,
			DefaultPermissionNames::COMMAND_SPAWN_SELF,
			DefaultPermissionNames::COMMAND_SPAWN_OTHER
		);
		if($target === null){
			return true;
		}

		$world = $sender->getServer()->getWorldManager()->getDefaultWorld();
		if($world === null){
			$sender->sendMessage(TextFormat::RED . "Default world is not available.");
			return true;
		}

		try{
			$spawn = $world->getSafeSpawn();
		}catch(WorldException $e){
			$sender->sendMessage(TextFormat::RED . "Cannot teleport to spawn: " . $e->getMessage());
			return true;
		}

		$target->teleport($spawn);
		Command::broadcastCommandMessage($sender, "Teleported " . $target->getName() . " to spawn");
		return true;
	}
}
