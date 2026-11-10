<?php

namespace pocketmine\world\worldedit\command\defaults\brush;

use pocketmine\world\worldedit\brush\BrushHandler;
use pocketmine\world\worldedit\command\flags\BlockCommandFlag;
use pocketmine\world\worldedit\command\flags\CommandFlag;
use pocketmine\world\worldedit\command\flags\CommandFlagCollection;
use pocketmine\world\worldedit\command\flags\FloatCommandFlag;
use pocketmine\world\worldedit\command\flags\SingularCommandFlag;
use pocketmine\world\worldedit\command\flags\StringyPatternCommandFlag;
use pocketmine\world\worldedit\session\Session;
use pocketmine\nbt\tag\CompoundTag;

class ReplaceBrushSubCommand extends BrushSubCommand
{
	protected const BRUSH_TYPE = BrushHandler::BRUSH_REPLACE;

	public function __construct()
	{
		parent::__construct(["replace", "rep"], ["from" => true, "pattern" => true, "size" => false, "gravity" => false]);
	}

	/**
	 * @param CompoundTag           $nbt
	 * @param CommandFlagCollection $flags
	 */
	protected function applyBrushNbt(CompoundTag $nbt, CommandFlagCollection $flags): void
	{
		$nbt->setString("replaceFrom", $flags->getBlockFlag("from")->fastSerialize());
		$nbt->setString("brushPattern", $flags->hasFlag("gravity") ? "gravity(" . $flags->getStringFlag("pattern") . ")" : $flags->getStringFlag("pattern"));
		$nbt->setFloat("brushSize", $flags->getFloatFlag("size"));
	}

	/**
	 * @param Session $session
	 * @return CommandFlag[]
	 */
	public function getKnownFlags(Session $session): array
	{
		return [
			"from" => new BlockCommandFlag("from", ["block", "mask"], "b"),
			"pattern" => StringyPatternCommandFlag::default("stone", "pattern", [], "p"),
			"size" => FloatCommandFlag::default(5.0, "size", ["radius", "rad"], "s"),
			"gravity" => new SingularCommandFlag("gravity", [], "g")
		];
	}
}
