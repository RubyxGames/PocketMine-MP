<?php

declare(strict_types=1);

namespace pocketmine\world\generator\ender;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\generator\populator\Populator;
use function cos;
use function deg2rad;
use function sin;
use const M_PI;

/**
 * Sparse obsidian pillars for {@link EnderGenerator} (lite MultiWorld port).
 */
final class EnderPillarPopulator implements Populator{

	public function populate(ChunkManager $world, int $chunkX, int $chunkZ, Random $random) : void{
		if($random->nextRange(0, 100) >= 10){
			return;
		}

		$obsidian = VanillaBlocks::OBSIDIAN();
		$x = $random->nextRange($chunkX * 16, $chunkX * 16 + 15);
		$z = $random->nextRange($chunkZ * 16, $chunkZ * 16 + 15);
		$y = $this->highestEndStone($world, $x, $z);
		if($y < 0){
			return;
		}

		$height = $random->nextRange(28, 50);
		for($ny = $y; $ny < $y + $height; $ny++){
			for($r = 0.5; $r < 5; $r += 0.5){
				$nd = 180 / (M_PI * $r);
				for($d = 0.0; $d < 360; $d += $nd){
					$world->setBlockAt(
						(int) ($x + (cos(deg2rad($d)) * $r)),
						$ny,
						(int) ($z + (sin(deg2rad($d)) * $r)),
						$obsidian
					);
				}
			}
		}
	}

	private function highestEndStone(ChunkManager $world, int $x, int $z) : int{
		for($y = 127; $y >= 0; --$y){
			if($world->getBlockAt($x, $y, $z)->getTypeId() === BlockTypeIds::END_STONE){
				return $y;
			}
		}
		return -1;
	}
}
