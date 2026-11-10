<?php

declare(strict_types=1);

namespace pocketmine\metrics;

use pocketmine\Server;
use pocketmine\utils\Process;
use pocketmine\world\format\io\leveldb\LevelDB;
use function count;
use function implode;
use function preg_replace;
use function sprintf;

/**
 * Renders a small Prometheus text exposition from live Server stats.
 */
final class PrometheusTextRenderer{

	private MetricsHistogram $tickDuration;
	/** @var array<string, MetricsHistogram> */
	private array $worldTickDurations = [];

	public function __construct(){
		$this->tickDuration = new MetricsHistogram(MetricsHistogram::defaultTickBuckets());
	}

	public function observeTickDurationSeconds(float $seconds) : void{
		$this->tickDuration->observe($seconds);
	}

	public function observeWorldTickMs(string $worldFolder, float $ms) : void{
		$hist = $this->worldTickDurations[$worldFolder] ??= new MetricsHistogram(MetricsHistogram::defaultTickBuckets());
		$hist->observe($ms / 1000.0);
	}

	public function render(Server $server) : string{
		[$phpReserved, $rss, $vsize] = Process::getAdvancedMemoryUsage();
		$lines = [];
		$lines[] = "# HELP pmmp_tps Average ticks per second";
		$lines[] = "# TYPE pmmp_tps gauge";
		$lines[] = sprintf("pmmp_tps %.3f", $server->getTicksPerSecondAverage());

		$lines[] = "# HELP pmmp_tick_usage Average tick usage percent";
		$lines[] = "# TYPE pmmp_tick_usage gauge";
		$lines[] = sprintf("pmmp_tick_usage %.3f", $server->getTickUsageAverage());

		$lines[] = "# HELP pmmp_players_online Online players";
		$lines[] = "# TYPE pmmp_players_online gauge";
		$lines[] = "pmmp_players_online " . count($server->getOnlinePlayers());

		$lines[] = "# HELP pmmp_players_max Max players";
		$lines[] = "# TYPE pmmp_players_max gauge";
		$lines[] = "pmmp_players_max " . $server->getMaxPlayers();

		$lines[] = "# HELP pmmp_memory_bytes Memory usage bytes";
		$lines[] = "# TYPE pmmp_memory_bytes gauge";
		$lines[] = "pmmp_memory_bytes{type=\"php_reserved\"} " . $phpReserved;
		$lines[] = "pmmp_memory_bytes{type=\"rss\"} " . $rss;
		$lines[] = "pmmp_memory_bytes{type=\"virtual\"} " . $vsize;

		$lines[] = "# HELP pmmp_tick_counter Server tick counter";
		$lines[] = "# TYPE pmmp_tick_counter counter";
		$lines[] = "pmmp_tick_counter " . $server->getTick();

		$lines[] = "# HELP pmmp_tick_frozen Whether game tick is frozen";
		$lines[] = "# TYPE pmmp_tick_frozen gauge";
		$lines[] = "pmmp_tick_frozen " . ($server->isTickFrozen() ? 1 : 0);

		$lines[] = "# HELP pmmp_world_chunks Loaded chunks per world";
		$lines[] = "# TYPE pmmp_world_chunks gauge";
		$lines[] = "# HELP pmmp_world_entities Entities per world";
		$lines[] = "# TYPE pmmp_world_entities gauge";
		$lines[] = "# HELP pmmp_world_tick_ms Last world tick time milliseconds";
		$lines[] = "# TYPE pmmp_world_tick_ms gauge";

		$lines[] = "# HELP pmmp_world_leveldb_flush_age_seconds Seconds since LAST_FLUSH sidecar write";
		$lines[] = "# TYPE pmmp_world_leveldb_flush_age_seconds gauge";

		foreach($server->getWorldManager()->getWorlds() as $world){
			$label = self::escapeLabel($world->getFolderName());
			$lines[] = "pmmp_world_chunks{world=\"$label\"} " . count($world->getLoadedChunks());
			$lines[] = "pmmp_world_entities{world=\"$label\"} " . count($world->getEntities());
			$lines[] = sprintf("pmmp_world_tick_ms{world=\"%s\"} %.3f", $label, $world->tickRateTime);
			$provider = $world->getProvider();
			if($provider instanceof LevelDB){
				$age = $provider->getLastFlushAgeSeconds();
				if($age !== null){
					$lines[] = "pmmp_world_leveldb_flush_age_seconds{world=\"$label\"} " . $age;
				}
			}
		}

		$lines[] = "# HELP pmmp_light_deferred_execute_total Coalesced light update batches executed";
		$lines[] = "# TYPE pmmp_light_deferred_execute_total counter";
		$lines[] = "pmmp_light_deferred_execute_total " . \pocketmine\world\light\LightUpdatePolicy::getDeferredExecuteCount();
		$lines[] = "# HELP pmmp_light_immediate_execute_total Immediate light update batches executed";
		$lines[] = "# TYPE pmmp_light_immediate_execute_total counter";
		$lines[] = "pmmp_light_immediate_execute_total " . \pocketmine\world\light\LightUpdatePolicy::getImmediateExecuteCount();

		$lines[] = "# HELP pmmp_tick_duration_seconds Server tick wall time";
		$lines[] = "# TYPE pmmp_tick_duration_seconds histogram";
		foreach($this->tickDuration->renderLines("pmmp_tick_duration_seconds") as $line){
			$lines[] = $line;
		}

		$lines[] = "# HELP pmmp_world_tick_duration_seconds World tick wall time";
		$lines[] = "# TYPE pmmp_world_tick_duration_seconds histogram";
		foreach($this->worldTickDurations as $worldName => $hist){
			foreach($hist->renderLines("pmmp_world_tick_duration_seconds", ["world" => self::escapeLabel($worldName)]) as $line){
				$lines[] = $line;
			}
		}

		return implode("\n", $lines) . "\n";
	}

	private static function escapeLabel(string $value) : string{
		$scrubbed = preg_replace('/[^a-zA-Z0-9_.:-]/', '_', $value);
		return $scrubbed ?? "world";
	}
}
