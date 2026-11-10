<?php

namespace pocketmine\world\worldedit\command\defaults\selection;

use pocketmine\world\worldedit\command\flags\BlockCommandFlag;
use pocketmine\world\worldedit\command\flags\CommandFlag;
use pocketmine\world\worldedit\command\flags\CommandFlagCollection;
use pocketmine\world\worldedit\command\flags\PatternCommandFlag;
use pocketmine\world\worldedit\pattern\block\MaskedBlockGroup;
use pocketmine\world\worldedit\pattern\logic\relation\AbovePattern;
use pocketmine\world\worldedit\pattern\logic\relation\BlockPattern;
use pocketmine\world\worldedit\pattern\Pattern;
use pocketmine\world\worldedit\session\Session;
use pocketmine\world\worldedit\world\HeightMapCache;

class OverlayCommand extends AliasedPatternCommand
{
	public function __construct()
	{
		parent::__construct("/overlay", ["pattern" => true, "block" => false]);
	}

	/**
	 * @param Session               $session
	 * @param CommandFlagCollection $flags
	 * @return Pattern
	 */
	public function parsePattern(Session $session, CommandFlagCollection $flags): Pattern
	{
		return new BlockPattern(new MaskedBlockGroup(HeightMapCache::getIgnore()), [new AbovePattern($flags->getBlockFlag("block"), [$flags->getPatternFlag("pattern")])]);
	}

	/**
	 * @param Session $session
	 * @return CommandFlag[]
	 */
	public function getKnownFlags(Session $session): array
	{
		return [
			"pattern" => new PatternCommandFlag("pattern", [], "p"),
			"block" => BlockCommandFlag::default(MaskedBlockGroup::inverted(HeightMapCache::getIgnore()), "block", [], "b"),
		];
	}
}