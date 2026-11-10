<?php

namespace pocketmine\world\worldedit\schematic\type;

use pocketmine\world\worldedit\schematic\nbt\AbstractNBTSerializer;
use pocketmine\world\worldedit\selection\DynamicBlockListSelection;
use pocketmine\nbt\tag\CompoundTag;

abstract class SchematicType
{
	public const TAG_WIDTH = "Width";
	public const TAG_HEIGHT = "Height";
	public const TAG_LENGTH = "Length";

	abstract public static function readIntoSelection(CompoundTag $nbt, DynamicBlockListSelection $target): void;

	abstract public static function getNbtSerializer(): AbstractNBTSerializer;
}