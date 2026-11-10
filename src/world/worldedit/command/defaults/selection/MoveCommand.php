<?php

namespace pocketmine\world\worldedit\command\defaults\selection;

use Generator;
use pocketmine\world\worldedit\command\WorldEditCommand;
use pocketmine\world\worldedit\command\flags\CommandFlag;
use pocketmine\world\worldedit\command\flags\CommandFlagCollection;
use pocketmine\world\worldedit\command\flags\VectorCommandFlag;
use pocketmine\world\worldedit\command\KnownPermissions;
use pocketmine\world\worldedit\math\OffGridBlockVector;
use pocketmine\world\worldedit\session\Session;
use pocketmine\world\worldedit\task\editing\move\MoveTask;
use pocketmine\world\worldedit\utils\ArgumentParser;

class MoveCommand extends WorldEditCommand
{
	public function __construct()
	{
		parent::__construct("/move", [KnownPermissions::PERMISSION_EDIT]);
	}

	/**
	 * @param Session               $session
	 * @param CommandFlagCollection $flags
	 */
	public function process(Session $session, CommandFlagCollection $flags): void
	{
		$session->runSettingTask(new MoveTask($session->getSelection(), $flags->getVectorFlag("vector")->diff(OffGridBlockVector::zero())));
	}

	/**
	 * @param Session $session
	 * @return CommandFlag[]
	 */
	public function getKnownFlags(Session $session): array
	{
		return [
			"vector" => new VectorCommandFlag("vector", [], "v")
		];
	}

	/**
	 * @param CommandFlagCollection $flags
	 * @param Session               $session
	 * @param string[]              $args
	 * @return Generator<CommandFlag>
	 */
	public function parseArguments(CommandFlagCollection $flags, Session $session, array $args): Generator
	{
		if (!$flags->hasFlag("vector")) {
			yield VectorCommandFlag::with(ArgumentParser::parseDirectionVector($session, $args[0] ?? null, $args[1] ?? null), "vector");
		}
	}
}