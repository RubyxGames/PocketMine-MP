<?php

namespace pocketmine\world\worldedit\convert;

use InvalidArgumentException;
use pocketmine\world\worldedit\convert\tile\BannerTileConvertor;
use pocketmine\world\worldedit\convert\tile\BedTileConvertor;
use pocketmine\world\worldedit\convert\tile\BellTileConvertor;
use pocketmine\world\worldedit\convert\tile\ChestTileConvertor;
use pocketmine\world\worldedit\convert\tile\ContainerTileConvertor;
use pocketmine\world\worldedit\convert\tile\CopyingTileConvertor;
use pocketmine\world\worldedit\convert\tile\FlowerPotTileConvertor;
use pocketmine\world\worldedit\convert\tile\FurnaceTileConvertor;
use pocketmine\world\worldedit\convert\tile\JukeboxTileConvertor;
use pocketmine\world\worldedit\convert\tile\LecternTileConvertor;
use pocketmine\world\worldedit\convert\tile\MobHeadTileConvertor;
use pocketmine\world\worldedit\convert\tile\NoteBlockTileConvertor;
use pocketmine\world\worldedit\convert\tile\ShulkerBoxTileConvertor;
use pocketmine\world\worldedit\convert\tile\SignConvertor;
use pocketmine\world\worldedit\convert\tile\TileConvertorPiece;
use pocketmine\world\worldedit\thread\EditThread;
use pocketmine\world\worldedit\utils\BlockParser;
use pocketmine\world\worldedit\utils\RepoManager;
use pocketmine\block\tile\Tile;
use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\nbt\tag\CompoundTag;
use Throwable;

class TileConvertor
{
	public const PREPROCESSED_TYPE = "WorldEditTileType";

	/**
	 * Still missing / deferred:
	 * Beehive, Bee Nest, Soul Campfire, Piston, End Portal, Command Block,
	 * End Gateway, Structure Block, Jigsaw, Nether Reactor, Conduit, Lodestone,
	 * Chiseled Bookshelf (1.20), Item Frame (entity in java).
	 */
	/**
	 * @var array<string, TileConvertorPiece>
	 */
	private static array $convertors = [];

	/**
	 * @param CompoundTag      $tile
	 * @param CompoundTag|null $extraData
	 * @return CompoundTag|null
	 */
	public static function toBedrock(CompoundTag $tile, ?CompoundTag $extraData): ?CompoundTag
	{
		//some of these aren't actually part of pmmp yet, but plugins might use them
		if ($extraData !== null) {
			foreach ($extraData->getValue() as $key => $value) {
				if ($key === Tile::TAG_ID || $key === self::PREPROCESSED_TYPE) {
					continue;
				}
				$tile->setTag($key, $value);
			}
		}
		if (!isset(self::$convertors[$tile->getString(Tile::TAG_ID)])) {
			EditThread::getInstance()->debug("Found unknown tile " . $tile->getString(Tile::TAG_ID));
			return null;
		}
		if ($extraData !== null && $extraData->getString(self::PREPROCESSED_TYPE) !== self::$convertors[$tile->getString(Tile::TAG_ID)]::class) {
			throw new InvalidArgumentException("Preprocessed tile type doesn't match");
		}
		try {
			self::$convertors[$tile->getString(Tile::TAG_ID)]->toBedrock($tile);
			return $tile;
		} catch (Throwable $exception) {
			EditThread::getInstance()->debug("Found malformed tile " . $tile->getString(Tile::TAG_ID) . ": " . $exception->getMessage());
		}
		return null;
	}

	/**
	 * @param CompoundTag $tile
	 * @param string      $state
	 * @return bool Whether the tile should be included
	 */
	public static function toJava(CompoundTag $tile, string &$state): bool
	{
		if (!isset(self::$convertors[$tile->getString(Tile::TAG_ID)])) {
			EditThread::getInstance()->debug("Found unknown tile " . $tile->getString(Tile::TAG_ID));
			return false;
		}
		$converter = self::$convertors[$tile->getString(Tile::TAG_ID)];
		try {
			$newState = $converter->toJava($tile, BlockParser::fromStateString($state, RepoManager::getVersion()));
			if ($newState !== null) {
				$state = BlockParser::toStateString($newState);
			}
		} catch (Throwable $exception) {
			EditThread::getInstance()->debug("Found malformed tile " . $tile->getString(Tile::TAG_ID) . ": " . $exception->getMessage());
		}
		return $converter->hasJavaCounterpart();
	}

