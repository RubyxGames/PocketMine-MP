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

namespace pocketmine\command\defaults;

use pocketmine\command\CommandSender;
use pocketmine\lang\KnownTranslationFactory as l10n;
use pocketmine\lang\Translatable;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\utils\Process;
use pocketmine\utils\TextFormat;
use function array_keys;
use function count;
use function floor;
use function implode;
use function microtime;
use function number_format;
use function round;
use function strval;
use function usleep;

class StatusCommand extends VanillaCommand{

	public function __construct(){
		parent::__construct(
			"status",
			l10n::pocketmine_command_status_description()
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_STATUS);
	}

	private static function send(CommandSender $sender, Translatable $message) : void{
		$sender->sendMessage($message->prefix(TextFormat::GOLD));
	}

	private static function formatTPS(float $tps, float $usage, string $tpsColor) : Translatable{
		return l10n::pocketmine_command_status_tps_stat(strval($tps), strval($usage))->prefix($tpsColor);
	}

	private static function formatBandwidth(float $bytes) : Translatable{
		return l10n::pocketmine_command_status_network_stat(strval(round($bytes / 1024, 2)))->prefix(TextFormat::RED);
	}

	/** Human size: MB under 1 GB, else GB. */
	private static function formatBytes(int $bytes) : string{
		$mb = $bytes / 1024 / 1024;
		if($mb >= 1024.0){
			return number_format(round($mb / 1024.0, 2), 2) . " GB";
		}
		return number_format(round($mb, 2), 2) . " MB";
	}

	private static function usageColor(float $percent) : string{
		if($percent >= 90.0){
			return TextFormat::RED;
		}
		if($percent >= 70.0){
			return TextFormat::GOLD;
		}
		return TextFormat::GREEN;
	}

