<?php

declare(strict_types=1);

namespace pocketmine\form\ddui;

use pocketmine\player\Player;

/**
 * Optional hook after the client receives ShowScreen + initial DataStoreChange.
 */
interface DduiFormOnOpen{

	public function onDduiOpen(Player $player, DduiSession $session) : void;
}
