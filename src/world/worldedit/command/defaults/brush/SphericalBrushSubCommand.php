<?php

namespace pocketmine\world\worldedit\command\defaults\brush;

use pocketmine\world\worldedit\command\flags\CommandFlag;
use pocketmine\world\worldedit\command\flags\CommandFlagCollection;
use pocketmine\world\worldedit\command\flags\FloatCommandFlag;
use pocketmine\world\worldedit\command\flags\SingularCommandFlag;
use pocketmine\world\worldedit\command\flags\StringyPatternCommandFlag;
use pocketmine\world\worldedit\session\Session;
use pocketmine\nbt\tag\CompoundTag;

class SphericalBrushSubCommand extends BrushSubCommand
{
	public function __construct()
	{
		parent::__construct(["sphere", "sph"], ["size" => false, "pattern" => false, "gravity" => false]);
	}

	/**
	 * @param CompoundTag           $nbt
	 * @param CommandFlagCollection $flags
	 */
	protected function applyBrushNbt(CompoundTag $nbt, CommandFlagCollection $flags): void
	{
		$nbt->setFloat("brushSize", $flags->getFloatFlag("size"));
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
			"gravity" => new SingularCommandFlag("gravity", [], "g")
		];
	}
}