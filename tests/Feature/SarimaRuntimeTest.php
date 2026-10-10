<?php

namespace Tests\Feature;

use App\Support\SarimaEngine;
use Tests\TestCase;

class SarimaRuntimeTest extends TestCase
{
    public function test_real_sarima_returns_estimates_intervals_and_out_of_sample_evaluation(): void
    {
        if (! is_file(config('office.python'))) {
            $this->markTestSkipped('Install app/Support/requirements-forecast.txt into the configured isolated Python environment.');
        }
        $history = [];
        for ($index = 0; $index < 60; $index++) {
            $count = (int) round(25 + 8 * sin(2 * M_PI * $index / 12) + $index * 0.2 + ($index % 4));
            $history[] = ['month' => date('Y-m', mktime(0, 0, 0, 1 + $index, 1, 2020)), 'count' => $count, 'revenue' => $count * (450 + ($index % 5) * 20)];
        }

        $result = (new SarimaEngine)->forecast($history, config('office.defaults'));

        foreach (['count', 'revenue'] as $metric) {
            $this->assertCount(6, $result[$metric]['forecast']);
            $this->assertArrayHasKey('mae', $result[$metric]['evaluation']);
            $this->assertArrayHasKey('seasonal_baseline_mae', $result[$metric]['evaluation']);
            $this->assertSame('0.14.6', $result[$metric]['evaluation']['statsmodels_version']);
            foreach ($result[$metric]['forecast'] as $point) {
                $this->assertGreaterThanOrEqual(0, $point['lower']);
                $this->assertGreaterThanOrEqual($point['lower'], $point['estimate']);
                $this->assertGreaterThanOrEqual($point['estimate'], $point['upper']);
            }
        }
    }
}
