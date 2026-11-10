<?php

declare(strict_types=1);

namespace pocketmine\command\defaults;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\utils\TextFormat;
use function count;
use function implode;
use function strtolower;

class SessionRecordCommand extends VanillaCommand{

	public function __construct(){
		parent::__construct(
			"sessionrecord",
			"Start or stop JSONL session recording for a player",
			"/sessionrecord <start|stop|status> [player]"
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_SESSIONRECORD);
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		if(count($args) < 1){
			throw new InvalidCommandSyntaxException();
		}

		$manager = $sender->getServer()->getSessionRecordingManager();
		if($manager === null || !$manager->isEnabled()){
			$sender->sendMessage(TextFormat::RED . "Session recording is disabled in pocketmine.yml (session-recording.enabled).");
			return true;
		}

		$action = strtolower($args[0]);
		if($action === "status"){
			$names = $manager->getRecordingPlayerNames();
			$sender->sendMessage(TextFormat::GREEN . "Recording: " . (count($names) === 0 ? "(none)" : implode(", ", $names)));
			return true;
		}

		if(count($args) < 2 || ($action !== "start" && $action !== "stop")){
			throw new InvalidCommandSyntaxException();
		}

		$player = $sender->getServer()->getPlayerByPrefix($args[1]);
		if($player === null){
			$sender->sendMessage(TextFormat::RED . "Player not found: " . $args[1]);
			return true;
		}

		if($action === "start"){
			if($manager->start($player, "command")){
				Command::broadcastCommandMessage($sender, "Started session recording for " . $player->getName());
			}else{
				$sender->sendMessage(TextFormat::RED . "Already recording or failed to start for " . $player->getName());
			}
			return true;
		}

		if($manager->stop($player, "command")){
			Command::broadcastCommandMessage($sender, "Stopped session recording for " . $player->getName());
		}else{
			$sender->sendMessage(TextFormat::RED . "Not recording " . $player->getName());
		}
		return true;
	}
}
