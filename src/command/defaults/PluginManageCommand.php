<?php

declare(strict_types=1);

namespace pocketmine\command\defaults;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\plugin\PluginInstaller;
use pocketmine\utils\TextFormat;
use function count;
use function implode;
use function sort;
use function strtolower;
use const SORT_STRING;

/**
 * Manage plugins: list details, soft-reload, install from HTTPS URL.
 * Alias /plm (avoids clash with /plugins|/pl).
 */
class PluginManageCommand extends VanillaCommand{

	public function __construct(){
		parent::__construct(
			"plugin",
			"Manage plugins (list, reload, install)",
			"/plugin <list|reload|install|capabilities> ...",
			["plm"]
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_PLUGIN);
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		if(count($args) < 1){
			throw new InvalidCommandSyntaxException();
		}

		$action = strtolower($args[0]);
		$server = $sender->getServer();
		$pm = $server->getPluginManager();

		switch($action){
			case "list":
				$lines = [];
				foreach($pm->getPlugins() as $plugin){
					$state = $plugin->isEnabled() ? TextFormat::GREEN . "enabled" : TextFormat::RED . "disabled";
					$lines[] = TextFormat::AQUA . $plugin->getName() . TextFormat::GRAY . " v" . $plugin->getDescription()->getVersion() . TextFormat::RESET . " ($state" . TextFormat::RESET . ")";
				}
				sort($lines, SORT_STRING);
				$sender->sendMessage(TextFormat::GREEN . "Plugins (" . count($lines) . "):");
				foreach($lines as $line){
					$sender->sendMessage(" - " . $line);
				}
				return true;

			case "capabilities":
			case "caps":
				$caps = $server->getCapabilities()->getAll();
				$sender->sendMessage(TextFormat::GREEN . "Server capabilities (" . count($caps) . "): " . (count($caps) === 0 ? "(none)" : implode(", ", $caps)));
				return true;

			case "reload":
				if(count($args) < 2){
					throw new InvalidCommandSyntaxException();
				}
				$name = $args[1];
				$plugin = $pm->getPlugin($name);
				if($plugin === null){
					$sender->sendMessage(TextFormat::RED . "Unknown plugin \"$name\".");
					return true;
				}
				try{
					if($pm->reloadPlugin($plugin)){
						Command::broadcastCommandMessage($sender, "Soft-reloaded plugin \"" . $plugin->getName() . "\" (PHP source was not reloaded)");
					}else{
						$sender->sendMessage(TextFormat::RED . "Failed to re-enable plugin \"$name\".");
					}
				}catch(\InvalidArgumentException $e){
					$sender->sendMessage(TextFormat::RED . $e->getMessage());
				}
				return true;

			case "install":
				if(count($args) < 2){
					throw new InvalidCommandSyntaxException();
				}
				$url = $args[1];
				$cfg = $server->getConfigGroup();
				if(!$cfg->getPropertyBool("plugins.install.enabled", true)){
					$sender->sendMessage(TextFormat::RED . "Plugin install is disabled (plugins.install.enabled in pocketmine.yml).");
					return true;
				}
				$maxMb = max(1, $cfg->getPropertyInt("plugins.install.max-mb", 32));
				try{
					$path = (new PluginInstaller($server))->installFromUrl($url, $maxMb);
					$loadErrors = 0;
					$loaded = $pm->loadPlugins($path, $loadErrors);
					$enabledNames = [];
					foreach($loaded as $plugin){
						if($pm->enablePlugin($plugin)){
							$enabledNames[] = $plugin->getName();
						}
					}
					if($enabledNames !== []){
						$server->getCommandMap()->registerServerAliases();
						Command::broadcastCommandMessage($sender, "Installed and enabled: " . implode(", ", $enabledNames) . " ($path)");
					}elseif($loadErrors > 0){
						$sender->sendMessage(TextFormat::RED . "Downloaded to $path but failed to load ($loadErrors error(s)); check the log.");
					}else{
						Command::broadcastCommandMessage($sender, "Downloaded plugin to $path (nothing new to enable — already loaded or graylisted)");
					}
				}catch(\InvalidArgumentException | \RuntimeException $e){
					$sender->sendMessage(TextFormat::RED . $e->getMessage());
				}
				return true;

			default:
				throw new InvalidCommandSyntaxException();
		}
	}
}
