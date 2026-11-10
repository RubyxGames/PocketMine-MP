<?php

declare(strict_types=1);

namespace pocketmine\resourcepacks\compression;

/**
 * Result of recompressing a resource pack zip for client download.
 */
final class CompressionResult{

	public function __construct(
		public readonly string $destPath,
		public readonly int $originalBytes,
		public readonly int $compressedBytes,
		public readonly bool $skipped,
	){}
}
