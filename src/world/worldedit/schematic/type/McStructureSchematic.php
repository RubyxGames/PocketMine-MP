<?php

namespace pocketmine\world\worldedit\schematic\type;

use pocketmine\world\worldedit\math\BlockOffsetVector;
use pocketmine\world\worldedit\math\BlockVector;
use pocketmine\world\worldedit\schematic\nbt\AbstractListTag;
use pocketmine\world\worldedit\schematic\nbt\AbstractNBT;
use pocketmine\world\worldedit\schematic\nbt\AbstractNBTSerializer;
use pocketmine\world\worldedit\schematic\nbt\LittleEndianAbstractNBTSerializer;
use pocketmine\world\worldedit\selection\DynamicBlockListSelection;
use pocketmine\world\worldedit\task\CancelException;
use pocketmine\world\worldedit\thread\block\BlockStateTranslationManager;
use pocketmine\world\worldedit\thread\EditThread;
use pocketmine\world\worldedit\utils\EntityUtils;
use pocketmine\world\worldedit\utils\TileUtils;
use pocketmine\entity\Entity;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use pocketmine\world\World;
use Throwable;
use UnexpectedValueException;

class McStructureSchematic extends SchematicType
{
	public const FORMAT_VERSION = "format_version"; //Always 1
	public const WORLD_ORIGIN = "structure_world_origin";
	public const SIZE = "size";
	public const STRUCTURE = "structure";
	public const PALETTE = "palette";
	public const DEFAULT_PALETTE = "default";
	public const BLOCK_INDEX = "block_indices";
	public const BLOCK_PALETTE = "block_palette";
	public const TILE_INDEX = "block_position_data";
	public const TILE_ENTRY = "block_entity_data";
	public const ENTITIES = "entities";
	public const ENTITY_POS = "pos";
	public const ENTITY_NBT = "nbt";

	/**
	 * @param CompoundTag               $nbt
	 * @param DynamicBlockListSelection $target
	 * @throws CancelException
	 */
	public static function readIntoSelection(CompoundTag $nbt, DynamicBlockListSelection $target): void
	{
		$size = $nbt->getTag(self::SIZE);
		$offset = $nbt->getTag(self::WORLD_ORIGIN);
		if (!$size instanceof AbstractListTag || $size->getTagType() !== NBT::TAG_Int || $size->getLength() !== 3 || !$offset instanceof AbstractListTag || $offset->getTagType() !== NBT::TAG_Int || $offset->getLength() !== 3) {
			throw new UnexpectedValueException("Invalid schematic file");
		}
		$xSize = $size->mustGetInt();
		$ySize = $size->mustGetInt();
		$zSize = $size->mustGetInt();
		$target->setPoint(new BlockOffsetVector(0, -World::Y_MIN, 0));
		$target->setPos1(new BlockVector(0, World::Y_MIN, 0));
		$target->setPos2(new BlockVector($xSize - 1, World::Y_MIN + $ySize - 1, $zSize - 1));
		$target->getManager()->loadBetween($target->getPos1(), $target->getPos2());

		$offsetX = $offset->mustGetInt();
		$offsetY = $offset->mustGetInt();
		$offsetZ = $offset->mustGetInt();

		$structure = $nbt->getCompoundTag(self::STRUCTURE);
		$palette = $structure?->getCompoundTag(self::PALETTE)?->getCompoundTag(self::DEFAULT_PALETTE);
		$blockDataRaw = $structure?->getTag(self::BLOCK_INDEX);

		if ($palette === null) {
			throw new UnexpectedValueException("Schematic is missing palette");
		}

		if (!$blockDataRaw instanceof AbstractListTag) {
			throw new UnexpectedValueException("Invalid schematic file");
		}

		$blockData = $blockDataRaw->next(); //First layer, we ignore the second one

		$blockPalette = $palette->getTag(self::BLOCK_PALETTE);
		$tileData = $palette->getCompoundTag(self::TILE_INDEX) ?? new CompoundTag();

		if (!$blockPalette instanceof AbstractListTag || $blockPalette->getTagType() !== NBT::TAG_Compound || !$blockData instanceof AbstractListTag) {
			throw new UnexpectedValueException("Invalid schematic file");
		}

		$palette = [];
		while ($blockPalette->hasNext()) {
			$block = $blockPalette->next();
			if (!$block instanceof CompoundTag) {
				throw new UnexpectedValueException("Invalid schematic file");
			}
			$palette[] = GlobalBlockStateHandlers::getUpgrader()->upgradeBlockStateNbt($block);
		}

		$palette = BlockStateTranslationManager::requestRuntimeId($palette);

		$i = 0;
		$yMax = $ySize + World::Y_MIN;
		for ($x = 0; $x < $xSize; ++$x) {
			for ($y = World::Y_MIN; $y < $yMax; ++$y) {
				for ($z = 0; $z < $zSize; ++$z) {
					$target->addBlock($x, $y, $z, $palette[$blockData->mustGetInt()]);

					$tile = $tileData->getCompoundTag((string) $i++);
					if ($tile !== null) {
						$tileEntry = AbstractNBT::fromAbstractTile($tile->getCompoundTag(self::TILE_ENTRY));
						if ($tileEntry === null) {
							throw new UnexpectedValueException("Invalid schematic file");
						}
						$target->addTile(TileUtils::offsetCompound($tileEntry, -$offsetX, -$offsetY + World::Y_MIN, -$offsetZ));
					}
				}
			}
		}

		self::loadEntities($structure, $offsetX, $offsetY, $offsetZ, $target);
	}

