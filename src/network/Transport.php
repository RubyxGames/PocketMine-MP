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

namespace pocketmine\network;

use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\EntityEventBroadcaster;
use pocketmine\network\mcpe\PacketBroadcaster;

/**
 * Represents a network transport (e.g. RakNet, NetherNet) that provides network interfaces for player connections.
 *
 * Plugins can register additional transports with {@link Network::registerTransport()} during early startup
 * (for example from {@see \pocketmine\plugin\PluginBase::onLoad()} or a server enable callback that runs before
 * network interfaces start). Users then enable the transport by name in `pocketmine.yml` under `transport.name`.
 *
 * Example:
 * ```
 * $server->getNetwork()->registerTransport("myproxy", new MyProxyTransport($server));
 * // pocketmine.yml: transport.name: nethernet,myproxy
 * ```
 */
interface Transport{

	/**
	 * Creates and returns the network interfaces for this transport.
	 *
	 * Broadcasters must be passed to each created NetworkSession so packets can be broadcast across all transports.
	 *
	 * Returning an empty array is allowed when the transport is unavailable (e.g. a missing PHP extension); the server
	 * continues with other active transports and fails only if none start any interface.
	 *
	 * @return NetworkInterface[]
	 * @throws NetworkInterfaceStartException
	 */
	public function createInterfaces(
		PacketBroadcaster $packetBroadcaster,
		EntityEventBroadcaster $entityEventBroadcaster,
		TypeConverter $typeConverter
	) : array;
}
