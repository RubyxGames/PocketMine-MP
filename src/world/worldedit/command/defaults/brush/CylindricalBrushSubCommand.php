<?php

namespace pocketmine\world\worldedit\command\defaults\brush;

use pocketmine\world\worldedit\brush\BrushHandler;
use pocketmine\world\worldedit\command\flags\CommandFlag;
use pocketmine\world\worldedit\command\flags\CommandFlagCollection;
use pocketmine\world\worldedit\command\flags\FloatCommandFlag;
use pocketmine\world\worldedit\command\flags\IntegerCommandFlag;
use pocketmine\world\worldedit\command\flags\SingularCommandFlag;
use pocketmine\world\worldedit\command\flags\StringyPatternCommandFlag;
use pocketmine\world\worldedit\session\Session;
use pocketmine\nbt\tag\CompoundTag;

class CylindricalBrushSubCommand extends BrushSubCommand
{
	protected const BRUSH_TYPE = BrushHandler::BRUSH_CYLINDER;

	public function __construct()
	{
		parent::__construct(["cylinder", "cyl", "cy"], ["size" => false, "height" => false, "pattern" => false, "gravity" => false]);
	}

	/**
	 * @param CompoundTag           $nbt
	 * @param CommandFlagCollection $flags
	 */
	protected function applyBrushNbt(CompoundTag $nbt, CommandFlagCollection $flags): void
	{
		$nbt->setFloat("brushSize", $flags->getFloatFlag("size"));
		$nbt->setShort("brushHeight", $flags->getIntFlag("height"));
		$nbt->setString("brushPattern", $flags->hasFlag("gravity") ? "gravity(" . $flags->getStringFlag("pattern") . ")" : $flags->getStringFlag("pattern"));
	}

	/**
	 * @param Session $session
	 * @return CommandFlag[]
	 */
	public function getKnownFlags(Session $session): array
	{
		return [
			"size" => FloatCommandFlag::default(5.0, "size", ["radius", "rad"], "s"),
			"pattern" => StringyPatternCommandFlag::default("stone", "pattern", [], "p"),
			"gravity" => new SingularCommandFlag("gravity", [], "g"),
			"height" => IntegerCommandFlag::default(3, "height", [], "h")
		];
	}
}