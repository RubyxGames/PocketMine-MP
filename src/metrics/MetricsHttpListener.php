<?php

declare(strict_types=1);

namespace pocketmine\metrics;

use function fclose;
use function fwrite;
use function is_resource;
use function microtime;
use function stream_get_contents;
use function stream_select;
use function stream_set_blocking;
use function stream_socket_accept;
use function stream_socket_server;
use function strlen;
use function str_contains;
use function str_starts_with;
use const STREAM_SERVER_BIND;
use const STREAM_SERVER_LISTEN;

/**
 * Tiny non-blocking HTTP scrape listener for GET /metrics.
 */
final class MetricsHttpListener{

	/** @var resource|null */
	private $socket = null;

	public function __construct(
		private string $bind,
		private \Closure $bodyProvider
	){
		$errno = 0;
		$errstr = "";
		$socket = @stream_socket_server("tcp://" . $this->bind, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN);
		if($socket === false){
			throw new \RuntimeException("Failed to bind metrics listener on {$this->bind}: $errstr ($errno)");
		}
		stream_set_blocking($socket, false);
		$this->socket = $socket;
	}

	public function tick() : void{
		if(!is_resource($this->socket)){
			return;
		}
		$read = [$this->socket];
		$write = null;
		$except = null;
		if(@stream_select($read, $write, $except, 0) > 0){
			$client = @stream_socket_accept($this->socket, 0);
			if(is_resource($client)){
				$this->handleClient($client);
			}
		}
	}

	/**
	 * @param resource $client
	 */
	private function handleClient($client) : void{
		stream_set_blocking($client, false);
		$request = "";
		$deadline = microtime(true) + 0.05;
		while(microtime(true) < $deadline && !str_contains($request, "\r\n\r\n")){
			$chunk = @stream_get_contents($client, 1024);
			if($chunk === false || $chunk === ""){
				break;
			}
			$request .= $chunk;
			if(strlen($request) > 8192){
				break;
			}
		}

		$body = ($this->bodyProvider)();
		if(str_starts_with($request, "GET /metrics")){
			$response = "HTTP/1.0 200 OK\r\nContent-Type: text/plain; version=0.0.4\r\nContent-Length: " .
				strlen($body) . "\r\nConnection: close\r\n\r\n" . $body;
		}else{
			$msg = "Not Found\n";
			$response = "HTTP/1.0 404 Not Found\r\nContent-Length: " . strlen($msg) . "\r\nConnection: close\r\n\r\n" . $msg;
		}
		@fwrite($client, $response);
		fclose($client);
	}

	public function close() : void{
		if(is_resource($this->socket)){
			fclose($this->socket);
			$this->socket = null;
		}
	}
}
