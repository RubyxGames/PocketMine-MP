<?php

declare(strict_types=1);

namespace pocketmine\world\worldedit;

use pocketmine\permission\DefaultPermissions;
use pocketmine\permission\Permission;
use pocketmine\permission\PermissionManager;
use pocketmine\plugin\CorePluginHost;
use pocketmine\Server;
use pocketmine\world\worldedit\command\KnownPermissions;

/**
 * Boots the core WorldEdit module (former EasyEdit plugin).
 */
final class WorldEditModule{

	private static bool $enabled = false;

	private function __construct(){}

	public static function enable(Server $server, CorePluginHost $host) : void{
		if(self::$enabled){
			return;
		}
		self::registerPermissions();
		WorldEdit::enable($server, $host);
		self::$enabled = true;
	}

	public static function disable() : void{
		if(!self::$enabled){
			return;
		}
		WorldEdit::disable();
		self::$enabled = false;
	}

	public static function isEnabled() : bool{
		return self::$enabled;
	}

	private static function registerPermissions() : void{
		$pm = PermissionManager::getInstance();
		$op = $pm->getPermission(DefaultPermissions::ROOT_OPERATOR);
		$user = $pm->getPermission(DefaultPermissions::ROOT_USER);
		if($op === null || $user === null){
			return;
		}

		$defs = [
			[KnownPermissions::PERMISSION_INFO, true],
			[KnownPermissions::PERMISSION_EDIT, false],
			[KnownPermissions::PERMISSION_GENERATE, false],
			[KnownPermissions::PERMISSION_HISTORY, false],
			[KnownPermissions::PERMISSION_HISTORY_OTHER, false],
			[KnownPermissions::PERMISSION_CLIPBOARD, false],
			[KnownPermissions::PERMISSION_READDISK, false],
			[KnownPermissions::PERMISSION_WRITEDISK, false],
			[KnownPermissions::PERMISSION_MANAGE, false],
			[KnownPermissions::PERMISSION_SELECT, false],
			[KnownPermissions::PERMISSION_BRUSH, false],
			[KnownPermissions::PERMISSION_ROD, false],
			[KnownPermissions::PERMISSION_UTIL, false],
		];

		foreach($defs as [$name, $everyone]){
			if($pm->getPermission($name) !== null){
				continue;
			}
			$parent = $everyone ? $user : $op;
			DefaultPermissions::registerPermission(new Permission($name), [$parent]);
		}
	}
}
