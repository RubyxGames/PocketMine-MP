<?php

namespace pocketmine\world\worldedit\convert\tile;

use pocketmine\block\tile\Lectern;
use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\worldedit\convert\ItemConvertor;
use pocketmine\world\worldedit\schematic\nbt\AbstractNBT;

/**
 * Lite Java↔Bedrock lectern NBT (page + book item).
 */
class LecternTileConvertor extends TileConvertorPiece
{
	private const JAVA_PAGE = "Page";
	private const JAVA_BOOK = "Book";

	public function preprocessTileState(BlockStateData $state): ?CompoundTag
	{
		return null;
	}

	public function toBedrock(CompoundTag $tile): void
	{
		parent::toBedrock($tile);
		$page = $tile->getInt(self::JAVA_PAGE, $tile->getInt(Lectern::TAG_PAGE, 0));
		$tile->setInt(Lectern::TAG_PAGE, $page);
		$tile->removeTag(self::JAVA_PAGE);

		$bookTag = $tile->getTag(self::JAVA_BOOK) ?? $tile->getTag(Lectern::TAG_BOOK);
		if($bookTag !== null){
			$book = AbstractNBT::fromAbstract($bookTag);
			if($book instanceof CompoundTag){
				$converted = ItemConvertor::convertItemBedrock($book);
				if($converted !== null){
					$tile->setTag(Lectern::TAG_BOOK, $converted);
					$tile->setByte(Lectern::TAG_HAS_BOOK, 1);
				}
			}
			$tile->removeTag(self::JAVA_BOOK);
		}else{
			$tile->setByte(Lectern::TAG_HAS_BOOK, 0);
		}
	}

	public function toJava(CompoundTag $tile, BlockStateData $state): ?BlockStateData
	{
		parent::toJava($tile, $state);
		$tile->setInt(self::JAVA_PAGE, $tile->getInt(Lectern::TAG_PAGE, 0));
		$book = $tile->getCompoundTag(Lectern::TAG_BOOK);
		if($book !== null){
			$converted = ItemConvertor::convertItemJava($book);
			if($converted !== null){
				$tile->setTag(self::JAVA_BOOK, $converted);
			}
		}
		$tile->removeTag(Lectern::TAG_HAS_BOOK, Lectern::TAG_PAGE, Lectern::TAG_BOOK, Lectern::TAG_TOTAL_PAGES);
		return null;
	}
}
