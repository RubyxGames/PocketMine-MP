<?php

namespace pocketmine\world\worldedit\thread\chunk;

use pocketmine\world\worldedit\thread\EditThread;
use pocketmine\world\worldedit\thread\output\ChunkRequestData;
use pocketmine\world\worldedit\utils\ExtendedBinaryStream;
use pocketmine\world\worldedit\world\ChunkInformation;
use pocketmine\world\World;

class ChunkRequestManager
{
	public const MAX_REQUEST = 16; //max concurrent requests
	/**
	 * @var ChunkRequest[]
	 */
	private static array $queue = [];
	private static int $currentRequests = 0;
	private static ?ChunkHandler $handler = null;

	public static function setHandler(ChunkHandler $handler): void
	{
		self::clear();
		self::$handler = $handler;
	}

	public static function addRequest(ChunkRequest $request): void
	{
		if (self::$currentRequests >= self::MAX_REQUEST) {
			self::$queue[] = $request;
			return;
		}
		EditThread::getInstance()->sendOutput(new ChunkRequestData($request));
		self::$currentRequests++;
	}

	public static function handleInput(string $data, ?int $payload): void
	{
		if (self::$handler === null) {
			EditThread::getInstance()->debug("Received unexpected chunk data, probably a cancelled request");
			return;
		}
		$stream = new ExtendedBinaryStream($data);
		self::$handler->handleInput(World::chunkHash($stream->getInt(), $stream->getInt()), ChunkInformation::readFrom($stream), $payload);
	}

	public static function markAsDone(): void
	{
		self::$currentRequests--;
		if (self::$currentRequests < self::MAX_REQUEST && self::$queue !== []) {
			self::addRequest(array_shift(self::$queue));
		}
	}

	public static function clear(): void
	{
		if (self::$handler === null) {
			return;
		}
		self::$handler->clear();
		self::$handler = null;
		self::$queue = [];
		self::$currentRequests = 0;
	}
}