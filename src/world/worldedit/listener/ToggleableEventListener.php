<?php

namespace pocketmine\world\worldedit\listener;

use pocketmine\world\worldedit\WorldEdit;
use pocketmine\Server;

trait ToggleableEventListener
{
	public static function init(): void
	{
		Server::getInstance()->getPluginManager()->registerEvents(new self(), WorldEdit::getInstance()->getPluginHost());
	}
}