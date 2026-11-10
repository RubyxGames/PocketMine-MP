<?php

declare(strict_types=1);

namespace pocketmine\world\worldedit;

use PHPUnit\Framework\TestCase;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityFactory;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\world\worldedit\utils\EntityUtils;

final class EntityUtilsTest extends TestCase{

	public function testNormalizeMapsJavaIdAndStripsUuid() : void{
		$nbt = CompoundTag::create()
			->setTag("Id", new StringTag("minecraft:tnt"))
			->setTag("UUID", new StringTag("deadbeef-dead-beef-dead-beefdeadbeef"))
			->setTag(Entity::TAG_POS, new ListTag([
				new DoubleTag(1.5),
				new DoubleTag(2.0),
				new DoubleTag(3.25)
			]));

		$normalized = EntityUtils::normalizeForBedrock($nbt);
		self::assertNotNull($normalized);
		self::assertSame("minecraft:tnt", $normalized->getString(EntityFactory::TAG_IDENTIFIER));
		self::assertNull($normalized->getTag("Id"));
		self::assertNull($normalized->getTag("UUID"));
		self::assertNotNull($normalized->getListTag(Entity::TAG_ROTATION));
	}

	public function testNormalizeReturnsNullWithoutId() : void{
		$nbt = CompoundTag::create()
			->setTag(Entity::TAG_POS, new ListTag([
				new DoubleTag(0.0),
				new DoubleTag(0.0),
				new DoubleTag(0.0)
			]));
		self::assertNull(EntityUtils::normalizeForBedrock($nbt));
	}

	public function testOffsetCompoundShiftsPos() : void{
		$nbt = CompoundTag::create()
			->setTag(Entity::TAG_POS, new ListTag([
				new DoubleTag(1.0),
				new DoubleTag(2.0),
				new DoubleTag(3.0)
			]));
		$offset = EntityUtils::offsetCompound($nbt, 10.0, -5.0, 0.5);
		self::assertNotNull($offset);
		$pos = EntityUtils::readPos($offset);
		self::assertSame([11.0, -3.0, 3.5], $pos);
	}
}
