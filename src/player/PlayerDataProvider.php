<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\player;

use pocketmine\nbt\tag\CompoundTag;

/**
 * Handles storage of player data. Implementations must treat player names in a case-insensitive manner.
 * Optional XUID prefers players/xuid/<xuid>.dat with name-file fallback and dual-write on save.
 */
interface PlayerDataProvider{

	/**
	 * Returns whether there are any data associated with the given player name (or XUID when provided).
	 */
	public function hasData(string $name, ?string $xuid = null) : bool;

	/**
	 * Returns the data associated with the given player, or null if there is no data.
	 * Load order when $xuid is set: xuid file, then name file.
	 * TODO: we need an async version of this
	 *
	 * @throws PlayerDataLoadException
	 */
	public function loadData(string $name, ?string $xuid = null) : ?CompoundTag;

	/**
	 * Saves data for the given player name. When $xuid is set, also writes players/xuid/<xuid>.dat.
	 *
	 * @throws PlayerDataSaveException
	 */
	public function saveData(string $name, CompoundTag $data, ?string $xuid = null) : void;
}
