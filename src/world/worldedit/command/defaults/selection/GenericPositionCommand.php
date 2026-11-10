<?php

namespace pocketmine\world\worldedit\command\defaults\selection;

use pocketmine\world\worldedit\command\WorldEditCommand;
use pocketmine\world\worldedit\command\FlagArgumentParser;
use pocketmine\world\worldedit\command\flags\CommandFlag;
use pocketmine\world\worldedit\command\flags\CommandFlagCollection;
use pocketmine\world\worldedit\command\flags\StringCommandFlag;
use pocketmine\world\worldedit\command\KnownPermissions;
use pocketmine\world\worldedit\session\Session;
use pocketmine\world\worldedit\utils\ArgumentParser;
use pocketmine\world\Position;

class GenericPositionCommand extends WorldEditCommand
{
	use FlagArgumentParser;

	public function __construct(private int $number)
	{
		parent::__construct("/pos$number", [KnownPermissions::PERMISSION_SELECT], ["/$number"]);
		$this->flagOrder = ["x" => false, "y" => false, "z" => false];
	}

	/**
	 * @param Session               $session
	 * @param CommandFlagCollection $flags
	 */
	public function process(Session $session, CommandFlagCollection $flags): void
	{
		if ($flags->hasFlag("x") && $flags->hasFlag("y") && $flags->hasFlag("z")) {
			$session->selectPos(Position::fromObject(ArgumentParser::parseCoordinates($session, $flags->getStringFlag("x"), $flags->getStringFlag("y"), $flags->getStringFlag("z")), $session->asPlayer()->getPosition()->getWorld()), $this->number);
		} else {
			$session->selectPos($session->asPlayer()->getPosition(), $this->number);
		}
	}

	/**
	 * @param Session $session
	 * @return CommandFlag[]
	 */
	public function getKnownFlags(Session $session): array
	{
		//TODO: Turn these into integers (handle special cases like ~ and ^ while parsing flags)
		return [
			"x" => new StringCommandFlag("x"),
			"y" => new StringCommandFlag("y"),
			"z" => new StringCommandFlag("z")
		];
	}
}