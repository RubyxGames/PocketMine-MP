<?php

namespace pocketmine\world\worldedit\pattern\type;

use pocketmine\world\worldedit\utils\ExtendedBinaryStream;

trait EmptyPatternData
{
	public function putData(ExtendedBinaryStream $stream): void { }

	public function parseData(ExtendedBinaryStream $stream): void { }
}