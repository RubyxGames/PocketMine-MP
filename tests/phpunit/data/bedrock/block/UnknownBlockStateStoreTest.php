<?php

declare(strict_types=1);

namespace pocketmine\data\bedrock\block;

use PHPUnit\Framework\TestCase;

final class UnknownBlockStateStoreTest extends TestCase{

	protected function setUp() : void{
		UnknownBlockStateStore::reset();
	}

	protected function tearDown() : void{
		UnknownBlockStateStore::reset();
	}

	public function testRegisterRoundTripsAndDedupes() : void{
		$data = BlockStateData::current("minecraft:totally_unknown_wave10", []);
		$id1 = UnknownBlockStateStore::register($data);
		$id2 = UnknownBlockStateStore::register(BlockStateData::current("minecraft:totally_unknown_wave10", []));

		self::assertTrue(UnknownBlockStateStore::has($id1));
		self::assertSame($id1, $id2);
		self::assertSame("minecraft:totally_unknown_wave10", UnknownBlockStateStore::get($id1)?->getName());
	}
}
