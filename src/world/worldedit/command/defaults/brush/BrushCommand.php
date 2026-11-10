<?php

namespace pocketmine\world\worldedit\command\defaults\brush;

use Generator;
use pocketmine\world\worldedit\command\flags\CommandFlagCollection;
use pocketmine\world\worldedit\command\KnownPermissions;
use pocketmine\world\worldedit\command\OverloadedCommand;
use pocketmine\world\worldedit\session\Session;
use RuntimeException;

class BrushCommand extends OverloadedCommand
{
	public function __construct()
	{
		parent::__construct("/brush", [
			new SphericalBrushSubCommand(),
			new ReplaceBrushSubCommand(),
			new SmoothingBrushSubCommand(),
			new NaturalizingBrushSubCommand(),
			new CylindricalBrushSubCommand(),
			new PastingBrushSubCommand()
		], true, [KnownPermissions::PERMISSION_BRUSH], ["/br"]);
	}

	/**
	 * @param Session               $session
	 * @param CommandFlagCollection $flags
	 */
	public function process(Session $session, CommandFlagCollection $flags): void
	{
		throw new RuntimeException("This method should not be called");
	}

	public function getKnownFlags(Session $session): array
	{
		throw new RuntimeException("This method should not be called");
	}

	public function parseArguments(CommandFlagCollection $flags, Session $session, array $args): Generator
	{
		throw new RuntimeException("This method should not be called");
	}
}