	/**
	 * @return Translatable colored "used / total (percent%)"
	 */
	private static function formatUsage(int $usedBytes, int $totalBytes, float $percent) : Translatable{
		$color = self::usageColor($percent);
		return l10n::pocketmine_command_status_usage_stat(
			$color . self::formatBytes($usedBytes) . TextFormat::RESET,
			$color . self::formatBytes($totalBytes) . TextFormat::RESET,
			$color . number_format(round($percent, 1), 1) . TextFormat::RESET
		);
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		// Sample VPS CPU over a short window (/proc/stat delta).
		Process::getSystemCpuUsagePercent();
		usleep(80_000);
		$vpsCpu = Process::getSystemCpuUsagePercent();

		$server = $sender->getServer();
		$sender->sendMessage(l10n::pocketmine_command_status_header()->format(
			TextFormat::GREEN . "---- " . TextFormat::RESET,
			TextFormat::GREEN . " ----" . TextFormat::RESET
		));

		self::send($sender, l10n::pocketmine_command_status_version(
			TextFormat::AQUA . $server->getName() . " " . $server->getPocketMineVersion() . TextFormat::RESET
		));

		$online = count($server->getOnlinePlayers());
		$max = $server->getMaxPlayers();
		self::send($sender, l10n::pocketmine_command_status_players(
			TextFormat::GREEN . (string) $online . TextFormat::GOLD . " / " . TextFormat::GREEN . (string) $max . TextFormat::RESET
		));

		$time = (int) (microtime(true) - $server->getStartTime());

		$seconds = strval($time % 60);
		if($time >= 60){
			$minutes = strval(floor(($time % 3600) / 60));
			if($time >= 3600){
				$hours = strval(floor(($time % (3600 * 24)) / 3600));
				$message = $time >= 3600 * 24 ?
					l10n::pocketmine_command_status_uptime_days(strval(floor($time / (3600 * 24))), $hours, $minutes, $seconds) :
					l10n::pocketmine_command_status_uptime_hours($hours, $minutes, $seconds);
			}else{
				$message = l10n::pocketmine_command_status_uptime_minutes($minutes, $seconds);
			}
		}else{
			$message = l10n::pocketmine_command_status_uptime_seconds($seconds);
		}

		self::send($sender, l10n::pocketmine_command_status_uptime($message->prefix(TextFormat::RED)));

		if($server->isTickFrozen()){
			self::send($sender, l10n::pocketmine_command_status_tick_frozen()->prefix(TextFormat::RED));
		}

		$tpsColor = TextFormat::GREEN;
		$tps = $server->getTicksPerSecond();
		if($tps < 12){
			$tpsColor = TextFormat::RED;
		}elseif($tps < 17){
			$tpsColor = TextFormat::GOLD;
		}

		self::send($sender, l10n::pocketmine_command_status_tps_current(self::formatTPS($tps, $server->getTickUsage(), $tpsColor)));

		$avgTps = $server->getTicksPerSecondAverage();
		$avgColor = TextFormat::GREEN;
		if($avgTps < 12){
			$avgColor = TextFormat::RED;
		}elseif($avgTps < 17){
			$avgColor = TextFormat::GOLD;
		}
		self::send($sender, l10n::pocketmine_command_status_tps_average(self::formatTPS($avgTps, $server->getTickUsageAverage(), $avgColor)));

		if($vpsCpu !== null){
			self::send($sender, l10n::pocketmine_command_status_cpu(
				l10n::pocketmine_command_status_cpu_stat(number_format(round($vpsCpu, 1), 1))->prefix(self::usageColor($vpsCpu))
			));
		}

		$sysMem = Process::getSystemMemoryUsage();
		if($sysMem !== null){
			$pct = ($sysMem["total"] > 0) ? ($sysMem["used"] / $sysMem["total"]) * 100.0 : 0.0;
			self::send($sender, l10n::pocketmine_command_status_ram_system(
				self::formatUsage($sysMem["used"], $sysMem["total"], $pct)
			));
		}

		$disk = Process::getDiskUsage($server->getDataPath());
		if($disk !== null){
			$diskPct = ($disk["total"] > 0) ? ($disk["used"] / $disk["total"]) * 100.0 : 0.0;
			self::send($sender, l10n::pocketmine_command_status_disk(
				self::formatUsage($disk["used"], $disk["total"], $diskPct)
			));
		}

		$bandwidth = $server->getNetwork()->getBandwidthTracker();
		self::send($sender, l10n::pocketmine_command_status_network_upload(self::formatBandwidth($bandwidth->getSend()->getAverageBytes())));
		self::send($sender, l10n::pocketmine_command_status_network_download(self::formatBandwidth($bandwidth->getReceive()->getAverageBytes())));

		$transportNames = array_keys($server->getNetwork()->getActiveTransports());
		self::send($sender, l10n::pocketmine_command_status_transport(
			TextFormat::AQUA . (count($transportNames) === 0 ? "-" : implode(", ", $transportNames)) . TextFormat::RESET
		));

		$enabledPlugins = 0;
		foreach($server->getPluginManager()->getPlugins() as $plugin){
			if($plugin->isEnabled()){
				$enabledPlugins++;
			}
		}
		self::send($sender, l10n::pocketmine_command_status_plugins(
			TextFormat::GREEN . (string) $enabledPlugins . TextFormat::GOLD . " / " .
			TextFormat::GREEN . (string) count($server->getPluginManager()->getPlugins()) . TextFormat::RESET
		));

		self::send($sender, l10n::pocketmine_command_status_threads(TextFormat::RED . Process::getThreadCount()));

		foreach($server->getWorldManager()->getWorlds() as $world){
			$worldName = $world->getFolderName() !== $world->getDisplayName() ? " (" . $world->getDisplayName() . ")" : "";
			$timeColor = $world->getTickRateTime() > 40 ? TextFormat::RED : TextFormat::YELLOW;
			self::send($sender, l10n::pocketmine_command_status_world(
				"\"{$world->getFolderName()}\"$worldName",
				TextFormat::RED . number_format(count($world->getLoadedChunks())) . TextFormat::GREEN,
				TextFormat::RED . number_format(count($world->getTickingChunks())) . TextFormat::GREEN,
				TextFormat::RED . number_format(count($world->getEntities())) . TextFormat::GREEN,
				l10n::pocketmine_command_status_world_timeStat(strval(round($world->getTickRateTime(), 2)))->prefix($timeColor)
			));
		}

		return true;
	}
}
