<?php

declare(strict_types=1);

namespace pocketmine\form;

use pocketmine\player\Player;

/**
 * Callable-backed form with send() helper. Subclasses fill JSON via $data / jsonSerialize().
 */
abstract class BaseForm implements Form{

	/** @var mixed[] */
	protected array $data = [];

	/** @var (callable(Player, mixed): void)|null */
	private $callable;

	/**
	 * @phpstan-param (callable(Player, mixed): void)|null $callable
	 */
	public function __construct(?callable $callable = null){
		$this->callable = $callable;
	}

	public function send(Player $player) : void{
		$player->sendForm($this);
	}

	public function handleResponse(Player $player, mixed $data) : void{
		$this->processData($data);
		if($this->callable !== null){
			($this->callable)($player, $data);
		}
	}

	/**
	 * Validate / normalize response data before the callable runs.
	 *
	 * @throws FormValidationException
	 */
	protected function processData(mixed &$data) : void{
		//NOOP
	}

	/**
	 * @phpstan-return (callable(Player, mixed): void)|null
	 */
	public function getCallable() : ?callable{
		return $this->callable;
	}

	/**
	 * @phpstan-param (callable(Player, mixed): void)|null $callable
	 */
	public function setCallable(?callable $callable) : void{
		$this->callable = $callable;
	}

	/**
	 * @return mixed[]
	 */
	public function jsonSerialize() : array{
		return $this->data;
	}
}
