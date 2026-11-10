<?php

namespace pocketmine\world\worldedit\brush;

use pocketmine\world\worldedit\WorldEdit;
use pocketmine\world\worldedit\math\OffGridBlockVector;
use pocketmine\world\worldedit\pattern\block\BlockType;
use pocketmine\world\worldedit\pattern\functional\NaturalizePattern;
use pocketmine\world\worldedit\pattern\logic\relation\BlockPattern;
use pocketmine\world\worldedit\pattern\parser\ParseError;
use pocketmine\world\worldedit\pattern\parser\PatternParser;
use pocketmine\world\worldedit\selection\Cylinder;
use pocketmine\world\worldedit\selection\Sphere;
use pocketmine\world\worldedit\session\SessionManager;
use pocketmine\world\worldedit\task\editing\DynamicPasteTask;
use pocketmine\world\worldedit\task\editing\SetTask;
use pocketmine\world\worldedit\task\editing\SmoothTask;
use pocketmine\block\BlockTypeIds;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use Throwable;

class BrushHandler
{
	public const BRUSH_SPHERE = 0;
	public const BRUSH_SMOOTH = 1;
	public const BRUSH_NATURALIZE = 2;
	public const BRUSH_CYLINDER = 3;
	public const BRUSH_PASTE = 4;
	public const BRUSH_REPLACE = 5;

	/**
	 * @param CompoundTag $brush
	 * @param Player      $player
	 */
	public static function handleBrush(CompoundTag $brush, Player $player): void
	{
		try {
			$target = $player->getTargetBlock(100, [BlockTypeIds::WATER => true, BlockTypeIds::LAVA => true, BlockTypeIds::AIR => true]);
		} catch (Throwable) {
			//No idea why this is crashing for some users, probably caused by weird binaries / plugins
			WorldEdit::getInstance()->getLogger()->warning("Player " . $player->getName() . " has thrown an exception while trying to get a target block");
			return;
		}
		if ($target !== null) {
			$session = SessionManager::get($player);
			$point = OffGridBlockVector::fromVector($target->getPosition());
			$world = $player->getWorld()->getFolderName();
			try {
				switch (self::nameToIdentifier($brush->getString("brushType", ""))) {
					case self::BRUSH_SPHERE:
						$session->runSettingTask(new SetTask(new Sphere($world, $point, $brush->getFloat("brushSize", 0)), PatternParser::parseInternal($brush->getString("brushPattern", "stone"))));
						break;
					case self::BRUSH_SMOOTH:
						$session->runSettingTask(new SmoothTask(new Sphere($world, $point, $brush->getFloat("brushSize", 0))));
						break;
					case self::BRUSH_NATURALIZE:
						$session->runSettingTask(new SetTask(new Sphere($world, $point, $brush->getFloat("brushSize", 0)), new NaturalizePattern(PatternParser::parseInternal($brush->getString("topBlock", "grass")), PatternParser::parseInternal($brush->getString("middleBlock", "dirt")), PatternParser::parseInternal($brush->getString("bottomBlock", "stone")))));
						break;
					case self::BRUSH_CYLINDER:
						$session->runSettingTask(new SetTask(new Cylinder($world, $point, $brush->getFloat("brushSize", 0), $brush->getShort("brushHeight", 0)), PatternParser::parseInternal($brush->getString("brushPattern", "stone"))));
						break;
					case self::BRUSH_PASTE:
						$clipboard = SessionManager::get($player)->getClipboard();
						if (!$clipboard->isValid()) {
							$session->sendMessage("no-clipboard");
							return;
						}
						$session->runSettingTask(new DynamicPasteTask($world, $clipboard, $point->up(), $brush->getByte("isInsert", 0)));
						break;
					case self::BRUSH_REPLACE:
						$mask = BlockType::fastDeserialize($brush->getString("replaceFrom"));
						$session->runSettingTask(new SetTask(new Sphere($world, $point, $brush->getFloat("brushSize", 0)), new BlockPattern($mask, [PatternParser::parseInternal($brush->getString("brushPattern", "stone"))])));
				}
			} catch (ParseError $e) {
				$session->sendMessage("pattern-invalid", ["{message}" => $e->getMessage()]);
			} catch (Throwable $e) {
				$session->sendMessage("pattern-invalid", ["{message}" => $e->getMessage()]);
			}
		}
	}

	/**
	 * @param string $brush
	 * @return int
	 */
	public static function nameToIdentifier(string $brush): int
	{
		return match (strtolower($brush)) {
			default => self::BRUSH_SPHERE,
			"smooth", "smoothing" => self::BRUSH_SMOOTH,
			"naturalize", "nat", "naturalized" => self::BRUSH_NATURALIZE,
			"cylinder", "cyl", "cy" => self::BRUSH_CYLINDER,
			"paste", "pasting" => self::BRUSH_PASTE,
			"replace", "rep" => self::BRUSH_REPLACE
		};
	}

	/**
	 * @param int $brush
	 * @return string
	 */
	public static function identifierToName(int $brush): string
	{
		return match ($brush) {
			default => "sphere",
			self::BRUSH_SMOOTH => "smooth",
			self::BRUSH_NATURALIZE => "naturalize",
			self::BRUSH_CYLINDER => "cylinder",
			self::BRUSH_PASTE => "paste",
			self::BRUSH_REPLACE => "replace"
		};
	}
}