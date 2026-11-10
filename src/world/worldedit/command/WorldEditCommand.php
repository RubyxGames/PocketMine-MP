<?php

namespace pocketmine\world\worldedit\command;

use Generator;
use pocketmine\world\worldedit\command\flags\CommandFlag;
use pocketmine\world\worldedit\command\flags\CommandFlagCollection;
use pocketmine\world\worldedit\WorldEdit;
use pocketmine\world\worldedit\session\Session;
use pocketmine\world\worldedit\utils\Messages;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginOwned;

abstract class WorldEditCommand extends Command implements PluginOwned, CommandExecutor
{
	/**
	 * @param string   $name
	 * @param string[] $permissions
	 * @param string[] $aliases
	 */
	public function __construct(string $name, array $permissions, array $aliases = [])
	{
		$realName = str_starts_with($name, "/") ? substr($name, 1) : $name;
		parent::__construct($name, Messages::translate("command-$realName-description"), $this->prepareUsage($realName), $aliases);
		$this->setPermissions($permissions);
	}

	/**
	 * @param CommandSender $sender
	 * @param string        $commandLabel
	 * @param string[]      $args
	 * @return void
	 */
	public function execute(CommandSender $sender, string $commandLabel, array $args): void
	{
		if (!$sender instanceof Player) {
			return;
		}
		if (!$this->testPermission($sender)) {
			return;
		}

		CommandManager::processCommand($this, $args, $sender);
	}

	/**
	 * @param string[] $args
	 */
	public function getExecutor(array &$args): CommandExecutor
	{
		return $this;
	}

	public function getOwningPlugin(): Plugin
	{
		return WorldEdit::getInstance()->getPluginHost();
	}

	/**
	 * @param string $commandName
	 * @return string
	 */
	public function prepareUsage(string $commandName): string
	{
		$usages = [];
		foreach (explode(PHP_EOL, Messages::translate("command-$commandName-usage")) as $help) {
			$usages[] = str_contains($help, " - ") ? $help : $help . " - " . Messages::translate("command-$commandName-description");
		}
		return implode(PHP_EOL, $usages);
	}
}