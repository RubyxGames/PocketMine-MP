<?php

namespace pocketmine\world\worldedit\command\defaults\brush;

use pocketmine\world\worldedit\brush\BrushHandler;
use pocketmine\world\worldedit\command\flags\CommandFlagCollection;
use pocketmine\world\worldedit\command\KnownPermissions;
use pocketmine\world\worldedit\command\SubCommand;
use pocketmine\world\worldedit\session\Session;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\tag\Tag;
use pocketmine\utils\TextFormat;
use function mb_check_encoding;
use function strlen;
use function substr;
use function bin2hex;

abstract class BrushSubCommand extends SubCommand
{
	protected const BRUSH_TYPE = BrushHandler::BRUSH_SPHERE;

	/**
	 * @param string[]            $names
	 * @param array<string, bool> $flagOrder
	 */
	public function __construct(array $names, array $flagOrder)
	{
		parent::__construct($names, $flagOrder, [KnownPermissions::PERMISSION_BRUSH]);
	}

	/**
	 * @param Session               $session
	 * @param CommandFlagCollection $flags
	 */
	public function process(Session $session, CommandFlagCollection $flags): void
	{
		$nbt = CompoundTag::create()->setString("brushType", BrushHandler::identifierToName(static::BRUSH_TYPE));
		$this->applyBrushNbt($nbt, $flags);
		$item = VanillaItems::WOODEN_SHOVEL()->setNamedTag($nbt);
		$lore = [];
		foreach ($nbt->getValue() as $name => $tag) {
			$lore[] = self::utf8LoreLine($name . ": " . self::formatTagValueForLore($tag));
		}
		$item->setLore($lore);
		$item->setCustomName(TextFormat::GOLD . "Brush");
		$session->asPlayer()->getInventory()->setItem($session->asPlayer()->getInventory()->getHeldItemIndex(), $item);
	}

	/**
	 * @param CompoundTag           $nbt
	 * @param CommandFlagCollection $flags
	 */
	abstract protected function applyBrushNbt(CompoundTag $nbt, CommandFlagCollection $flags): void;

	private static function formatTagValueForLore(Tag $tag) : string{
		if($tag instanceof StringTag){
			return self::utf8SafeSerializedString($tag->getValue());
		}
		return $tag->toString();
	}

	/**
	 * StringTag может хранить бинарь (например replaceFrom из fastSerialize()); Item::setLore требует UTF-8.
	 */
	private static function utf8SafeSerializedString(string $raw) : string{
		if(mb_check_encoding($raw, 'UTF-8')){
			return $raw;
		}
		$len = strlen($raw);
		$hex = bin2hex(substr($raw, 0, 24));
		return '[binary] ' . $hex . ($len > 24 ? '…' : '');
	}

	private static function utf8LoreLine(string $line) : string{
		if(mb_check_encoding($line, 'UTF-8')){
			return $line;
		}
		$fixed = @iconv('UTF-8', 'UTF-8//IGNORE', $line);
		return ($fixed !== false && $fixed !== '') ? $fixed : '[…]';
	}
}