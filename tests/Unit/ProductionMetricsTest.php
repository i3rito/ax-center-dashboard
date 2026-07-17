<?php

namespace Tests\Unit;

use App\Support\ProductionMetrics;
use PHPUnit\Framework\TestCase;

class ProductionMetricsTest extends TestCase
{
    public function test_defect_rate_and_efficiency()
    {
        $this->assertSame(5.0, ProductionMetrics::defectRate(100, 5));
        $this->assertSame(95.0, ProductionMetrics::efficiency(100, 5));
        $this->assertSame(0.0, ProductionMetrics::defectRate(0, 5));
        $this->assertSame(0.0, ProductionMetrics::efficiency(0, 5));
    }

    public function test_alert_threshold_is_exclusive()
    {
        $this->assertFalse(ProductionMetrics::isAlert(5.0));
        $this->assertTrue(ProductionMetrics::isAlert(5.01));
    }
}
