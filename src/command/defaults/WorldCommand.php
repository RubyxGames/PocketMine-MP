<?php

declare(strict_types=1);

namespace pocketmine\command\defaults;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\math\Vector3;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\world\format\io\region\AnvilWorldExporter;
use pocketmine\world\multiworld\MultiWorldForms;
use pocketmine\world\multiworld\MultiWorldModule;
use pocketmine\world\multiworld\WorldDiskSize;
use pocketmine\world\World;
use pocketmine\world\WorldCreationOptions;
use pocketmine\world\WorldException;
use Symfony\Component\Filesystem\Path;
use function array_slice;
use function ceil;
use function count;
use function implode;
use function is_numeric;
use function max;
use function mt_rand;
use function sort;
use function strtolower;

/**
 * MultiWorld-style world management (core module).
 * Aliases: /mw, /multiworld
 */
class WorldCommand extends VanillaCommand{

	public function __construct(){
		parent::__construct(
			"world",
			"Manage worlds (MultiWorld)",
			"/world <list|create|delete|rename|load|unload|tp|clone|duplicate|info|update|manage|export-anvil|help> ...",
			["mw", "multiworld"]
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_WORLD);
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		if(count($args) < 1){
			throw new InvalidCommandSyntaxException();
		}

		$wm = $sender->getServer()->getWorldManager();
		$action = strtolower($args[0]);

		switch($action){
			case "list":
			case "ls":
				$names = $wm->getWorldFolderNames();
				sort($names);
				$lines = [];
				foreach($names as $name){
					$size = WorldDiskSize::formatDirectory($wm->getWorldPath($name));
					$world = $wm->getWorldByName($name);
					if($world !== null){
						$lines[] = TextFormat::GRAY . $name . " > " . TextFormat::GREEN . "Loaded" . TextFormat::GRAY . ", " .
							count($world->getPlayers()) . " Players, " . TextFormat::WHITE . $size;
					}else{
						$lines[] = TextFormat::GRAY . $name . " > " . TextFormat::RED . "Unloaded" . TextFormat::GRAY . ", " .
							TextFormat::WHITE . $size;
					}
				}
				$header = TextFormat::GREEN . "Worlds (" . count($names) . "):";
				$sender->sendMessage($header . (count($lines) === 0 ? "\n" . TextFormat::GRAY . "(none)" : "\n" . implode("\n", $lines)));
				return true;

			case "create":
			case "generate":
			case "new":
				if(count($args) < 2){
					throw new InvalidCommandSyntaxException();
				}
				$name = $args[1];
				if($wm->isWorldGenerated($name)){
					$sender->sendMessage(TextFormat::RED . "World \"$name\" already exists.");
					return true;
				}
				$seed = (isset($args[2]) && is_numeric($args[2])) ? (int) $args[2] : mt_rand();
				$genName = $args[3] ?? "normal";
				$entry = MultiWorldModule::resolveGenerator($genName);
				if($entry === null){
					$sender->sendMessage(TextFormat::RED . "Unknown generator \"$genName\". Available: normal, flat, nether, void, skyblock");
					return true;
				}
				$ok = $wm->generateWorld($name, WorldCreationOptions::create()
					->setSeed($seed)
					->setGeneratorClass($entry->getGeneratorClass()));
				if($ok){
					Command::broadcastCommandMessage($sender, "Created world \"$name\" (seed $seed, generator $genName)");
				}else{
					$sender->sendMessage(TextFormat::RED . "Failed to create world \"$name\".");
				}
				return true;

			case "delete":
			case "remove":
			case "rm":
			case "del":
				if(count($args) < 2){
					throw new InvalidCommandSyntaxException();
				}
				try{
					$wm->deleteWorld($args[1]);
					Command::broadcastCommandMessage($sender, "Deleted world \"" . $args[1] . "\"");
				}catch(\InvalidArgumentException | WorldException $e){
					$sender->sendMessage(TextFormat::RED . $e->getMessage());
				}
				return true;

			case "rename":
			case "rnm":
			case "re":
				if(count($args) < 3){
					throw new InvalidCommandSyntaxException();
				}
				try{
					$wm->renameWorld($args[1], $args[2]);
					Command::broadcastCommandMessage($sender, "Renamed world \"" . $args[1] . "\" -> \"" . $args[2] . "\"");
				}catch(\InvalidArgumentException | WorldException $e){
					$sender->sendMessage(TextFormat::RED . $e->getMessage());
				}
				return true;

			case "load":
			case "ld":
				if(count($args) < 2){
					throw new InvalidCommandSyntaxException();
				}
				$name = $args[1];
				if($wm->isWorldLoaded($name)){
					$sender->sendMessage(TextFormat::YELLOW . "World \"$name\" is already loaded.");
					return true;
				}
				if(!$wm->isWorldGenerated($name)){
					$sender->sendMessage(TextFormat::RED . "World \"$name\" does not exist on disk.");
					return true;
				}
				try{
					if($wm->loadWorld($name)){
						Command::broadcastCommandMessage($sender, "Loaded world \"$name\"");
					}else{
						$sender->sendMessage(TextFormat::RED . "Failed to load world \"$name\".");
					}
				}catch(WorldException $e){
					$sender->sendMessage(TextFormat::RED . "Failed to load world \"$name\": " . $e->getMessage());
				}
				return true;

			case "unload":
			case "unld":
				if(count($args) < 2){
					throw new InvalidCommandSyntaxException();
				}
				$name = $args[1];
				$world = $wm->getWorldByName($name);
				if($world === null){
					$sender->sendMessage(TextFormat::RED . "World \"$name\" is not loaded.");
					return true;
				}
				if($world === $wm->getDefaultWorld()){
					$sender->sendMessage(TextFormat::RED . "Cannot unload the default world.");
					return true;
				}
				try{
					if($wm->unloadWorld($world)){
						Command::broadcastCommandMessage($sender, "Unloaded world \"$name\"");
					}else{
						$sender->sendMessage(TextFormat::RED . "Failed to unload world \"$name\".");
					}
				}catch(\InvalidArgumentException $e){
					$sender->sendMessage(TextFormat::RED . $e->getMessage());
				}
				return true;

			case "tp":
			case "teleport":
			case "move":
				if(count($args) < 2){
					throw new InvalidCommandSyntaxException();
				}
				$worldName = $args[1];
				$world = $wm->getWorldByName($worldName);
				if($world === null){
					if(!$wm->isWorldGenerated($worldName)){
						$sender->sendMessage(TextFormat::RED . "World \"$worldName\" does not exist.");
						return true;
					}
					if(!$wm->loadWorld($worldName)){
						$sender->sendMessage(TextFormat::RED . "Failed to load world \"$worldName\".");
						return true;
					}
					$world = $wm->getWorldByName($worldName);
					if($world === null){
						$sender->sendMessage(TextFormat::RED . "World \"$worldName\" failed to load.");
						return true;
					}
				}

				$target = null;
				if(isset($args[2])){
					$target = $sender->getServer()->getPlayerByPrefix($args[2]);
					if($target === null){
						$sender->sendMessage(TextFormat::RED . "Player not found: " . $args[2]);
						return true;
					}
				}elseif($sender instanceof Player){
					$target = $sender;
				}else{
					$sender->sendMessage(TextFormat::RED . "Specify a player when running from console.");
					return true;
				}

				try{
					$target->teleport($world->getSafeSpawn());
				}catch(WorldException $e){
					$sender->sendMessage(TextFormat::RED . "Cannot teleport: " . $e->getMessage());
					return true;
				}
				Command::broadcastCommandMessage($sender, "Teleported " . $target->getName() . " to world \"" . $world->getFolderName() . "\"");
				return true;

			case "clone":
			case "duplicate":
			case "copy":
			case "cp":
				if(count($args) < 2){
					throw new InvalidCommandSyntaxException();
				}
				$src = $args[1];
				$dest = $args[2] ?? ($src . "_copy");
				try{
					$cloned = $wm->cloneWorld($src, $dest, true);
					Command::broadcastCommandMessage($sender, "Cloned world \"$src\" -> \"" . ($cloned?->getFolderName() ?? $dest) . "\"");
				}catch(\InvalidArgumentException | WorldException $e){
					$sender->sendMessage(TextFormat::RED . $e->getMessage());
				}
				return true;

			case "info":
			case "i":
				if(count($args) < 2){
					if($sender instanceof Player){
						$name = $sender->getWorld()->getFolderName();
					}else{
						throw new InvalidCommandSyntaxException();
					}
				}else{
					$name = $args[1];
				}
				$world = $wm->getWorldByName($name);
				$onDisk = $wm->isWorldGenerated($name);
				if($world === null && !$onDisk){
					$sender->sendMessage(TextFormat::RED . "World \"$name\" not found.");
					return true;
				}
				$sender->sendMessage(TextFormat::GREEN . "=== World: $name ===");
				$sender->sendMessage("Loaded: " . ($world !== null ? "yes" : "no"));
				$sender->sendMessage("On disk: " . ($onDisk ? "yes" : "no"));
				if($world !== null){
					$sender->sendMessage("Display name: " . $world->getDisplayName());
					$sender->sendMessage("Players: " . count($world->getPlayers()));
					$sender->sendMessage("Seed: " . $world->getSeed());
					$sender->sendMessage("Generator: " . $world->getProvider()->getWorldData()->getGenerator());
					$spawn = $world->getSpawnLocation();
					$sender->sendMessage("Spawn: " . $spawn->getFloorX() . ", " . $spawn->getFloorY() . ", " . $spawn->getFloorZ());
					$sender->sendMessage("Time: " . $world->getTime());
					$sender->sendMessage("Default: " . ($wm->getDefaultWorld() === $world ? "yes" : "no"));
				}else{
					$sender->sendMessage(TextFormat::GRAY . "Load the world for generator/seed/players details.");
				}
				return true;

			case "update":
			case "ue":
				if(count($args) < 2){
					throw new InvalidCommandSyntaxException();
				}
				$sub = strtolower($args[1]);
				switch($sub){
					case "spawn":
						if(count($args) === 2 && $sender instanceof Player){
							$sender->getWorld()->setSpawnLocation($sender->getPosition()->asVector3());
							Command::broadcastCommandMessage($sender, "Updated spawn of \"" . $sender->getWorld()->getFolderName() . "\"");
							return true;
						}
						if(count($args) < 6 || !is_numeric($args[3]) || !is_numeric($args[4]) || !is_numeric($args[5])){
							throw new InvalidCommandSyntaxException();
						}
						$wName = $args[2];
						if(!$wm->isWorldGenerated($wName)){
							$sender->sendMessage(TextFormat::RED . "World \"$wName\" does not exist.");
							return true;
						}
						if(!$wm->isWorldLoaded($wName) && !$wm->loadWorld($wName)){
							$sender->sendMessage(TextFormat::RED . "Failed to load \"$wName\".");
							return true;
						}
						$world = $wm->getWorldByName($wName);
						if($world === null){
							$sender->sendMessage(TextFormat::RED . "World \"$wName\" not found.");
							return true;
						}
						$world->setSpawnLocation(new Vector3((int) $args[3], (int) $args[4], (int) $args[5]));
						Command::broadcastCommandMessage($sender, "Updated spawn of \"$wName\"");
						return true;

					case "lobby":
					case "hub":
						if(!$sender instanceof Player){
							$sender->sendMessage(TextFormat::RED . "This subcommand is in-game only.");
							return true;
						}
						$sender->getWorld()->setSpawnLocation($sender->getPosition()->asVector3());
						$wm->setDefaultWorld($sender->getWorld());
						Command::broadcastCommandMessage($sender, "Lobby/default set to \"" . $sender->getWorld()->getFolderName() . "\"");
						return true;

					case "default":
					case "defaultlevel":
						if(count($args) < 3){
							throw new InvalidCommandSyntaxException();
						}
						$wName = $args[2];
						if(!$wm->isWorldGenerated($wName)){
							$sender->sendMessage(TextFormat::RED . "World \"$wName\" does not exist.");
							return true;
						}
						if(!$wm->isWorldLoaded($wName) && !$wm->loadWorld($wName)){
							$sender->sendMessage(TextFormat::RED . "Failed to load \"$wName\".");
							return true;
						}
						$world = $wm->getWorldByName($wName);
						if($world === null){
							$sender->sendMessage(TextFormat::RED . "World \"$wName\" not found.");
							return true;
						}
						$wm->setDefaultWorld($world);
						Command::broadcastCommandMessage($sender, "Default world set to \"$wName\"");
						return true;

					default:
						throw new InvalidCommandSyntaxException();
				}

			case "manage":
			case "mng":
			case "mg":
				if(!$sender instanceof Player){
					$sender->sendMessage(TextFormat::RED . "Manage UI is in-game only.");
					return true;
				}
				MultiWorldForms::openManage($sender);
				return true;

			case "export-anvil":
			case "exportanvil":
			case "toanvil":
				if(count($args) < 3){
					throw new InvalidCommandSyntaxException();
				}
				$srcName = $args[1];
				$destName = $args[2];
				$world = $wm->getWorldByName($srcName);
				if($world === null){
					$sender->sendMessage(TextFormat::RED . "World \"$srcName\" must be loaded to export.");
					return true;
				}
				$destPath = Path::join($sender->getServer()->getDataPath(), "worlds", $destName);
				try{
					$n = AnvilWorldExporter::export($world, $destPath);
					Command::broadcastCommandMessage($sender, "Exported \"$srcName\" → Anvil \"$destName\" ($n loaded chunks; modern blocks may become air)");
				}catch(\Throwable $e){
					$sender->sendMessage(TextFormat::RED . "Export failed: " . $e->getMessage());
				}
				return true;

			case "help":
			case "?":
				$page = 1;
				if(isset($args[1]) && is_numeric($args[1])){
					$page = (int) $args[1];
				}
				$sender->sendMessage($this->getMultiWorldHelpMessage($page));
				return true;

			default:
				throw new InvalidCommandSyntaxException();
		}
	}

