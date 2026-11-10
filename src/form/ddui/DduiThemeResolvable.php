<?php

declare(strict_types=1);

namespace pocketmine\form\ddui;

use pocketmine\player\Player;

/** Optional: resolve {@code pocketmine.yml} theme alias per player when opening a DDUI form. */
interface DduiThemeResolvable{
	public function resolveThemeAlias(Player $player) : ?string;
}
