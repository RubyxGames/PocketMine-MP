<?php

namespace pocketmine\world\worldedit\schematic\type;

use pocketmine\block\tile\Tile;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\utils\InternetException;
use pocketmine\world\World;
use pocketmine\world\worldedit\convert\LegacyBlockIdConvertor;
use pocketmine\world\worldedit\convert\TileConvertor;
use pocketmine\world\worldedit\math\BlockOffsetVector;
use pocketmine\world\worldedit\math\BlockVector;
use pocketmine\world\worldedit\schematic\nbt\AbstractByteArrayTag;
use pocketmine\world\worldedit\schematic\nbt\AbstractNBT;
use pocketmine\world\worldedit\schematic\nbt\AbstractNBTSerializer;
use pocketmine\world\worldedit\schematic\nbt\BigEndianAbstractNBTSerializer;
use pocketmine\world\worldedit\selection\DynamicBlockListSelection;
use pocketmine\world\worldedit\thread\EditThread;
use pocketmine\world\worldedit\utils\EntityUtils;
use UnexpectedValueException;

class McEditSchematic extends SchematicType
{
	public const OFFSET_X = "WEOffsetX";
	public const OFFSET_Y = "WEOffsetY";
	public const OFFSET_Z = "WEOffsetZ";
	public const BLOCK_ID = "Blocks";
	public const BLOCK_META = "Data";
	public const TILE_ENTITIES = "TileEntities";
	public const ENTITIES = "Entities";

	public static function readIntoSelection(CompoundTag $nbt, DynamicBlockListSelection $target): void
	{
		if (!LegacyBlockIdConvertor::isAvailable()) {
			throw new InternetException("Couldn't load needed data files");
		}
		$xSize = $nbt->getShort(self::TAG_WIDTH);
		$ySize = $nbt->getShort(self::TAG_HEIGHT);
		$zSize = $nbt->getShort(self::TAG_LENGTH);
		$target->setPoint(new BlockOffsetVector($nbt->getInt(self::OFFSET_X, 0), $nbt->getInt(self::OFFSET_Y, 0) - World::Y_MIN, $nbt->getInt(self::OFFSET_Z, 0)));
		$target->setPos1(new BlockVector(0, World::Y_MIN, 0));
		$target->setPos2(new BlockVector($xSize - 1, World::Y_MIN + $ySize - 1, $zSize - 1));
		$target->getManager()->loadBetween($target->getPos1(), $target->getPos2());

		//"AddBlocks" allows ids over 255
		//this can be ignored as java pre-flattening only had 255 block ids in use and later didn't support block ids at all
		$blockIdData = $nbt->getTag(self::BLOCK_ID);
		$blockMetaData = $nbt->getTag(self::BLOCK_META);

		if (!$blockIdData instanceof AbstractByteArrayTag || !$blockMetaData instanceof AbstractByteArrayTag) {
			throw new UnexpectedValueException("Invalid schematic file");
		}

		$blockIdData->optimizeHighFrequencyAccess();
		$blockMetaData->optimizeHighFrequencyAccess();

		$blockIdChunk = "";
		$blockMetaChunk = "";
		$blockCache = [];
		$ySize += World::Y_MIN;
		$i = 0;
		//McEdit why this weird order?
		for ($y = World::Y_MIN; $y < $ySize; ++$y) {
			for ($z = 0; $z < $zSize; ++$z) {
				for ($x = 0; $x < $xSize; ++$x) {
					if ($i % AbstractByteArrayTag::CHUNK_SIZE === 0) {
						$blockIdChunk = $blockIdData->nextChunk();
						$blockMetaChunk = $blockMetaData->nextChunk();
					}
					$id = ord($blockIdChunk[$i % AbstractByteArrayTag::CHUNK_SIZE]);
					$meta = ord($blockMetaChunk[$i % AbstractByteArrayTag::CHUNK_SIZE]);

					$j = $id << LegacyBlockIdConvertor::METADATA_BITS | $meta;
					if (!isset($blockCache[$j])) {
						$blockCache[$j] = LegacyBlockIdConvertor::convertFromJava($j);
					}

					$target->addBlock($x, $y, $z, $blockCache[$j]);
					$i++;
				}
			}
		}

		$blockIdData->close();
		$blockMetaData->close();

		self::loadTileEntities($nbt, $target);
		self::loadEntities($nbt, $target);
	}

	/**
	 * Classic McEdit/WorldEdit TileEntities: compounds with id + x/y/z (Y in 0..height space).
	 */
	private static function loadTileEntities(CompoundTag $nbt, DynamicBlockListSelection $target): void
	{
		$tiles = $nbt->getListTag(self::TILE_ENTITIES);
		if(!$tiles instanceof ListTag){
			return;
		}
		foreach($tiles as $tile){
			if(!$tile instanceof CompoundTag){
				continue;
			}
			try{
				$id = $tile->getString(Tile::TAG_ID);
				$x = $tile->getInt(Tile::TAG_X);
				$y = $tile->getInt(Tile::TAG_Y) + World::Y_MIN;
				$z = $tile->getInt(Tile::TAG_Z);
				$data = clone $tile;
				$data->setString(Tile::TAG_ID, $id);
				$data->setInt(Tile::TAG_X, $x);
				$data->setInt(Tile::TAG_Y, $y);
				$data->setInt(Tile::TAG_Z, $z);
				$converted = TileConvertor::toBedrock($data, null);
				$target->addTile(AbstractNBT::fromAbstractTile($converted));
			}catch(\Throwable $e){
				EditThread::getInstance()->debug("Skipping malformed McEdit tile: " . $e->getMessage());
			}
		}
	}

	/**
	 * Classic McEdit Entities: compounds with id + Pos doubles (Y in 0..height space).
	 */
	private static function loadEntities(CompoundTag $nbt, DynamicBlockListSelection $target): void
	{
		$entities = $nbt->getListTag(self::ENTITIES);
		if(!$entities instanceof ListTag){
			return;
		}
		foreach($entities as $entity){
			if(!$entity instanceof CompoundTag){
				continue;
			}
			try{
				$data = AbstractNBT::fromAbstract($entity);
				if(!$data instanceof CompoundTag){
					continue;
				}
				$normalized = EntityUtils::normalizeForBedrock($data);
				if($normalized === null){
					continue;
				}
				// Lift Y from schematic 0-based space into world Y_MIN space (Pos only).
				$offset = EntityUtils::offsetCompound($normalized, 0.0, (float) World::Y_MIN, 0.0);
				$target->addEntity($offset);
			}catch(\Throwable $e){
				EditThread::getInstance()->debug("Skipping malformed McEdit entity: " . $e->getMessage());
			}
		}
	}

	public static function getNbtSerializer(): AbstractNBTSerializer
	{
		return new BigEndianAbstractNBTSerializer();
	}
}