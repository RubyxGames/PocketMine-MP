<?php

declare(strict_types=1);

namespace pocketmine\world\worldedit\utils;

use pocketmine\entity\Entity;
use pocketmine\entity\EntityFactory;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;

final class EntityUtils
{
	private function __construct()
	{
	}

	/**
	 * @param CompoundTag|null $compoundTag
	 * @param float            $x
	 * @param float            $y
	 * @param float            $z
	 * @return CompoundTag|null
	 */
	public static function offsetCompound(?CompoundTag $compoundTag, float $x, float $y, float $z): ?CompoundTag
	{
		if ($compoundTag === null) {
			return null;
		}
		$compoundTag = clone $compoundTag;
		$pos = $compoundTag->getListTag(Entity::TAG_POS);
		if ($pos === null || $pos->getCount() < 3) {
			$compoundTag->setTag(Entity::TAG_POS, new ListTag([
				new DoubleTag($x),
				new DoubleTag($y),
				new DoubleTag($z)
			]));
			return $compoundTag;
		}
		$values = $pos->getValue();
		$ox = self::tagAsFloat($values[0] ?? null);
		$oy = self::tagAsFloat($values[1] ?? null);
		$oz = self::tagAsFloat($values[2] ?? null);
		$compoundTag->setTag(Entity::TAG_POS, new ListTag([
			new DoubleTag($ox + $x),
			new DoubleTag($oy + $y),
			new DoubleTag($oz + $z)
		]));
		return $compoundTag;
	}

	/**
	 * Map Java/Sponge/McEdit ids to Bedrock `identifier`, ensure Pos/Rotation, strip UniqueID/UUID.
	 * Returns null when no usable entity id is present.
	 */
	public static function normalizeForBedrock(CompoundTag $nbt): ?CompoundTag
	{
		$nbt = clone $nbt;

		$identifier = null;
		$idTag = $nbt->getTag(EntityFactory::TAG_IDENTIFIER);
		if ($idTag instanceof StringTag) {
			$identifier = $idTag->getValue();
		} elseif (($idTag = $nbt->getTag("Id")) instanceof StringTag) {
			$identifier = $idTag->getValue();
			$nbt->removeTag("Id");
		} elseif (($idTag = $nbt->getTag("id")) instanceof StringTag) {
			$identifier = $idTag->getValue();
			$nbt->removeTag("id");
		} elseif (($idTag = $nbt->getTag(EntityFactory::TAG_LEGACY_ID)) instanceof IntTag) {
			// keep legacy int id for EntityFactory
			$nbt->removeTag("Id", "id", "UniqueID", "UniqueId", "UUID", "uuid");
			self::ensurePosRotation($nbt);
			return $nbt;
		}

		if ($identifier === null || $identifier === "") {
			return null;
		}

		$nbt->setString(EntityFactory::TAG_IDENTIFIER, $identifier);
		$nbt->removeTag("Id", "id", "UniqueID", "UniqueId", "UUID", "uuid");
		self::ensurePosRotation($nbt);
		return $nbt;
	}

	private static function ensurePosRotation(CompoundTag $nbt): void
	{
		$pos = $nbt->getListTag(Entity::TAG_POS);
		if ($pos === null || $pos->getCount() < 3) {
			$nbt->setTag(Entity::TAG_POS, new ListTag([
				new DoubleTag(0.0),
				new DoubleTag(0.0),
				new DoubleTag(0.0)
			]));
		} else {
			// Normalize to DoubleTag list (Java may use floats)
			$values = $pos->getValue();
			$nbt->setTag(Entity::TAG_POS, new ListTag([
				new DoubleTag(self::tagAsFloat($values[0] ?? null)),
				new DoubleTag(self::tagAsFloat($values[1] ?? null)),
				new DoubleTag(self::tagAsFloat($values[2] ?? null))
			]));
		}

		$rotation = $nbt->getListTag(Entity::TAG_ROTATION);
		if ($rotation === null || $rotation->getCount() < 2) {
			$nbt->setTag(Entity::TAG_ROTATION, new ListTag([
				new FloatTag(0.0),
				new FloatTag(0.0)
			]));
		}
	}

	private static function tagAsFloat(mixed $tag): float
	{
		if ($tag instanceof DoubleTag || $tag instanceof FloatTag) {
			return $tag->getValue();
		}
		if ($tag instanceof IntTag) {
			return (float) $tag->getValue();
		}
		return 0.0;
	}

	/**
	 * @return array{0: float, 1: float, 2: float}|null
	 */
	public static function readPos(CompoundTag $nbt): ?array
	{
		$pos = $nbt->getListTag(Entity::TAG_POS);
		if ($pos === null || $pos->getCount() < 3) {
			return null;
		}
		$values = $pos->getValue();
		return [
			self::tagAsFloat($values[0] ?? null),
			self::tagAsFloat($values[1] ?? null),
			self::tagAsFloat($values[2] ?? null)
		];
	}
}
