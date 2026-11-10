<?php

namespace pocketmine\world\worldedit\command\defaults\utility;

use pocketmine\world\worldedit\command\CommandManager;
use pocketmine\world\worldedit\command\WorldEditCommand;
use pocketmine\world\worldedit\command\FlagArgumentParser;
use pocketmine\world\worldedit\command\flags\CommandFlag;
use pocketmine\world\worldedit\command\flags\CommandFlagCollection;
use pocketmine\world\worldedit\command\flags\IntegerCommandFlag;
use pocketmine\world\worldedit\command\KnownPermissions;
use pocketmine\world\worldedit\session\Session;
use pocketmine\lang\Translatable;
use UnexpectedValueException;

class HelpCommand extends WorldEditCommand
{
	use FlagArgumentParser;

	private const COMMANDS_PER_PAGE = 8;

	public function __construct()
	{
		parent::__construct("/commands", [KnownPermissions::PERMISSION_INFO], ["/h", "/cmd"]);
		$this->flagOrder = ["page" => false];
	}

	/**
	 * @param Session               $session
	 * @param CommandFlagCollection $flags
	 */
	public function process(Session $session, CommandFlagCollection $flags): void
	{
		$commands = [];
		foreach (CommandManager::getCommands() as $command) {
			$usage = $command->getUsage();
			if ($usage instanceof Translatable) {
				throw new UnexpectedValueException("WorldEdit commands shouldn't contain translatable data");
			}
			foreach (explode(PHP_EOL, $usage) as $help) {
				$commands[] = $help;
			}
		}
		$page = max(1, $flags->getIntFlag("page"));
		if (($page - 1) * self::COMMANDS_PER_PAGE >= count($commands)) {
			$page = (int) ceil(count($commands) / self::COMMANDS_PER_PAGE);
		}
		$show = array_slice($commands, ($page - 1) * self::COMMANDS_PER_PAGE, self::COMMANDS_PER_PAGE);
		$session->sendMessage("command-list", ["{commands}" => implode("\n", $show), "{start}" => (string) (($page - 1) * self::COMMANDS_PER_PAGE + 1), "{end}" => (string) (($page - 1) * self::COMMANDS_PER_PAGE + count($show)), "{total}" => (string) count($commands)]);
	}

	/**
	 * @param Session $session
	 * @return CommandFlag[]
	 */
	public function getKnownFlags(Session $session): array
	{
		return [
			"page" => IntegerCommandFlag::default(1, "page", [], "p")
		];
	}
}