	/**
	 * @param BlockStateData $state
	 * @return CompoundTag|null
	 */
	public static function preprocessTileState(BlockStateData $state): ?CompoundTag
	{
		if (isset(self::$convertors[$state->getName()])) {
			return self::$convertors[$state->getName()]->preprocessTileState($state)?->setString(self::PREPROCESSED_TYPE, self::$convertors[$state->getName()]::class);
		}
		return null;
	}

	public static function load(int $version): void
	{
		/**
		 * @var TileConvertorPiece $convertor
		 */
		foreach ([
					 new ChestTileConvertor("Chest", "minecraft:chest", "minecraft:trapped_chest"),
					 new ContainerTileConvertor("EnderChest", "minecraft:ender_chest"),
					 new ContainerTileConvertor("Dispenser", "minecraft:dispenser"),
					 new ContainerTileConvertor("Dropper", "minecraft:dropper"),
					 new ContainerTileConvertor("Hopper", "minecraft:hopper"),
					 new ContainerTileConvertor("Barrel", "minecraft:barrel"),
					 new ShulkerBoxTileConvertor("ShulkerBox", "minecraft:shulker_box"),
					 new SignConvertor("Sign", "minecraft:sign"),
					 new CopyingTileConvertor("Comparator", "minecraft:comparator"),
					 new CopyingTileConvertor("DaylightDetector", "minecraft:daylight_detector"),
					 new CopyingTileConvertor("EnchantTable", "minecraft:enchanting_table"),
					 new FurnaceTileConvertor("Furnace", "minecraft:furnace"),
					 new FurnaceTileConvertor("BlastFurnace", "minecraft:blast_furnace"),
					 new FurnaceTileConvertor("Smoker", "minecraft:smoker"),
					 new NoteBlockTileConvertor("Music", "minecraft:noteblock"),
					 new JukeboxTileConvertor("Jukebox", "RecordPlayer", "minecraft:jukebox"),
					 new BellTileConvertor("Bell", "minecraft:bell"),
					 new LecternTileConvertor("Lectern", "minecraft:lectern"),
					 new CopyingTileConvertor("Beacon", "minecraft:beacon"),
					 new CopyingTileConvertor("MobSpawner", "minecraft:mob_spawner"),
					 new ContainerTileConvertor("BrewingStand", "minecraft:brewing_stand"),
					 new CopyingTileConvertor("Campfire", "minecraft:campfire"),
					 new CopyingTileConvertor("Cauldron", "minecraft:cauldron")
				 ] as $convertor) {
			foreach ($convertor->getIdentifiers() as $identifier) {
				self::$convertors[$identifier] = $convertor;
			}
		}

		if ($version >= 18042891) { //Initial support for extra tile data (1.19.80.11)
			/**
			 * @var TileConvertorPiece $convertor
			 */
			foreach ([
						 new BannerTileConvertor("Banner", "minecraft:banner"),
						 new BedTileConvertor("Bed", "minecraft:bed"),
						 new MobHeadTileConvertor("Skull", "minecraft:skull"),
						 new FlowerPotTileConvertor("FlowerPot", "minecraft:flower_pot")
					 ] as $convertor) {
				foreach ($convertor->getIdentifiers() as $identifier) {
					self::$convertors[$identifier] = $convertor;
				}
			}
		} else {
			EditThread::getInstance()->getLogger()->debug("Extra tile data not supported");
		}
	}

	/**
	 * @param string $item Java or Bedrock item name
	 * @return string|null Java tile name
	 */
	public static function itemToTileName(string $item): ?string
	{
		if (isset(self::$convertors[$item])) {
			return $item;
		}
		if (str_ends_with($item, "sign")) { //wooden variants
			return "minecraft:sign";
		}
		if (str_ends_with($item, "shulker_box")) { //colored variants + undyed from bedrock
			return "minecraft:shulker_box";
		}
		return null;
	}
}