	/**
	 * Bedrock structure entities: list of { pos: [rel floats], nbt: compound }.
	 */
	private static function loadEntities(CompoundTag $structure, int $offsetX, int $offsetY, int $offsetZ, DynamicBlockListSelection $target): void
	{
		$entitiesTag = $structure->getTag(self::ENTITIES);
		if ($entitiesTag === null) {
			return;
		}
		try {
			if ($entitiesTag instanceof AbstractListTag) {
				$count = $entitiesTag->getLength();
				for ($i = 0; $i < $count; $i++) {
					$entry = $entitiesTag->next();
					if ($entry instanceof CompoundTag) {
						self::ingestMcStructureEntity($entry, $offsetX, $offsetY, $offsetZ, $target);
					}
				}
			} elseif ($entitiesTag instanceof ListTag) {
				foreach ($entitiesTag as $entry) {
					if ($entry instanceof CompoundTag) {
						self::ingestMcStructureEntity($entry, $offsetX, $offsetY, $offsetZ, $target);
					}
				}
			}
		} catch (Throwable $e) {
			EditThread::getInstance()->debug("Skipping malformed McStructure entities: " . $e->getMessage());
		}
	}

	private static function ingestMcStructureEntity(CompoundTag $entry, int $offsetX, int $offsetY, int $offsetZ, DynamicBlockListSelection $target): void
	{
		$entry = AbstractNBT::fromAbstract($entry);
		if (!$entry instanceof CompoundTag) {
			return;
		}
		$nbt = $entry->getCompoundTag(self::ENTITY_NBT);
		if ($nbt === null) {
			// Some exporters put entity fields on the entry itself
			$nbt = clone $entry;
			$nbt->removeTag(self::ENTITY_POS);
		} else {
			$nbt = AbstractNBT::fromAbstract($nbt);
			if (!$nbt instanceof CompoundTag) {
				return;
			}
		}

		$relX = 0.0;
		$relY = 0.0;
		$relZ = 0.0;
		$posTag = $entry->getListTag(self::ENTITY_POS);
		if ($posTag !== null && $posTag->getCount() >= 3) {
			$values = $posTag->getValue();
			$relX = self::listNumber($values[0] ?? null);
			$relY = self::listNumber($values[1] ?? null);
			$relZ = self::listNumber($values[2] ?? null);
		} else {
			$existing = EntityUtils::readPos($nbt);
			if ($existing !== null) {
				// Absolute Pos in nbt — convert into structure-local then selection space
				$relX = $existing[0] - $offsetX;
				$relY = $existing[1] - $offsetY;
				$relZ = $existing[2] - $offsetZ;
			}
		}

		$nbt->setTag(Entity::TAG_POS, new ListTag([
			new DoubleTag($relX),
			new DoubleTag($relY + World::Y_MIN),
			new DoubleTag($relZ)
		]));
		$normalized = EntityUtils::normalizeForBedrock($nbt);
		if ($normalized !== null) {
			$target->addEntity($normalized);
		}
	}

	private static function listNumber(mixed $tag): float
	{
		if ($tag instanceof DoubleTag || $tag instanceof FloatTag) {
			return $tag->getValue();
		}
		return 0.0;
	}

	public static function getNbtSerializer(): AbstractNBTSerializer
	{
		return new LittleEndianAbstractNBTSerializer();
	}
}