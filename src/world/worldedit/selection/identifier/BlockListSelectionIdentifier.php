<?php

namespace pocketmine\world\worldedit\selection\identifier;

interface BlockListSelectionIdentifier extends SelectionIdentifier
{
	public function toIdentifier(): StoredSelectionIdentifier;
}