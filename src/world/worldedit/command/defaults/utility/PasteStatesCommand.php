<?php

namespace pocketmine\world\worldedit\command\defaults\utility;

use Generator;
use pocketmine\world\worldedit\command\WorldEditCommand;
use pocketmine\world\worldedit\command\flags\CommandFlag;
use pocketmine\world\worldedit\command\flags\CommandFlagCollection;
use pocketmine\world\worldedit\command\KnownPermissions;
use pocketmine\world\worldedit\math\BlockVector;
use pocketmine\world\worldedit\session\Session;
use pocketmine\world\worldedit\task\expanding\PasteBlockStatesTask;

class PasteStatesCommand extends WorldEditCommand
{
	public function __construct()
	{
		parent::__construct("/pastestates", [KnownPermissions::PERMISSION_MANAGE, KnownPermissions::PERMISSION_GENERATE, KnownPermissions::PERMISSION_EDIT]);
	}

	/**
	 * @param Session               $session
	 * @param CommandFlagCollection $flags
	 */
	public function process(Session $session, CommandFlagCollection $flags): void
	{
		$session->runSettingTask(new PasteBlockStatesTask($session->asPlayer()->getWorld()->getFolderName(), BlockVector::fromVector($session->asPlayer()->getPosition())));
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