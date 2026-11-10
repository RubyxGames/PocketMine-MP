<?php

namespace pocketmine\world\worldedit\pattern\type;

use pocketmine\world\worldedit\pattern\block\BlockType;
use pocketmine\world\worldedit\pattern\Pattern;
use pocketmine\world\worldedit\utils\ExtendedBinaryStream;

trait BlockPatternData
{
	/**
	 * @param BlockType $block
	 * @param Pattern[] $pieces
	 */
	public function __construct(private BlockType $block, array $pieces)
	{
		parent::__construct($pieces);
	}

	/**
	 * @param ExtendedBinaryStream $stream
	 */
	public function putData(ExtendedBinaryStream $stream): void
	{
		$stream->putString($this->block->fastSerialize());
	}

	/**
	 * @param ExtendedBinaryStream $stream
	 */
	public function parseData(ExtendedBinaryStream $stream): void
	{
		$this->block = BlockType::fastDeserialize($stream->getString());
	}
}