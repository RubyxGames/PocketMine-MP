<?php

declare(strict_types=1);

namespace pocketmine\world\generator\ender;

use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\math\Vector3;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\Generator;
use pocketmine\world\generator\noise\Simplex;
use pocketmine\world\generator\populator\Populator;
use function abs;

/**
 * Lite End-like terrain for MultiWorld `ender` / `ender_mw` aliases.
 * Ported from CzechPMDevs MultiWorld (PM4) to PM5 block/biome APIs — not a full vanilla End.
 */
class EnderGenerator extends Generator{

	private int $emptyHeight = 32;
	private float $emptyAmplitude = 1.0;
	private float $density = 0.6;

	private Simplex $noiseBase;

	/** @var Populator[] */
	private array $populators = [];

	public function __construct(int $seed, string $preset){
		parent::__construct($seed, $preset);

		$this->random->setSeed($this->seed);
		$this->noiseBase = new Simplex($this->random, 4, 1 / 4, 1 / 64);
		$this->random->setSeed($this->seed);
		$this->populators[] = new EnderPillarPopulator();
	}

	public function generateChunk(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		$this->random->setSeed(0xa6fe78dc ^ ($chunkX << 8) ^ $chunkZ ^ $this->seed);

		$chunk = $world->getChunk($chunkX, $chunkZ) ?? throw new \InvalidArgumentException("Chunk $chunkX $chunkZ does not yet exist");
		$noise = $this->noiseBase->getFastNoise3D(Chunk::EDGE_LENGTH, 128, Chunk::EDGE_LENGTH, 4, 8, 4, $chunkX * Chunk::EDGE_LENGTH, 0, $chunkZ * Chunk::EDGE_LENGTH);
		$endStone = VanillaBlocks::END_STONE()->getStateId();
		$origin = new Vector3(0, 64, 0);

		for($x = 0; $x < Chunk::EDGE_LENGTH; ++$x){
			for($z = 0; $z < Chunk::EDGE_LENGTH; ++$z){
				for($y = 0; $y < 128; ++$y){
					$chunk->setBiomeId($x, $y, $z, BiomeIds::THE_END);

					$noiseValue = (abs($this->emptyHeight - $y) / $this->emptyHeight) * $this->emptyAmplitude - $noise[$x][$z][$y];
					$noiseValue -= 1 - $this->density;
					$distance = $origin->distance(new Vector3($chunkX * Chunk::EDGE_LENGTH + $x, $y / 1.3, $chunkZ * Chunk::EDGE_LENGTH + $z));
					if(($noiseValue < 0 && $distance < 100) || ($noiseValue < -0.2 && $distance > 400)){
						$chunk->setBlockStateId($x, $y, $z, $endStone);
					}
				}
			}
		}
	}

	public function populateChunk(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		$this->random->setSeed(0xa6fe78dc ^ ($chunkX << 8) ^ $chunkZ ^ $this->seed);
		foreach($this->populators as $populator){
			$populator->populate($world, $chunkX, $chunkZ, $this->random);
		}
	}
}
