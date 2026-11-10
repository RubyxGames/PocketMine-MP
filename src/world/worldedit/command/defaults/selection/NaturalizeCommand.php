<?php

namespace pocketmine\world\worldedit\command\defaults\selection;

use pocketmine\world\worldedit\command\flags\CommandFlagCollection;
use pocketmine\world\worldedit\command\flags\PatternCommandFlag;
use pocketmine\world\worldedit\pattern\block\StaticBlock;
use pocketmine\world\worldedit\pattern\functional\NaturalizePattern;
use pocketmine\world\worldedit\pattern\Pattern;
use pocketmine\world\worldedit\session\Session;
use pocketmine\block\VanillaBlocks;

class NaturalizeCommand extends AliasedPatternCommand
{
	public function __construct()
	{
		parent::__construct("/naturalize", ["top" => false, "middle" => false, "bottom" => false], ["/nat"]);
	}

	/**
	 * @param Session               $session
	 * @param CommandFlagCollection $flags
	 * @return Pattern
	 */
	public function parsePattern(Session $session, CommandFlagCollection $flags): Pattern
	{
		return new NaturalizePattern($flags->getPatternFlag("top"), $flags->getPatternFlag("middle"), $flags->getPatternFlag("bottom"));
	}

	public function getKnownFlags(Session $session): array
	{
		return [
			"top" => PatternCommandFlag::default(StaticBlock::from(VanillaBlocks::GRASS()), "top", [], "t"),
			"middle" => PatternCommandFlag::default(StaticBlock::from(VanillaBlocks::DIRT()), "middle", [], "m"),
			"bottom" => PatternCommandFlag::default(StaticBlock::from(VanillaBlocks::STONE()), "bottom", [], "b"),
		];
	}
}