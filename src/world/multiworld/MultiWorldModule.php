<?php

declare(strict_types=1);

namespace pocketmine\world\multiworld;

use pocketmine\plugin\CorePluginHost;
use pocketmine\Server;
use pocketmine\world\generator\GeneratorManager;
use pocketmine\world\generator\GeneratorManagerEntry;
use pocketmine\world\multiworld\gamerule\GameRuleListener;
use function strtolower;

/**
 * Core MultiWorld module (ported from CzechPMDevs MultiWorld, cleaned for PM5).
 *
 * Provides gamerule enforcement and generator alias resolution.
 * Commands live in WorldCommand / GameRuleCommand.
 */
final class MultiWorldModule{

	private static bool $enabled = false;

	private function __construct(){}

	public static function enable(Server $server, CorePluginHost $host) : void{
		if(self::$enabled){
			return;
		}
		self::$enabled = true;
		$server->getPluginManager()->registerEvents(new GameRuleListener(), $host);
		$server->getLogger()->debug("MultiWorld module enabled (gamerules + /world|/mw|/multiworld|/gamerule)");
	}

	public static function isEnabled() : bool{
		return self::$enabled;
	}

	/**
	 * Resolve MultiWorld-style generator aliases to a registered core generator.
	 */
	public static function resolveGenerator(string $name) : ?GeneratorManagerEntry{
		$mapped = match(strtolower($name)){
			"", "normal", "default", "classic", "basic", "custom", "normal_mw" => "normal",
			"superflat", "flat" => "flat",
			"nether", "hell", "nether_mw", "nether_old" => "nether",
			"end", "ender", "ender_mw" => "ender",
			"empty", "emptyworld", "void" => "void",
			"sb", "skyblock" => "skyblock",
			default => strtolower($name)
		};
		return GeneratorManager::getInstance()->getGenerator($mapped);
	}
}
