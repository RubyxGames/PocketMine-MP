<?php

declare(strict_types=1);

namespace pocketmine\world\multiworld;

use pocketmine\form\CustomForm;
use pocketmine\form\SimpleForm;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\world\WorldCreationOptions;
use pocketmine\world\WorldException;
use function count;
use function is_array;
use function is_numeric;
use function is_string;
use function mt_rand;
use function trim;

/**
 * In-game manage UI for MultiWorld (core FormAPI).
 */
final class MultiWorldForms{

	private function __construct(){}

	public static function openManage(Player $player) : void{
		$form = new SimpleForm(static function(Player $player, mixed $data) : void{
			if(!is_numeric($data)){
				return;
			}
			match((int) $data){
				0 => self::openCreate($player),
				1 => self::openDelete($player),
				2 => self::openTeleportSelf($player),
				3 => self::openLoadUnload($player, true),
				4 => self::openLoadUnload($player, false),
				5 => self::openInfo($player),
				default => null
			};
		});
		$form->setTitle("MultiWorld");
		$form->setContent("Manage worlds");
		$form->addButton("Create");
		$form->addButton("Delete");
		$form->addButton("Teleport");
		$form->addButton("Load");
		$form->addButton("Unload");
		$form->addButton("Info");
		$player->sendForm($form);
	}

	private static function openCreate(Player $player) : void{
		$form = new CustomForm(static function(Player $player, mixed $data) : void{
			if(!is_array($data)){
				return;
			}
			$name = trim((string) ($data[0] ?? ""));
			$seedRaw = trim((string) ($data[1] ?? ""));
			$gen = trim((string) ($data[2] ?? "normal"));
			if($name === ""){
				$player->sendMessage(TextFormat::RED . "World name required.");
				return;
			}
			$wm = Server::getInstance()->getWorldManager();
			if($wm->isWorldGenerated($name)){
				$player->sendMessage(TextFormat::RED . "World \"$name\" already exists.");
				return;
			}
			$entry = MultiWorldModule::resolveGenerator($gen);
			if($entry === null){
				$player->sendMessage(TextFormat::RED . "Unknown generator \"$gen\". Use: normal, flat, nether, void");
				return;
			}
			$seed = is_numeric($seedRaw) ? (int) $seedRaw : mt_rand();
			$ok = $wm->generateWorld($name, WorldCreationOptions::create()
				->setSeed($seed)
				->setGeneratorClass($entry->getGeneratorClass()));
			$player->sendMessage($ok
				? TextFormat::GREEN . "Created world \"$name\" (seed $seed, gen $gen)"
				: TextFormat::RED . "Failed to create world \"$name\".");
		});
		$form->setTitle("Create world");
		$form->addInput("Name", "arena1");
		$form->addInput("Seed (optional)", "random");
		$form->addInput("Generator", "normal", "normal");
		$player->sendForm($form);
	}

	private static function openDelete(Player $player) : void{
		$worlds = Server::getInstance()->getWorldManager()->getWorldFolderNames();
		if(count($worlds) === 0){
			$player->sendMessage(TextFormat::RED . "No worlds on disk.");
			return;
		}
		$form = new SimpleForm(static function(Player $player, mixed $data) use ($worlds) : void{
			if(!is_numeric($data)){
				return;
			}
			$idx = (int) $data;
			$name = $worlds[$idx] ?? null;
			if(!is_string($name)){
				return;
			}
			try{
				Server::getInstance()->getWorldManager()->deleteWorld($name);
				$player->sendMessage(TextFormat::GREEN . "Deleted world \"$name\".");
			}catch(\InvalidArgumentException | WorldException $e){
				$player->sendMessage(TextFormat::RED . $e->getMessage());
			}
		});
		$form->setTitle("Delete world");
		$form->setContent(TextFormat::RED . "This permanently deletes the world folder.");
		foreach($worlds as $w){
			$form->addButton($w);
		}
		$player->sendForm($form);
	}

	private static function openTeleportSelf(Player $player) : void{
		$worlds = Server::getInstance()->getWorldManager()->getWorldFolderNames();
		$form = new SimpleForm(static function(Player $player, mixed $data) use ($worlds) : void{
			if(!is_numeric($data)){
				return;
			}
			$name = $worlds[(int) $data] ?? null;
			if(!is_string($name)){
				return;
			}
			$wm = Server::getInstance()->getWorldManager();
			if(!$wm->isWorldLoaded($name) && !$wm->loadWorld($name)){
				$player->sendMessage(TextFormat::RED . "Failed to load \"$name\".");
				return;
			}
			$world = $wm->getWorldByName($name);
			if($world === null){
				$player->sendMessage(TextFormat::RED . "World \"$name\" not found.");
				return;
			}
			try{
				$player->teleport($world->getSafeSpawn());
				$player->sendMessage(TextFormat::GREEN . "Teleported to \"$name\".");
			}catch(WorldException $e){
				$player->sendMessage(TextFormat::RED . $e->getMessage());
			}
		});
		$form->setTitle("Teleport");
		foreach($worlds as $w){
			$form->addButton($w);
		}
		$player->sendForm($form);
	}

	private static function openLoadUnload(Player $player, bool $load) : void{
		$wm = Server::getInstance()->getWorldManager();
		$worlds = $wm->getWorldFolderNames();
		$form = new SimpleForm(static function(Player $player, mixed $data) use ($worlds, $load, $wm) : void{
			if(!is_numeric($data)){
				return;
			}
			$name = $worlds[(int) $data] ?? null;
			if(!is_string($name)){
				return;
			}
			if($load){
				if($wm->isWorldLoaded($name)){
					$player->sendMessage(TextFormat::YELLOW . "Already loaded.");
					return;
				}
				$player->sendMessage($wm->loadWorld($name)
					? TextFormat::GREEN . "Loaded \"$name\"."
					: TextFormat::RED . "Failed to load \"$name\".");
				return;
			}
			$world = $wm->getWorldByName($name);
			if($world === null){
				$player->sendMessage(TextFormat::YELLOW . "Not loaded.");
				return;
			}
			if($world === $wm->getDefaultWorld()){
				$player->sendMessage(TextFormat::RED . "Cannot unload the default world.");
				return;
			}
			try{
				$player->sendMessage($wm->unloadWorld($world)
					? TextFormat::GREEN . "Unloaded \"$name\"."
					: TextFormat::RED . "Failed to unload \"$name\".");
			}catch(\InvalidArgumentException $e){
				$player->sendMessage(TextFormat::RED . $e->getMessage());
			}
		});
		$form->setTitle($load ? "Load world" : "Unload world");
		foreach($worlds as $w){
			$form->addButton($w);
		}
		$player->sendForm($form);
	}

	private static function openInfo(Player $player) : void{
		$worlds = Server::getInstance()->getWorldManager()->getWorldFolderNames();
		$form = new SimpleForm(static function(Player $player, mixed $data) use ($worlds) : void{
			if(!is_numeric($data)){
				return;
			}
			$name = $worlds[(int) $data] ?? null;
			if(!is_string($name)){
				return;
			}
			$player->getServer()->dispatchCommand($player, "world info " . $name);
		});
		$form->setTitle("World info");
		foreach($worlds as $w){
			$form->addButton($w);
		}
		$player->sendForm($form);
	}
}
