<?php

namespace pocketmine\world\worldedit\world\blockupdate;

use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\UpdateSubChunkBlocksPacketEntry;

class InjectingData
{
	private ByteBufferWriter $injection;
	private int $blockCount = 0;
	private BlockPosition $position;

	public function __construct(int $x, int $y, int $z)
	{
		$this->position = new BlockPosition($x, $y, $z);
		$this->injection = new ByteBufferWriter();
	}

	public function writeBlock(int $x, int $y, int $z, int $id): void
	{
		$this->blockCount++;
		$entry = UpdateSubChunkBlocksPacketEntry::simple(
			new BlockPosition($x, $y, $z),
			TypeConverter::getInstance()->getBlockTranslator()->internalIdToNetworkId($id)
		);
		$entry->write($this->injection, ProtocolInfo::CURRENT_PROTOCOL);
	}

	public function toProtocol(): string
	{
		$serializer = new ByteBufferWriter();
		CommonTypes::putBlockPosition($serializer, $this->position, ProtocolInfo::CURRENT_PROTOCOL >= ProtocolInfo::PROTOCOL_1_26_10);
		VarInt::writeUnsignedInt($serializer, $this->blockCount);
		$serializer->writeByteArray($this->injection->getData());
		VarInt::writeUnsignedInt($serializer, 0);

		return $serializer->getData();
	}
}
