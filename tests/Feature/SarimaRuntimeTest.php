<?php

namespace Tests\Feature;

use App\Support\SarimaEngine;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SarimaRuntimeTest extends TestCase
{
    #[DataProvider('serviceResults')]
    public function test_real_sarima_reproduces_the_verified_parish_evaluation(string $service, float $expectedMae, bool $beatsBaselines, int $covered): void
    {
        if (! is_file(config('office.python'))) {
            $this->markTestSkipped('Install app/Support/requirements-forecast.txt into the configured isolated Python environment.');
        }

        $data = json_decode(file_get_contents(database_path('seeders/parish-statistics-2015-2025.json')), true, 512, JSON_THROW_ON_ERROR);
        $history = array_map(fn ($row) => ['month' => $row['month'], 'count' => $row[$service]], $data['months']);

        $result = (new SarimaEngine)->forecast($history, config('forecast.services.'.$service));

        $this->assertArrayNotHasKey('revenue', $result);
        $this->assertArrayNotHasKey('error', $result['count']);
        $this->assertCount(6, $result['count']['forecast']);
        $evaluation = $result['count']['evaluation'];
        $this->assertEqualsWithDelta($expectedMae, $evaluation['mae'], 0.001);
        $this->assertSame($beatsBaselines, $evaluation['outperformed_baselines']);
        $this->assertSame('2024-01', $evaluation['calibration_start']);
        $this->assertSame('2024-12', $evaluation['calibration_end']);
        $this->assertSame('2025-01', $evaluation['test_start']);
        $this->assertSame('2025-12', $evaluation['test_end']);
        $this->assertSame(72, $evaluation['test_predictions']);
        $this->assertSame(12, $evaluation['test_months']);
        $this->assertSame($covered, array_sum(array_column($evaluation['by_horizon'], 'covered')));
        $this->assertSame('0.14.6', $evaluation['statsmodels_version']);
        foreach ($result['count']['forecast'] as $point) {
            $this->assertGreaterThanOrEqual(0, $point['lower']);
            $this->assertGreaterThanOrEqual($point['lower'], $point['estimate']);
            $this->assertGreaterThanOrEqual($point['estimate'], $point['upper']);
            $this->assertTrue(is_finite($point['upper']));
        }
    }

    /** @return array<string, array{string, float, bool, int}> */
    public static function serviceResults(): array
    {
        return [
            'Baptisms' => ['baptism', 3.27793950996, true, 66],
            'Weddings' => ['wedding', 0.88686028446, true, 66],
            'Funerals' => ['funeral', 2.22063775014, false, 72],
        ];
    }
}
