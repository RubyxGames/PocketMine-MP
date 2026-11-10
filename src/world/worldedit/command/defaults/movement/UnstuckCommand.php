<?php

namespace pocketmine\world\worldedit\command\defaults\movement;

use Generator;
use pocketmine\world\worldedit\command\WorldEditCommand;
use pocketmine\world\worldedit\command\flags\CommandFlagCollection;
use pocketmine\world\worldedit\command\KnownPermissions;
use pocketmine\world\worldedit\session\Session;

class UnstuckCommand extends WorldEditCommand
{
	public function __construct()
	{
		parent::__construct("/unstuck", [KnownPermissions::PERMISSION_UTIL], ["/u"]);
	}

	/**
	 * @param Session               $session
	 * @param CommandFlagCollection $flags
	 */
	public function process(Session $session, CommandFlagCollection $flags): void
	{
		$player = $session->asPlayer();
		$player->teleport($player->getWorld()->getSafeSpawn($player->getPosition()));
	}

	public function getKnownFlags(Session $session): array
	{
		return [];
	}

	public function parseArguments(CommandFlagCollection $flags, Session $session, array $args): Generator
	{
		yield from [];
	}
}