<?php

declare(strict_types=1);

namespace pocketmine\item;

use PHPUnit\Framework\TestCase;
use pocketmine\data\bedrock\item\SavedItemData;

final class UnknownItemTest extends TestCase{

	public function testPreservesSavedTypeDataAndStackSize() : void{
		$saved = new SavedItemData("minecraft:totally_unknown_item", 3);
		$item = new UnknownItem($saved);

		self::assertSame($saved, $item->getSavedTypeData());
		self::assertSame(1, $item->getMaxStackSize());
		self::assertSame("minecraft:totally_unknown_item", $item->getName());
		self::assertSame(UnknownItem::typeId(), $item->getTypeId());
	}
}
