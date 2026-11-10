<?php

namespace pocketmine\world\worldedit\command\defaults\selection;

use Generator;
use pocketmine\world\worldedit\command\exception\InvalidUsageException;
use pocketmine\world\worldedit\command\flags\BlockCommandFlag;
use pocketmine\world\worldedit\command\flags\CommandFlagCollection;
use pocketmine\world\worldedit\command\flags\PatternCommandFlag;
use pocketmine\world\worldedit\pattern\block\MaskedBlockGroup;
use pocketmine\world\worldedit\pattern\logic\relation\BlockPattern;
use pocketmine\world\worldedit\pattern\Pattern;
use pocketmine\world\worldedit\session\Session;
use pocketmine\world\worldedit\world\HeightMapCache;

class ReplaceCommand extends AliasedPatternCommand
{
	public function __construct()
	{
		parent::__construct("/replace");
	}

	/**
	 * @param Session               $session
	 * @param CommandFlagCollection $flags
	 * @return Pattern
	 */
	public function parsePattern(Session $session, CommandFlagCollection $flags): Pattern
	{
		return new BlockPattern($flags->getBlockFlag("block"), [$flags->getPatternFlag("pattern")]);
	}

	public function getKnownFlags(Session $session): array
	{
		return [
			"block" => new BlockCommandFlag("block", [], "b"),
			"pattern" => new PatternCommandFlag("pattern", [], "p"),
		];
	}

	public function parseArguments(CommandFlagCollection $flags, Session $session, array $args): Generator
	{
		if (count($args) >= 2) {
			yield (new BlockCommandFlag("block"))->parseArgument($session, $args[0]);
			array_shift($args);
		} elseif (count($args) === 1) {
			yield BlockCommandFlag::with(MaskedBlockGroup::inverted(HeightMapCache::getIgnore()), "block");
		} else {
			throw new InvalidUsageException();
		}
		yield (new PatternCommandFlag("pattern"))->parseArgument($session, $args[0]);
	}
}