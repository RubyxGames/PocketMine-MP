<?php

namespace pocketmine\world\worldedit\command\defaults\selection;

use pocketmine\world\worldedit\command\WorldEditCommand;
use pocketmine\world\worldedit\command\FlagArgumentParser;
use pocketmine\world\worldedit\command\flags\CommandFlagCollection;
use pocketmine\world\worldedit\command\flags\FloatCommandFlag;
use pocketmine\world\worldedit\math\OffGridBlockVector;
use pocketmine\world\worldedit\selection\Selection;
use pocketmine\world\worldedit\selection\Sphere;
use pocketmine\world\worldedit\session\Session;

abstract class SphericalSelectionCommand extends WorldEditCommand
{
	use FlagArgumentParser;

	public function __construct(string $name, array $permissions, array $aliases = [])
	{
		parent::__construct($name, $permissions, $aliases);
		$this->flagOrder = ["radius" => false];
	}

	public function getKnownFlags(Session $session): array
	{
		return [
			"radius" => new FloatCommandFlag("radius", [], "r")
		];
	}

	/**
	 * @param Session               $session
	 * @param CommandFlagCollection $flags
	 */
	public function process(Session $session, CommandFlagCollection $flags): void
	{
		if ($flags->hasFlag("radius")) {
			$this->processSelection($session, new Sphere($session->asPlayer()->getWorld()->getFolderName(), OffGridBlockVector::fromVector($session->asPlayer()->getPosition()), $flags->getFloatFlag("radius")), $flags);
		} else {
			$this->processSelection($session, $session->getSelection(), $flags);
		}
	}

	abstract public function processSelection(Session $session, Selection $selection, CommandFlagCollection $flags): void;
}