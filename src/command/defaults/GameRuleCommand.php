<?php

declare(strict_types=1);

namespace pocketmine\command\defaults;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\world\multiworld\gamerule\GameRuleDef;
use pocketmine\world\multiworld\gamerule\WorldGameRules;
use function array_map;
use function count;
use function gettype;
use function implode;
use function is_bool;
use function is_int;
use function json_decode;
use function strtolower;

/**
 * Per-world gamerules (MultiWorld module).
 */
class GameRuleCommand extends VanillaCommand{

	public function __construct(){
		parent::__construct(
			"gamerule",
			"Edit per-world gamerules",
			"/gamerule <list|rule> [value] [world]"
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_GAMERULE);
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		if(count($args) < 1){
			throw new InvalidCommandSyntaxException();
		}

		if(strtolower($args[0]) === "list"){
			$names = array_map(static fn(GameRuleDef $d) => $d->getName(), GameRuleDef::all());
			$sender->sendMessage(TextFormat::GREEN . "Gamerules: " . implode(", ", $names));
			$sender->sendMessage(TextFormat::GRAY . "Enforced server-side: keepInventory, doTileDrops, naturalRegeneration, pvp, tntExplodes");
			return true;
		}

		if(count($args) < 2){
			throw new InvalidCommandSyntaxException();
		}

		$def = GameRuleDef::get($args[0]);
		if($def === null){
			$sender->sendMessage(TextFormat::RED . "Unknown gamerule \"" . $args[0] . "\". Use /gamerule list");
			return true;
		}

		$value = json_decode($args[1]);
		$okType = ($def->getType() === GameRuleDef::TYPE_BOOL && is_bool($value))
			|| ($def->getType() === GameRuleDef::TYPE_INT && is_int($value));
		if(!$okType){
			$sender->sendMessage(TextFormat::RED . "Invalid value type for " . $def->getName() . " (got " . gettype($value) . "). Use JSON: true/false or number.");
			return true;
		}

		$wm = $sender->getServer()->getWorldManager();
		if(isset($args[2])){
			$worldName = $args[2];
			if(!$wm->isWorldGenerated($worldName)){
				$sender->sendMessage(TextFormat::RED . "World \"$worldName\" not found.");
				return true;
			}
			if(!$wm->isWorldLoaded($worldName) && !$wm->loadWorld($worldName)){
				$sender->sendMessage(TextFormat::RED . "Failed to load \"$worldName\".");
				return true;
			}
			$world = $wm->getWorldByName($worldName);
		}elseif($sender instanceof Player){
			$world = $sender->getWorld();
		}else{
			$sender->sendMessage(TextFormat::RED . "Specify a world when running from console.");
			return true;
		}

		if($world === null){
			$sender->sendMessage(TextFormat::RED . "World not available.");
			return true;
		}

		/** @var bool|int $value */
		$rules = WorldGameRules::for($world);
		$rules->set($def->getName(), $value);
		$rules->save($world);
		Command::broadcastCommandMessage($sender, "Set gamerule " . $def->getName() . "=" . $args[1] . " for world \"" . $world->getFolderName() . "\"");
		return true;
	}
}
