<?php

declare(strict_types=1);

namespace pocketmine\resourcepacks\compression;

use pocketmine\resourcepacks\ResourcePackException;

interface ResourcePackCompressor{

	/**
	 * Recompresses $sourceZip into $destZip using DEFLATE at $level.
	 *
	 * @throws ResourcePackException
	 */
	public function compress(string $sourceZip, string $destZip, int $level = 9) : CompressionResult;
}
