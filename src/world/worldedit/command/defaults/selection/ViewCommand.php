<?php

namespace pocketmine\world\worldedit\command\defaults\selection;

use Generator;
use pocketmine\world\worldedit\command\WorldEditCommand;
use pocketmine\world\worldedit\command\flags\CommandFlag;
use pocketmine\world\worldedit\command\flags\CommandFlagCollection;
use pocketmine\world\worldedit\command\KnownPermissions;
use pocketmine\world\worldedit\session\Session;
use pocketmine\world\worldedit\world\clientblock\ClientSideBlockManager;
use pocketmine\world\worldedit\world\clientblock\StructureBlockWindow;

class ViewCommand extends WorldEditCommand
{
	public function __construct()
	{
		parent::__construct("/view", [KnownPermissions::PERMISSION_SELECT], ["/show"]);
	}

	/**
	 * @param Session               $session
	 * @param CommandFlagCollection $flags
	 */
	public function process(Session $session, CommandFlagCollection $flags): void
	{
		$selection = $session->getCube();

		ClientSideBlockManager::registerBlock($session->getPlayer(), new StructureBlockWindow($session->asPlayer(), $selection->getPos1(), $selection->getPos2()));
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