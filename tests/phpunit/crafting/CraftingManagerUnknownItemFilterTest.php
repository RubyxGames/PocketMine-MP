<?php

declare(strict_types=1);

namespace pocketmine\crafting;

use PHPUnit\Framework\TestCase;
use pocketmine\crafting\json\ItemStackData;
use pocketmine\item\UnknownItem;

/**
 * Unimplemented bedrock-data items must not enter creative/recipes as UnknownItem
 * (that previously crashed CreativeInventoryCache via unmapped blockstates).
 */
final class CraftingManagerUnknownItemFilterTest extends TestCase{

	public function testUnimplementedBlockItemFilteredFromCreativeLoader() : void{
		$item = CraftingManagerFromDataHelper::deserializeItemStack(new ItemStackData("minecraft:shelf_mushroom"));

		self::assertNull($item, "unimplemented creative block-items must be skipped, not UnknownItem");
		self::assertFalse($item instanceof UnknownItem);
	}
}
