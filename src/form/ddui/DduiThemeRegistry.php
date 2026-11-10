<?php

declare(strict_types=1);

namespace pocketmine\form\ddui;

use pocketmine\resourcepacks\ResourcePackManager;
use pocketmine\Server;
use pocketmine\ServerConfigGroup;
use pocketmine\utils\Utils;
use function count;
use function is_array;
use function is_string;
use function strtolower;
use function trim;

/**
 * DDUI Theme API — presets in {@code pocketmine.yml} + styles discovered in {@code RP/ddui/styling/*.json}.
 */
final class DduiThemeRegistry{
	/** @var array<string, DduiThemeStyle> styleId (lower) => style */
	private static array $discovered = [];

	/** @var array<string, DduiThemePreset> alias (lower) => preset */
	private static array $presets = [];

	private static string $defaultAlias = "rubyx:classic";

	private function __construct(){
	}

	public static function bootstrap(Server $server) : void{
		self::loadPresetsFromConfig($server->getConfigGroup(), $server->getLogger());
		self::reloadFromPacks($server->getResourcePackManager(), $server->getLogger());
	}

	public static function reloadFromPacks(ResourcePackManager $manager, \Logger $logger) : void{
		self::$discovered = [];
		foreach(DduiThemeScanner::scan($manager, $logger) as $style){
			self::$discovered[strtolower($style->getStyleId())] = $style;
		}
		DduiScreenScanner::reloadFromPacks($manager, $logger);
		self::validatePresets($logger);
	}

	private static function loadPresetsFromConfig(ServerConfigGroup $config, \Logger $logger) : void{
		self::$presets = [];
		$default = trim($config->getPropertyString("ddui.default-theme", self::$defaultAlias));
		if($default !== ""){
			self::$defaultAlias = $default;
		}
		$themes = $config->getProperty("ddui.themes", []);
		if(!is_array($themes)){
			$logger->warning("ddui.themes must be a map in pocketmine.yml");
			self::installDefaultPresets();

			return;
		}
		foreach(Utils::stringifyKeys($themes) as $alias => $row){
			if(!is_array($row)){
				continue;
			}
			$styleId = "";
			if(isset($row["style-id"])){
				$styleId = is_string($row["style-id"]) ? trim($row["style-id"]) : "";
			}elseif(isset($row["style_id"])){
				$styleId = is_string($row["style_id"]) ? trim($row["style_id"]) : "";
			}
			$pack = null;
			if(isset($row["resource_pack"]) && is_string($row["resource_pack"]) && $row["resource_pack"] !== ""){
				$pack = $row["resource_pack"];
			}elseif(isset($row["resource-pack"]) && is_string($row["resource-pack"]) && $row["resource-pack"] !== ""){
				$pack = $row["resource-pack"];
			}
			self::$presets[strtolower((string) $alias)] = new DduiThemePreset((string) $alias, $styleId, $pack);
		}
		if(count(self::$presets) === 0){
			self::installDefaultPresets();
		}
	}

	private static function installDefaultPresets() : void{
		self::$presets = [
			"rubyx:classic" => new DduiThemePreset("rubyx:classic", "", null),
		];
	}

	private static function validatePresets(\Logger $logger) : void{
		foreach(self::$presets as $preset){
			$id = $preset->getStyleId();
			if($id === ""){
				continue;
			}
			$key = strtolower($id);
			if(!isset(self::$discovered[$key])){
				$logger->warning(
					"DDUI theme preset \"{$preset->getAlias()}\" references style \"$id\" "
					. "but no matching ddui/styling/*.json was found in resource_stack (pack \""
					. ($preset->getResourcePack() ?? "?") . "\")"
				);
			}
		}
	}

	public static function getDefaultAlias() : string{
		return self::$defaultAlias;
	}

	/**
	 * @return list<DduiThemePreset>
	 */
	public static function getPresets() : array{
		return array_values(self::$presets);
	}

	/**
	 * @return list<DduiThemeStyle>
	 */
	public static function getDiscoveredStyles() : array{
		return array_values(self::$discovered);
	}

	public static function resolvePreset(?string $alias) : DduiThemePreset{
		$key = strtolower(trim($alias ?? self::$defaultAlias));
		return self::$presets[$key] ?? self::$presets[strtolower(self::$defaultAlias)]
			?? new DduiThemePreset(self::$defaultAlias, "", null);
	}

	/** Wire {@code styleId} for DDUI CustomForm root; empty string = omit (Ore default). */
	public static function resolveStyleId(?string $alias) : string{
		return self::resolvePreset($alias)->getStyleId();
	}

	public static function hasStyle(string $styleId) : bool{
		return isset(self::$discovered[strtolower($styleId)]);
	}
}
