<?php

declare(strict_types=1);

namespace pocketmine\metrics;

use function count;
use function implode;
use function rtrim;
use function sprintf;

/**
 * Simple cumulative Prometheus histogram (fixed buckets).
 */
final class MetricsHistogram{

	/** @var float[] */
	private array $buckets;
	/** @var int[] */
	private array $counts;
	private int $sumCount = 0;
	private float $sum = 0.0;

	/**
	 * @param float[] $upperBounds ascending exclusive upper bounds; +Inf is implied
	 */
	public function __construct(array $upperBounds){
		$this->buckets = $upperBounds;
		$this->counts = array_fill(0, count($upperBounds) + 1, 0);
	}

	public function observe(float $value) : void{
		$this->sum += $value;
		++$this->sumCount;
		foreach($this->buckets as $i => $bound){
			if($value <= $bound){
				++$this->counts[$i];
				return;
			}
		}
		++$this->counts[count($this->buckets)];
	}

	/**
	 * Cumulative bucket lines for Prometheus text format (without HELP/TYPE).
	 *
	 * @param array<string, string> $labels
	 * @return string[]
	 */
	public function renderLines(string $metricName, array $labels = []) : array{
		$lines = [];
		$cumulative = 0;
		foreach($this->buckets as $i => $bound){
			$cumulative += $this->counts[$i];
			$lines[] = sprintf(
				'%s_bucket%s %d',
				$metricName,
				self::formatLabels($labels + ["le" => self::fmtBound($bound)]),
				$cumulative
			);
		}
		$cumulative += $this->counts[count($this->buckets)];
		$lines[] = sprintf(
			'%s_bucket%s %d',
			$metricName,
			self::formatLabels($labels + ["le" => "+Inf"]),
			$cumulative
		);
		$lines[] = sprintf('%s_sum%s %s', $metricName, self::formatLabels($labels), self::fmtBound($this->sum));
		$lines[] = sprintf('%s_count%s %d', $metricName, self::formatLabels($labels), $this->sumCount);
		return $lines;
	}

	/**
	 * @return float[]
	 */
	public static function defaultTickBuckets() : array{
		return [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0];
	}

	/**
	 * @param array<string, string> $labels
	 */
	private static function formatLabels(array $labels) : string{
		if($labels === []){
			return "";
		}
		$parts = [];
		foreach($labels as $k => $v){
			$parts[] = $k . '="' . $v . '"';
		}
		return "{" . implode(",", $parts) . "}";
	}

	private static function fmtBound(float $v) : string{
		$s = sprintf("%.6f", $v);
		$s = rtrim($s, "0");
		return rtrim($s, ".") ?: "0";
	}
}
