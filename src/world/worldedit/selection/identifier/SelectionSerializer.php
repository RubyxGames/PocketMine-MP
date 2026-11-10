<?php

namespace pocketmine\world\worldedit\selection\identifier;

use InvalidArgumentException;
use pocketmine\world\worldedit\utils\ExtendedBinaryStream;

class SelectionSerializer
{
	public static function fastSerialize(SelectionIdentifier $identifier): string
	{
		$stream = new ExtendedBinaryStream();
		$stream->putString($identifier::class);
		$stream->putString($identifier->fastSerialize());
		return $stream->getBuffer();
	}

	public static function fastDeserialize(string $data): SelectionIdentifier
	{
		$stream = new ExtendedBinaryStream($data);
		/**
		 * @noinspection PhpRedundantVariableDocTypeInspection PhpStorm... this is not redundant, you'll complain about it either way
		 * @var class-string<SelectionIdentifier> $class
		 */
		$class = $stream->getString();
		return $class::fastDeserialize($stream->getString());
	}

	/**
	 * @param string $data
	 * @return BlockListSelectionIdentifier
	 */
	public static function mustGetBlockList(string $data): BlockListSelectionIdentifier
	{
		$identifier = self::fastDeserialize($data);
		if ($identifier instanceof BlockListSelectionIdentifier) {
			return $identifier;
		}
		throw new InvalidArgumentException("Expected BlockListSelectionIdentifier, got " . get_class($identifier));
	}
}