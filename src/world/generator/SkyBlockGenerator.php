<?php

declare(strict_types=1);

namespace pocketmine\world\generator;

use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\math\Vector3;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\PalettedBlockArray;
use pocketmine\world\format\SubChunk;
use pocketmine\world\generator\object\OakTree;

/**
 * Empty world with a small starter island at (256, 68, 256).
 * Compatible with worlds created by CzechPMDevs MultiWorld `skyblock` generator.
 */
class SkyBlockGenerator extends Generator{

	public function generateChunk(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		$chunk = new Chunk([], false);
		$biomeArray = new PalettedBlockArray(BiomeIds::PLAINS);
		foreach($chunk->getSubChunks() as $y => $subChunk){
			$chunk->setSubChunk($y, new SubChunk(Block::EMPTY_STATE_ID, [], clone $biomeArray));
		}
		$world->setChunk($chunkX, $chunkZ, $chunk);
	}

	public function populateChunk(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		if($chunkX !== 16 || $chunkZ !== 16){
			return;
		}
		$center = new Vector3(256, 68, 256);
		for($x = -1; $x <= 1; $x++){
			for($y = -1; $y <= 1; $y++){
				for($z = -1; $z <= 1; $z++){
					foreach([
						$center->add($x, $y, $z),
						$center->add(3 + $x, $y, $z),
						$center->add($x, $y, -3 + $z),
					] as $pos){
						$block = ((int) $pos->y === 69) ? VanillaBlocks::GRASS() : VanillaBlocks::DIRT();
						$world->setBlockAt((int) $pos->x, (int) $pos->y, (int) $pos->z, $block);
					}
				}
			}
		}
		$chest = $center->add(0, 2, -4);
		$world->setBlockAt((int) $chest->x, (int) $chest->y, (int) $chest->z, VanillaBlocks::CHEST());
		$treePos = $center->add(4, 2, 1);
		$txn = (new OakTree())->getBlockTransaction($world, (int) $treePos->x, (int) $treePos->y, (int) $treePos->z, $this->random);
		$txn?->apply();
		$world->setBlockAt((int) $center->x, (int) $center->y, (int) $center->z, VanillaBlocks::BEDROCK());
	}
}
