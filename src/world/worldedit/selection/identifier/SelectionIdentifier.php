<?php

namespace pocketmine\world\worldedit\selection\identifier;

use pocketmine\world\worldedit\selection\Selection;

interface SelectionIdentifier
{
	public function fastSerialize(): string;

	public static function fastDeserialize(string $data): static;

	public function asSelection(): Selection;
}