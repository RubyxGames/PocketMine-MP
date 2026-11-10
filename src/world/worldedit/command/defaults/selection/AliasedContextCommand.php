<?php

namespace pocketmine\world\worldedit\command\defaults\selection;

use pocketmine\world\worldedit\command\WorldEditCommand;
use pocketmine\world\worldedit\command\FlagArgumentParser;
use pocketmine\world\worldedit\command\flags\CommandFlag;
use pocketmine\world\worldedit\command\flags\CommandFlagCollection;
use pocketmine\world\worldedit\command\flags\PatternCommandFlag;
use pocketmine\world\worldedit\command\KnownPermissions;
use pocketmine\world\worldedit\pattern\block\StaticBlock;
use pocketmine\world\worldedit\selection\SelectionContext;
use pocketmine\world\worldedit\session\Session;
use pocketmine\world\worldedit\task\editing\SetTask;
use pocketmine\block\VanillaBlocks;

class AliasedContextCommand extends WorldEditCommand
{
	use FlagArgumentParser;

	/**
	 * @param string           $name
	 * @param string[]         $aliases
	 * @param SelectionContext $context
	 */
	public function __construct(private SelectionContext $context, string $name, array $aliases = [])
	{
		parent::__construct($name, [KnownPermissions::PERMISSION_EDIT], $aliases);
		$this->flagOrder = ["pattern" => false];
	}

	/**
	 * @param Session               $session
	 * @param CommandFlagCollection $flags
	 */
	public function process(Session $session, CommandFlagCollection $flags): void
	{
		$session->runSettingTask(new SetTask($session->getSelection(), $flags->getPatternFlag("pattern"), $this->context));
	}

	/**
	 * @param Session $session
	 * @return CommandFlag[]
	 */
	public function getKnownFlags(Session $session): array
	{
		return [
			"pattern" => PatternCommandFlag::default(StaticBlock::from(VanillaBlocks::STONE()), "pattern", [], "p")
		];
	}
}