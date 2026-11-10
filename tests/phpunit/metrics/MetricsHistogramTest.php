<?php

declare(strict_types=1);

namespace pocketmine\metrics;

use PHPUnit\Framework\TestCase;
use function implode;

final class MetricsHistogramTest extends TestCase{

	public function testObserveAndRenderBuckets() : void{
		$h = new MetricsHistogram([0.01, 0.05, 0.1]);
		$h->observe(0.005);
		$h->observe(0.04);
		$h->observe(0.2);

		$lines = implode("\n", $h->renderLines("demo"));
		self::assertStringContainsString('demo_bucket{le="0.01"} 1', $lines);
		self::assertStringContainsString('demo_bucket{le="0.05"} 2', $lines);
		self::assertStringContainsString('demo_bucket{le="+Inf"} 3', $lines);
		self::assertStringContainsString('demo_count 3', $lines);
	}
}
