<?php

namespace pocketmine\world\worldedit\command\defaults\brush;

use pocketmine\world\worldedit\brush\BrushHandler;
use pocketmine\world\worldedit\command\flags\CommandFlag;
use pocketmine\world\worldedit\command\flags\CommandFlagCollection;
use pocketmine\world\worldedit\command\flags\FloatCommandFlag;
use pocketmine\world\worldedit\command\flags\StringyPatternCommandFlag;
use pocketmine\world\worldedit\session\Session;
use pocketmine\nbt\tag\CompoundTag;

class NaturalizingBrushSubCommand extends BrushSubCommand
{
	protected const BRUSH_TYPE = BrushHandler::BRUSH_NATURALIZE;

	public function __construct()
	{
		parent::__construct(["naturalize", "nat", "naturalized"], ["size" => false, "top" => false, "middle" => false, "bottom" => false]);
	}

	/**
	 * @param CompoundTag           $nbt
	 * @param CommandFlagCollection $flags
	 */
	protected function applyBrushNbt(CompoundTag $nbt, CommandFlagCollection $flags): void
	{
		$nbt->setFloat("brushSize", $flags->getFloatFlag("size"));
		$nbt->setString("topBlock", $flags->getStringFlag("top"));
		$nbt->setString("middleBlock", $flags->getStringFlag("middle"));
		$nbt->setString("bottomBlock", $flags->getStringFlag("bottom"));
	}

	/**
	 * @param Session $session
	 * @return CommandFlag[]
	 */
	public function getKnownFlags(Session $session): array
	{
		return [
			"size" => FloatCommandFlag::default(5.0, "size", ["radius", "rad"], "s"),
			"top" => StringyPatternCommandFlag::default("grass", "top", [], "t"),
			"middle" => StringyPatternCommandFlag::default("dirt", "middle", [], "m"),
			"bottom" => StringyPatternCommandFlag::default("stone", "bottom", [], "b")
		];
	}
}