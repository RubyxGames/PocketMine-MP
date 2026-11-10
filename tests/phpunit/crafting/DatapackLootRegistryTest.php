<?php

declare(strict_types=1);

namespace pocketmine\crafting;

use PHPUnit\Framework\TestCase;

final class DatapackLootRegistryTest extends TestCase{

	public function testRegisterAndRoll() : void{
		$reg = DatapackLootRegistry::getInstance();
		$reg->register("test_loot", [
			"pools" => [
				[
					"rolls" => 1,
					"entries" => [
						["item" => "minecraft:dirt", "count" => 2, "weight" => 1],
					],
				],
			],
		]);
		self::assertTrue($reg->has("test_loot"));
		$items = $reg->roll("test_loot");
		self::assertNotSame([], $items);
		self::assertSame(2, $items[0]->getCount());
	}
}
