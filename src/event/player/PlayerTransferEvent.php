<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\event\player;

use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\lang\Translatable;
use pocketmine\network\mcpe\protocol\types\GatheringJoinInfo;
use pocketmine\player\Player;

/**
 * Called when a player attempts to be transferred to another server, e.g. by using /transferserver.
 *
 * For NetherNet destinations, address/port should target the destination signaling endpoint (see docs/nethernet.md).
 */
class PlayerTransferEvent extends PlayerEvent implements Cancellable{
	use CancellableTrait;

	public function __construct(
		Player $player,
		protected string $address,
		protected int $port,
		protected Translatable|string $message,
		protected bool $reloadWorld = false,
		protected ?GatheringJoinInfo $gatheringsConfig = null
	){
		$this->player = $player;
	}

	/**
	 * Returns the destination server address. This could be an IP or a domain name.
	 */
	public function getAddress() : string{
		return $this->address;
	}

	/**
	 * Sets the destination server address.
	 */
	public function setAddress(string $address) : void{
		$this->address = $address;
	}

	/**
	 * Returns the destination server port.
	 */
	public function getPort() : int{
		return $this->port;
	}

	/**
	 * Sets the destination server port.
	 */
	public function setPort(int $port) : void{
		$this->port = $port;
	}

	/**
	 * Returns the disconnect reason shown in the server log and on the console.
	 */
	public function getMessage() : Translatable|string{
		return $this->message;
	}

	/**
	 * Sets the disconnect reason shown in the server log and on the console.
	 */
	public function setMessage(Translatable|string $message) : void{
		$this->message = $message;
	}

	/**
	 * Whether the client should reload the world after transfer (TransferPacket flag since 1.21.30).
	 */
	public function isReloadWorld() : bool{
		return $this->reloadWorld;
	}

	public function setReloadWorld(bool $reloadWorld) : void{
		$this->reloadWorld = $reloadWorld;
	}

	/**
	 * Optional gatherings/experience join metadata (TransferPacket since 1.26.40). Null omits the field.
	 */
	public function getGatheringsConfig() : ?GatheringJoinInfo{
		return $this->gatheringsConfig;
	}

	public function setGatheringsConfig(?GatheringJoinInfo $gatheringsConfig) : void{
		$this->gatheringsConfig = $gatheringsConfig;
	}
}
