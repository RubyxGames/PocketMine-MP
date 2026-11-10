<?php

declare(strict_types=1);

namespace pocketmine\form\ddui;

use pocketmine\network\mcpe\protocol\types\cereal\DynamicValue;
use pocketmine\network\mcpe\protocol\types\ddui\DataStoreUpdate;
use pocketmine\player\Player;

interface DduiForm{

	public function getScreenId() : string;

	public function getRootPropertyName() : string;

	public function buildInitialValue() : DynamicValue;

	public function handleClientUpdate(Player $player, DduiSession $session, DataStoreUpdate $update) : void;

	public function handleClose(Player $player, DduiSession $session, ?string $closeReason) : void;
}
