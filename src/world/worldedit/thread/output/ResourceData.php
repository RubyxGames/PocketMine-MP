<?php

namespace pocketmine\world\worldedit\thread\output;

use pocketmine\world\worldedit\convert\BedrockStatePreprocessor;
use pocketmine\world\worldedit\convert\BlockStateConvertor;
use pocketmine\world\worldedit\convert\BlockTagManager;
use pocketmine\world\worldedit\convert\TileConvertor;
use pocketmine\world\worldedit\utils\ConfigManager;
use pocketmine\world\worldedit\utils\ExtendedBinaryStream;
use pocketmine\world\worldedit\utils\RepoManager;
use pocketmine\world\worldedit\world\HeightMapCache;

class ResourceData extends OutputData
{
	public function __construct(private string $rawJTB, private string $rawBTJ) { }

	public function handle(): void { }

	public function putData(ExtendedBinaryStream $stream): void
	{
		$stream->putString($this->rawJTB);
		$stream->putString($this->rawBTJ);
		$stream->putString(BedrockStatePreprocessor::$rawData);
		$stream->putString(BlockTagManager::$rawData);
		$stream->putLong(RepoManager::getVersion());
		BedrockStatePreprocessor::$rawData = "";
		BlockTagManager::$rawData = "";
	}

	public function parseData(ExtendedBinaryStream $stream): void
	{
		BlockStateConvertor::loadResourceData($stream->getString(), $stream->getString());
		BedrockStatePreprocessor::loadResourceData($stream->getString());
		BlockTagManager::loadResourceData($stream->getString());
		TileConvertor::load($stream->getLong());
		HeightMapCache::loadIgnore(ConfigManager::getTerrainIgnored());
	}
}