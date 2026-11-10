<?php

declare(strict_types=1);

namespace pocketmine\sessionrecord;

use function dirname;
use function fclose;
use function fflush;
use function filesize;
use function fopen;
use function fwrite;
use function is_dir;
use function is_resource;
use function json_encode;
use function mkdir;
use function microtime;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Append-only JSONL sink for one recording session.
 */
final class JsonlSessionSink{

	/** @var resource */
	private $handle;
	private int $bytesWritten = 0;
	private bool $closed = false;

	public function __construct(
		private readonly string $path,
		private readonly int $maxBytes
	){
		$dir = dirname($path);
		if(!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)){
			throw new \RuntimeException("Failed to create session recording directory: $dir");
		}
		$handle = fopen($path, "ab");
		if($handle === false){
			throw new \RuntimeException("Failed to open session recording file: $path");
		}
		$this->handle = $handle;
		$size = @filesize($path);
		$this->bytesWritten = $size === false ? 0 : $size;
	}

	public function getPath() : string{
		return $this->path;
	}

	public function isClosed() : bool{
		return $this->closed;
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	public function write(string $type, array $payload = []) : bool{
		if($this->closed){
			return false;
		}
		if($this->maxBytes > 0 && $this->bytesWritten >= $this->maxBytes){
			$this->writeRaw([
				"ts" => microtime(true),
				"type" => "limit_reached",
				"max_file_mb" => $this->maxBytes / (1024 * 1024),
			]);
			$this->close();
			return false;
		}

		$line = [
			"ts" => microtime(true),
			"type" => $type,
		] + $payload;
		return $this->writeRaw($line);
	}

	/**
	 * @param array<string, mixed> $line
	 */
	private function writeRaw(array $line) : bool{
		try{
			$json = json_encode($line, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		}catch(\JsonException $e){
			return false;
		}
		$out = $json . "\n";
		$written = fwrite($this->handle, $out);
		if($written === false){
			return false;
		}
		$this->bytesWritten += $written;
		return true;
	}

	public function close() : void{
		if($this->closed){
			return;
		}
		$this->closed = true;
		if(is_resource($this->handle)){
			fflush($this->handle);
			fclose($this->handle);
		}
	}
}
