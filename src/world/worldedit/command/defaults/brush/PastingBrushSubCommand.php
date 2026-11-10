<?php

namespace pocketmine\world\worldedit\command\defaults\brush;

use pocketmine\world\worldedit\brush\BrushHandler;
use pocketmine\world\worldedit\command\flags\CommandFlag;
use pocketmine\world\worldedit\command\flags\CommandFlagCollection;
use pocketmine\world\worldedit\command\flags\SingularCommandFlag;
use pocketmine\world\worldedit\session\Session;
use pocketmine\nbt\tag\CompoundTag;

class PastingBrushSubCommand extends BrushSubCommand
{
	protected const BRUSH_TYPE = BrushHandler::BRUSH_PASTE;

	public function __construct()
	{
		parent::__construct(["paste", "pasting"], ["insert" => false]);
	}

	/**
	 * @param CompoundTag           $nbt
	 * @param CommandFlagCollection $flags
	 */
	protected function applyBrushNbt(CompoundTag $nbt, CommandFlagCollection $flags): void
	{
		$nbt->setByte("isInsert", $flags->hasFlag("insert") ? 1 : 0);
	}

	/**
	 * @param Session $session
	 * @return CommandFlag[]
	 */
	public function getKnownFlags(Session $session): array
	{
		return [
			"insert" => new SingularCommandFlag("insert", [], "i")
		];
	}
}