<?php

namespace pocketmine\world\worldedit\command\defaults\utility;

use Generator;
use pocketmine\world\worldedit\command\WorldEditCommand;
use pocketmine\world\worldedit\command\flags\CommandFlag;
use pocketmine\world\worldedit\command\flags\CommandFlagCollection;
use pocketmine\world\worldedit\command\KnownPermissions;
use pocketmine\world\worldedit\session\Session;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\utils\TextFormat;

class BlockInfoCommand extends WorldEditCommand
{
	public function __construct()
	{
		parent::__construct("/blockinfo", [KnownPermissions::PERMISSION_UTIL], ["/bi"]);
	}

	/**
	 * @param Session               $session
	 * @param CommandFlagCollection $flags
	 */
	public function process(Session $session, CommandFlagCollection $flags): void
	{
		$item = VanillaItems::STICK()->setNamedTag(CompoundTag::create()->setByte("isInfoStick", 1))->setCustomName(TextFormat::YELLOW . "InfoStick");
		$session->asPlayer()->getInventory()->setItem($session->asPlayer()->getInventory()->getHeldItemIndex(), $item);
	}

	/**
	 * @param Session $session
	 * @return CommandFlag[]
	 */
	public function getKnownFlags(Session $session): array
	{
		return [];
	}

	/**
	 * @param CommandFlagCollection $flags
	 * @param Session               $session
	 * @param string[]              $args
	 * @return Generator<CommandFlag>
	 */
	public function parseArguments(CommandFlagCollection $flags, Session $session, array $args): Generator
	{
		yield from [];
	}
}