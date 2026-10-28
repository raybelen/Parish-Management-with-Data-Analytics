<?php

namespace Tests\Feature;

use App\Models\MonthlyStatistic;
use App\Models\User;
use App\Support\ParishInsights;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ParishInsightsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_real_partial_year_compares_matching_months_and_renders_planning_insights(): void
    {
        $this->travelTo('2026-10-28');
        $this->withoutVite();
        $this->artisan('analytics:import-history')->assertSuccessful();
        $this->artisan('analytics:import-history', ['--dataset' => '2026-partial'])->assertSuccessful();
        $user = User::factory()->admin()->withTwoFactor()->create();
        $this->actingAs($user)->withSession(['admin.mfa_user_id' => $user->id, 'admin.mfa_fingerprint' => $user->adminSessionFingerprint()]);

        $response = $this->get('/admin/statistics?service=baptism&year=2026');

        $response->assertOk()->assertSee('January–September in both years: 133 vs 136.')
            ->assertSee('Typical seasonal pattern')->assertSee('Months to review')->assertSee('What this means for planning');
        $insights = $response->viewData('insights');
        $this->assertSame(9, $insights['expected']);
        $this->assertSame(9, $insights['verified']);
        $this->assertSame([], $insights['missing']);
        $this->assertSame(-3, $insights['comparison']['difference']);
        $this->assertEqualsWithDelta(-2.205882, $insights['comparison']['percent'], 0.000001);
        $this->assertSame(['May'], $insights['peak']['labels']);
        $this->assertSame(66, $insights['peak']['count']);
        $this->assertSame(1, $insights['zero_months']);
        $this->assertSame(['May'], $insights['seasonal_peak']);
        $this->assertSame(9, $insights['screened']);
    }

    public function test_gaps_drafts_other_sources_and_unfinished_months_do_not_inflate_insights(): void
    {
        $this->travelTo('2026-10-28');
        MonthlyStatistic::factory()->create(['month' => '2026-01-01', 'count' => 0]);
        MonthlyStatistic::factory()->create(['month' => '2026-02-01', 'count' => 999, 'status' => 'draft']);
        MonthlyStatistic::factory()->create(['month' => '2026-03-01', 'count' => 20]);
        MonthlyStatistic::factory()->create(['month' => '2026-02-01', 'count' => 888, 'source' => 'synthetic']);
        MonthlyStatistic::factory()->create(['month' => '2026-10-01', 'count' => 777]);
        MonthlyStatistic::factory()->create(['month' => '2026-01-01', 'count' => 666, 'service_type' => 'wedding']);

        $result = app(ParishInsights::class)->forService('baptism', 2026);

        $this->assertNull($result['comparison']);
        $this->assertSame(2, $result['verified']);
        $this->assertSame(1, $result['drafts']);
        $this->assertSame(10.0, $result['average']);
        $this->assertSame(['February', 'April', 'May', 'June', 'July', 'August', 'September'], $result['missing']);
        $this->assertSame('March 2026', $result['through']);
        $this->assertSame([], $result['anomalies']);
        $this->assertSame(0, $result['screened']);
    }

    public function test_zero_baseline_reports_absolute_difference_without_a_percentage(): void
    {
        $this->travelTo('2026-02-10');
        MonthlyStatistic::factory()->create(['month' => '2025-01-01', 'count' => 0]);
        MonthlyStatistic::factory()->create(['month' => '2026-01-01', 'count' => 2]);

        $result = app(ParishInsights::class)->forService('baptism', 2026);

        $this->assertSame(['current' => 2, 'previous' => 0, 'difference' => 2, 'percent' => null], $result['comparison']);
        $this->assertSame('January', $result['period']);
    }

    public function test_seasonal_screening_flags_large_changes_but_preserves_regular_peaks_and_counts(): void
    {
        $this->travelTo('2026-10-28');
        foreach (range(2021, 2025) as $year) {
            MonthlyStatistic::factory()->create(['month' => $year.'-01-01', 'count' => 10]);
            MonthlyStatistic::factory()->create(['month' => $year.'-05-01', 'count' => 100]);
        }
        MonthlyStatistic::factory()->create(['month' => '2026-01-01', 'count' => 60]);
        MonthlyStatistic::factory()->create(['month' => '2026-05-01', 'count' => 103]);
        MonthlyStatistic::factory()->create(['month' => '2027-01-01', 'count' => 1000]);

        $result = app(ParishInsights::class)->forService('baptism', 2026);

        $this->assertSame([['month' => 'January', 'actual' => 60, 'typical' => 10.0, 'lower' => 7.0, 'upper' => 13.0, 'direction' => 'Higher']], $result['anomalies']);
        $this->assertSame(100.0, $result['seasonal'][4]['value']);
        $this->assertSame(2, $result['screened']);
        $this->assertSame([], $result['seasonal_peak']);
        $this->assertDatabaseHas('monthly_statistics', ['month' => '2026-01-01', 'count' => 60]);
    }

    public function test_screening_handles_variable_history_and_requires_five_verified_prior_years(): void
    {
        $this->travelTo('2026-10-28');
        foreach ([2021 => 8, 2022 => 9, 2023 => 10, 2024 => 11, 2025 => 12] as $year => $count) {
            MonthlyStatistic::factory()->create(['month' => $year.'-01-01', 'count' => $count]);
            MonthlyStatistic::factory()->create(['month' => $year.'-02-01', 'count' => $count, 'status' => $year === 2025 ? 'draft' : 'confirmed']);
        }
        MonthlyStatistic::factory()->create(['month' => '2026-01-01', 'count' => 0]);
        MonthlyStatistic::factory()->create(['month' => '2026-02-01', 'count' => 100]);

        $result = app(ParishInsights::class)->forService('baptism', 2026);

        $this->assertCount(1, $result['anomalies']);
        $this->assertSame('Lower', $result['anomalies'][0]['direction']);
        $this->assertSame(10.0, $result['anomalies'][0]['typical']);
        $this->assertEqualsWithDelta(4.81097, $result['anomalies'][0]['lower'], 0.00001);
        $this->assertNull($result['seasonal'][1]['value']);
        $this->assertSame(1, $result['screened']);
    }

    public function test_group_ceremonies_keep_annual_analysis_without_monthly_pattern_or_anomaly_claims(): void
    {
        $this->travelTo('2026-10-28');
        $this->withoutVite();
        $this->artisan('analytics:import-history')->assertSuccessful();
        $this->artisan('analytics:import-history', ['--dataset' => '2026-partial'])->assertSuccessful();
        $user = User::factory()->admin()->withTwoFactor()->create();
        $this->actingAs($user)->withSession(['admin.mfa_user_id' => $user->id, 'admin.mfa_fingerprint' => $user->adminSessionFingerprint()]);

        $response = $this->get('/admin/statistics?service=confirmation&year=2026');

        $response->assertSee('Plan group ceremonies from the parish calendar')->assertDontSee('Typical seasonal pattern')->assertDontSee('Months to review');
        $result = $response->viewData('insights');
        $this->assertNull($result['comparison']);
        $this->assertSame([], $result['anomalies']);
        $this->assertSame([], $result['seasonal']);
    }

    public function test_empty_january_does_not_report_zero_activity_or_false_trends(): void
    {
        $this->travelTo('2026-01-10');

        $result = app(ParishInsights::class)->forService('baptism', 2026);

        $this->assertSame(0, $result['expected']);
        $this->assertNull($result['average']);
        $this->assertNull($result['peak']);
        $this->assertNull($result['comparison']);
        $this->assertSame('No completed months yet', $result['period']);
    }
}
