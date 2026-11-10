<?php

declare(strict_types=1);

namespace pocketmine\world\format\io\region;

use pocketmine\world\format\io\ChunkData;
use pocketmine\world\generator\GeneratorManager;
use pocketmine\world\World;
use pocketmine\world\WorldCreationOptions;
use function count;
use function file_exists;
use function is_dir;
use function scandir;

/**
 * Exports a loaded world to legacy Anvil (.mca) on disk (lossy for modern blocks).
 */
final class AnvilWorldExporter{

	private function __construct(){}

	/**
	 * @throws \InvalidArgumentException
	 */
	public static function export(World $world, string $destPath) : int{
		if(file_exists($destPath)){
			if(!is_dir($destPath)){
				throw new \InvalidArgumentException("Destination \"$destPath\" exists and is not a directory");
			}
			$entries = scandir($destPath) ?: [];
			if(count($entries) > 2){ // . and ..
				throw new \InvalidArgumentException("Destination \"$destPath\" is not empty");
			}
		}

		$genName = $world->getProvider()->getWorldData()->getGenerator();
		$genEntry = GeneratorManager::getInstance()->getGenerator($genName);
		$genClass = $genEntry !== null
			? $genEntry->getGeneratorClass()
			: GeneratorManager::getInstance()->getGenerator("normal")?->getGeneratorClass()
				?? throw new \InvalidArgumentException("No generator available for export");

		WritableAnvil::generate($destPath, $world->getFolderName(), WorldCreationOptions::create()
			->setSeed($world->getSeed())
			->setSpawnPosition($world->getSpawnLocation())
			->setGeneratorClass($genClass)
			->setGeneratorOptions($world->getProvider()->getWorldData()->getGeneratorOptions())
		);

		$provider = new WritableAnvil($destPath, new \PrefixedLogger($world->getServer()->getLogger(), "AnvilExport"));
		$written = 0;
		foreach($world->getLoadedChunks() as $hash => $chunk){
			World::getXZ($hash, $chunkX, $chunkZ);
			$tiles = [];
			foreach($chunk->getTiles() as $tile){
				$tiles[] = $tile->saveNBT();
			}
			$data = new ChunkData($chunk->getSubChunks(), $chunk->isPopulated(), [], $tiles);
			$provider->saveChunk($chunkX, $chunkZ, $data, ~0);
			$written++;
		}
		$provider->close();
		return $written;
	}
}
