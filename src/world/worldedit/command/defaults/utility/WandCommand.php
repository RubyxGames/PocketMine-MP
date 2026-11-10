<?php

namespace pocketmine\world\worldedit\command\defaults\utility;

use Generator;
use pocketmine\world\worldedit\command\WorldEditCommand;
use pocketmine\world\worldedit\command\flags\CommandFlag;
use pocketmine\world\worldedit\command\flags\CommandFlagCollection;
use pocketmine\world\worldedit\command\KnownPermissions;
use pocketmine\world\worldedit\session\Session;
use pocketmine\item\VanillaItems;

class WandCommand extends WorldEditCommand
{
	public function __construct()
	{
		parent::__construct("/wand", [KnownPermissions::PERMISSION_UTIL]);
	}

	/**
	 * @param Session               $session
	 * @param CommandFlagCollection $flags
	 */
	public function process(Session $session, CommandFlagCollection $flags): void
	{
		$session->asPlayer()->getInventory()->setItem($session->asPlayer()->getInventory()->getHeldItemIndex(), VanillaItems::WOODEN_AXE()); //some people prefer a command I guess
	}

	/**
	 * @param Session $session
	 * @return CommandFlag[]
	 */
	public function getKnownFlags(Session $session): array
	{
		return [];
	}

	/**
	 * @param CommandFlagCollection $flags
	 * @param Session               $session
	 * @param string[]              $args
	 * @return Generator<CommandFlag>
	 */
	public function parseArguments(CommandFlagCollection $flags, Session $session, array $args): Generator
	{
		yield from [];
	}
}