	/**
	 * Plugin-style paginated help (5 lines per page), matching MultiWorld en_US formatting.
	 */
	private function getMultiWorldHelpMessage(int $page) : string{
		$entries = [
			TextFormat::DARK_GREEN . "/mw create " . TextFormat::WHITE . "Create a world",
			TextFormat::DARK_GREEN . "/mw teleport " . TextFormat::WHITE . "Teleport to a world",
			TextFormat::DARK_GREEN . "/mw list " . TextFormat::WHITE . "Displays a list of all worlds",
			TextFormat::DARK_GREEN . "/mw load " . TextFormat::WHITE . "Loads a world",
			TextFormat::DARK_GREEN . "/mw unload " . TextFormat::WHITE . "Unloads a world",
			TextFormat::DARK_GREEN . "/mw delete " . TextFormat::WHITE . "Removes a world",
			TextFormat::DARK_GREEN . "/mw info " . TextFormat::WHITE . "Displays information about a world",
			TextFormat::DARK_GREEN . "/mw gamerule " . TextFormat::WHITE . "Manages the gamerules of a level",
			TextFormat::DARK_GREEN . "/mw manage " . TextFormat::WHITE . "Displays form for managing with worlds",
			TextFormat::DARK_GREEN . "/mw rename " . TextFormat::WHITE . "Renames the world",
			TextFormat::DARK_GREEN . "/mw duplicate " . TextFormat::WHITE . "Duplicates a world",
			TextFormat::DARK_GREEN . "/mw update " . TextFormat::WHITE . "Update lobby, spawn in a world or change the default world",
			TextFormat::DARK_GREEN . "/mw export-anvil " . TextFormat::WHITE . "Export a loaded world to legacy Anvil",
		];
		$perPage = 5;
		$pages = (int) max(1, (int) ceil(count($entries) / $perPage));
		if($page < 1 || $page > $pages){
			$page = 1;
		}
		$start = ($page - 1) * $perPage;
		$slice = array_slice($entries, $start, $perPage);
		$header = TextFormat::DARK_GREEN . "--- Showing MultiWorld help page $page of $pages (/mw help <page>) ---";
		return $header . "\n" . implode("\n", $slice);
	}
}
