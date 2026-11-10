<?php

declare(strict_types=1);

namespace pocketmine\command\defaults;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\utils\TextFormat;
use function count;
use function max;
use function strtolower;

class TickCommand extends VanillaCommand{

	public function __construct(){
		parent::__construct(
			"tick",
			"Freeze, step, or query the server game tick",
			"/tick <freeze|unfreeze|step|query> [n]"
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_TICK);
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		if(count($args) < 1){
			throw new InvalidCommandSyntaxException();
		}

		$server = $sender->getServer();
		$action = strtolower($args[0]);

		switch($action){
			case "freeze":
				$server->setTickFrozen(true);
				Command::broadcastCommandMessage($sender, "Game tick frozen (network/console still run)");
				return true;

			case "unfreeze":
			case "thaw":
				$server->setTickFrozen(false);
				Command::broadcastCommandMessage($sender, "Game tick unfrozen");
				return true;

			case "step":
				$n = isset($args[1]) ? max(1, (int) $args[1]) : 1;
				if(!$server->isTickFrozen()){
					$server->setTickFrozen(true);
				}
				$server->requestTickSteps($n);
				Command::broadcastCommandMessage($sender, "Stepping $n game tick(s) while frozen");
				return true;

			case "query":
			case "status":
				$frozen = $server->isTickFrozen();
				$pending = $server->getPendingTickSteps();
				$sender->sendMessage(TextFormat::GREEN . "Tick frozen: " . ($frozen ? "yes" : "no") .
					($pending > 0 ? " (pending steps: $pending)" : "") .
					" | counter: " . $server->getTick());
				return true;

			default:
				throw new InvalidCommandSyntaxException();
		}
	}
}
