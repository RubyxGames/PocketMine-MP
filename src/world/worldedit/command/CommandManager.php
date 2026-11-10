<?php

namespace pocketmine\world\worldedit\command;

use pocketmine\world\worldedit\command\exception\CommandException;
use pocketmine\world\worldedit\command\flags\CommandFlagParser;
use pocketmine\world\worldedit\session\SessionManager;
use pocketmine\player\Player;
use pocketmine\Server;

class CommandManager
{
	/**
	 * @var WorldEditCommand[]
	 */
	private static array $commands = [];

	/**
	 * @param WorldEditCommand $command
	 */
	public static function registerCommand(WorldEditCommand $command): void
	{
		self::$commands[strtolower($command->getName())] = $command;
		Server::getInstance()->getCommandMap()->register("worldedit", $command);
	}

	/**
	 * @param WorldEditCommand[] $commands
	 */
	public static function registerCommands(array $commands): void
	{
		foreach ($commands as $command) {
			self::registerCommand($command);
		}
	}

	/**
	 * @param WorldEditCommand $command
	 * @param string[]        $args
	 * @param Player          $player
	 */
	public static function processCommand(WorldEditCommand $command, array $args, Player $player): void
	{
		$executor = $command->getExecutor($args);
		try {
			$session = SessionManager::get($player);
			$executor->process($session, CommandFlagParser::parseFlags($executor, $args, $session));
		} catch (CommandException $e) {
			$e->sendWarning(SessionManager::get($player), $command);
		}
	}

	/**
	 * @return WorldEditCommand[]
	 */
	public static function getCommands(): array
	{
		return self::$commands;
	}

	/**
	 * @param string $command
	 * @return WorldEditCommand|null
	 */
	public static function getKnownCommand(string $command): ?WorldEditCommand
	{
		foreach (self::$commands as $cmd) {
			if (strtolower($cmd->getName()) === strtolower($command)) {
				return $cmd;
			}
			foreach ($cmd->getAliases() as $alias) {
				if (strtolower($alias) === strtolower($command)) {
					return $cmd;
				}
			}
		}
		return null;
	}
}