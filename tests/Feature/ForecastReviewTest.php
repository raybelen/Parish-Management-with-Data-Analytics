<?php

namespace Tests\Feature;

use App\Models\ForecastRun;
use App\Models\MonthlyStatistic;
use App\Models\User;
use App\Support\ParishStatistics;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ForecastReviewTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_review_selects_latest_prior_snapshot_and_keeps_verified_zero_totals(): void
    {
        $this->travelTo('2026-04-15');
        $this->signIn();
        MonthlyStatistic::factory()->create(['month' => '2026-01-01', 'count' => 0]);
        MonthlyStatistic::factory()->create(['month' => '2026-02-01', 'count' => 15]);
        MonthlyStatistic::factory()->create(['month' => '2026-03-01', 'status' => 'draft']);
        MonthlyStatistic::factory()->create(['month' => '2026-04-01', 'count' => 99]);
        $this->snapshot('2025-12-10', ['results' => ['count' => ['forecast' => array_fill(0, 6, ['estimate' => 99, 'lower' => 90, 'upper' => 100])]]]);
        $saved = $this->snapshot('2025-12-31 23:59:59', ['status' => 'unreliable']);
        $this->snapshot('2026-01-01', ['status' => 'unavailable']);
        $this->snapshot('2025-12-31 23:59:59', ['source' => 'synthetic']);
        $this->snapshot('2025-12-31 23:59:59', ['service_type' => 'wedding']);

        $response = $this->get('/admin/forecasts');

        $response->assertOk()->assertSee('Past forecast versus actual results')->assertSee('Issued with extra caution');
        $rows = $response->viewData('forecastReview');
        $this->assertCount(2, $rows);
        $this->assertSame(['February 2026', 'January 2026'], array_column($rows, 'month'));
        $this->assertSame([15, 0], array_column($rows, 'actual'));
        $this->assertSame([13, -2], array_column($rows, 'difference'));
        $this->assertSame([false, true], array_column($rows, 'covered'));
        $this->assertSame([2, 1], array_column($rows, 'horizon'));
        $this->assertSame(0, $rows[1]['lower']);
        $this->assertSame(6, $rows[1]['upper']);
        $this->assertSame($saved->results, $saved->fresh()->results);
    }

    public function test_review_excludes_late_or_modified_snapshots_and_explains_empty_results(): void
    {
        $this->travelTo('2026-02-15');
        $this->signIn();
        MonthlyStatistic::factory()->create(['month' => '2026-01-01', 'count' => 5]);
        $this->snapshot('2026-01-01 00:00:00');
        $this->snapshot('2025-12-30', ['updated_at' => '2026-01-02']);

        $this->get('/admin/forecasts')->assertOk()->assertViewHas('forecastReview', [])
            ->assertSee('No eligible comparisons yet');
    }

    public function test_review_uses_corrected_actual_and_searches_beyond_previous_ten_updates(): void
    {
        $this->travelTo('2026-02-15');
        $this->signIn();
        $actual = MonthlyStatistic::factory()->create(['month' => '2026-01-01', 'count' => 5]);
        $saved = $this->snapshot('2025-12-20');
        $actual->update(['count' => 6]);
        for ($index = 0; $index < 11; $index++) {
            $this->snapshot('2026-02-01', ['status' => 'unavailable']);
        }

        $response = $this->get('/admin/forecasts');

        $rows = $response->viewData('forecastReview');
        $this->assertCount(1, $rows);
        $this->assertSame(6, $rows[0]['actual']);
        $this->assertSame(4, $rows[0]['difference']);
        $this->assertTrue($rows[0]['covered']);
        $this->assertSame($saved->results, $saved->fresh()->results);
    }

    public function test_current_summary_gives_action_and_caution_but_stale_results_do_not(): void
    {
        $this->travelTo('2026-01-15');
        $this->signIn();
        $actual = MonthlyStatistic::factory()->create(['month' => '2025-12-01', 'count' => 10]);
        $this->snapshot('2026-01-10', ['status' => 'unreliable', 'configuration' => [
            'model_version' => config('forecast.version'),
            'history_hash' => app(ParishStatistics::class)->fingerprint(app(ParishStatistics::class)->history('baptism')),
        ]]);

        $this->get('/admin/forecasts')->assertOk()->assertSee('What should staff do?')
            ->assertSee('Review baptism sessions')->assertSee('Keep arrangements flexible');

        $actual->update(['count' => 11]);
        $this->get('/admin/forecasts')->assertSee('saved estimates are outdated')->assertDontSee('Review baptism sessions');
    }

    public function test_historical_summary_prioritizes_recent_records_over_scheduling(): void
    {
        $this->travelTo('2026-04-15');
        $this->signIn();
        MonthlyStatistic::factory()->create(['month' => '2025-12-01', 'count' => 10]);
        $this->snapshot('2026-01-10', ['configuration' => [
            'model_version' => config('forecast.version'),
            'history_hash' => app(ParishStatistics::class)->fingerprint(app(ParishStatistics::class)->history('baptism')),
        ]]);

        $this->get('/admin/forecasts')->assertSee('Verify recent monthly totals, then refresh analytics')
            ->assertDontSee('Review baptism sessions');
    }

    public function test_review_limits_results_to_last_twelve_completed_months(): void
    {
        $this->travelTo('2026-02-15');
        $this->signIn();
        MonthlyStatistic::factory()->create(['month' => '2025-01-01', 'count' => 10]);
        MonthlyStatistic::factory()->create(['month' => '2025-02-01', 'count' => 4]);
        $this->snapshot('2024-12-31', ['history' => [['month' => '2024-12', 'count' => 10]]]);
        $this->snapshot('2025-01-31', ['history' => [['month' => '2025-01', 'count' => 10]]]);

        $response = $this->get('/admin/forecasts');

        $rows = $response->viewData('forecastReview');
        $this->assertCount(1, $rows);
        $this->assertSame('February 2025', $rows[0]['month']);
        $this->assertSame('Jan 31, 2025', $rows[0]['generated']);
        $this->assertSame(1, $rows[0]['horizon']);
    }

    public function test_review_with_no_verified_actuals_explains_empty_results(): void
    {
        $this->travelTo('2026-02-15');
        $this->signIn();

        $this->get('/admin/forecasts')->assertOk()->assertViewHas('forecastReview', [])
            ->assertSee('No eligible comparisons yet');
    }

    /** @param array<string, mixed> $attributes */
    private function snapshot(string $generated, array $attributes = []): ForecastRun
    {
        return ForecastRun::factory()->create([
            'service_type' => 'baptism', 'status' => 'ready', 'created_at' => $generated, 'updated_at' => $generated,
            'history' => [['month' => '2025-12', 'count' => 10]],
            'results' => ['count' => [
                'forecast' => array_fill(0, 6, ['estimate' => 2.4, 'lower' => 0.2, 'upper' => 5.1]),
                'evaluation' => ['test_start' => '2025-01', 'test_end' => '2025-12', 'mae' => 1, 'seasonal_baseline_mae' => 2, 'recent_mean_mae' => 3],
            ]],
            ...$attributes,
        ]);
    }

    private function signIn(): void
    {
        $this->withoutVite();
        $user = User::factory()->admin()->withTwoFactor()->create();
        $this->actingAs($user)->withSession(['admin.mfa_user_id' => $user->id, 'admin.mfa_fingerprint' => $user->adminSessionFingerprint()]);
    }
}
