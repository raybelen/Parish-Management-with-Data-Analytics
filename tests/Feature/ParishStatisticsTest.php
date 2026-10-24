<?php

namespace Tests\Feature;

use App\Actions\GenerateForecast;
use App\Models\Appointment;
use App\Models\ForecastRun;
use App\Models\MonthlyStatistic;
use App\Models\ServiceRecord;
use App\Models\User;
use App\Support\ParishStatistics;
use App\Support\SarimaEngine;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class ParishStatisticsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_real_import_is_repeatable_preserves_corrections_and_creates_no_operational_records(): void
    {
        $this->artisan('analytics:import-history')->assertSuccessful();
        $this->artisan('analytics:import-history')->assertSuccessful();
        $this->assertDatabaseCount('monthly_statistics', 624);
        $this->assertDatabaseEmpty('appointments');
        $this->assertDatabaseEmpty('service_records');
        $this->assertDatabaseMissing('monthly_statistics', ['service_type' => 'first_communion', 'month' => '2015-01-01']);
        $annual = collect(app(ParishStatistics::class)->annual())->keyBy('year');
        $this->assertSame(169, $annual[2020]['services']['baptism']['total']);
        $this->assertSame(80, $annual[2021]['services']['funeral']['total']);
        $this->assertSame(53, $annual[2022]['services']['funeral']['total']);
        $this->assertSame(196, $annual[2024]['services']['baptism']['total']);
        $this->assertSame(274, $annual[2024]['services']['confirmation']['total']);
        $this->assertSame(168, $annual[2025]['services']['baptism']['total']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'statistics.imported']);
    }

    public function test_conflicting_import_rolls_back_without_overwriting_a_correction(): void
    {
        MonthlyStatistic::factory()->create(['month' => '2025-12-01', 'count' => 999]);
        $this->artisan('analytics:import-history')->assertFailed();
        $this->assertDatabaseCount('monthly_statistics', 1);
        $this->assertDatabaseHas('monthly_statistics', ['count' => 999]);
    }

    public function test_confirmed_zero_and_missing_months_are_different_and_digital_records_are_not_added(): void
    {
        $this->travelTo('2026-10-21');
        MonthlyStatistic::factory()->create(['month' => '2026-01-01', 'count' => 0]);
        MonthlyStatistic::factory()->create(['month' => '2026-03-01', 'count' => 20]);
        MonthlyStatistic::factory()->create(['month' => '2026-02-01', 'count' => 8, 'status' => 'draft']);
        MonthlyStatistic::factory()->create(['source' => 'synthetic', 'month' => '2026-02-01', 'count' => 40]);
        ServiceRecord::factory()->create(['source' => 'operational', 'service_type' => 'baptism', 'service_date' => '2026-03-01']);
        Appointment::factory()->create(['status' => 'completed', 'completed_at' => '2026-03-01']);
        $this->assertSame([
            ['month' => '2026-01', 'count' => 0], ['month' => '2026-02', 'count' => null], ['month' => '2026-03', 'count' => 20],
        ], app(ParishStatistics::class)->history('baptism'));
    }

    public function test_staff_can_prepare_drafts_but_cannot_confirm_or_change_confirmed_totals(): void
    {
        $this->travelTo('2026-10-21');
        $this->verified(User::factory()->staff()->withTwoFactor()->create());
        $data = $this->entry();
        $this->post('/admin/statistics', [...$data, 'status' => 'draft'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('monthly_statistics', ['status' => 'draft', 'count' => 0]);
        $this->post('/admin/statistics', [...$data, 'version' => 1])->assertForbidden();
        MonthlyStatistic::query()->update(['status' => 'confirmed']);
        $this->post('/admin/statistics', [...$data, 'version' => 1, 'status' => 'draft'])->assertForbidden();
    }

    public function test_admin_confirmation_correction_and_stale_version_are_audited(): void
    {
        $this->travelTo('2026-10-21');
        $admin = User::factory()->admin()->withTwoFactor()->create();
        $this->verified($admin);
        $this->post('/admin/statistics', $this->entry())->assertSessionHasNoErrors();
        $this->assertDatabaseHas('monthly_statistics', ['count' => 0, 'status' => 'confirmed', 'confirmed_by' => $admin->id]);
        $this->post('/admin/statistics', [...$this->entry(), 'count' => 2, 'version' => 1])->assertSessionHasNoErrors();
        $this->post('/admin/statistics', [...$this->entry(), 'count' => 99, 'version' => 1])->assertSessionHasErrors('version');
        $this->assertDatabaseHas('monthly_statistics', ['count' => 2, 'version' => 2]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'statistics.saved', 'reason' => 'Verified register']);
    }

    public function test_current_month_unknown_confirmations_and_negative_counts_are_rejected(): void
    {
        $this->travelTo('2026-10-21');
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $this->post('/admin/statistics', [...$this->entry(), 'month' => '2026-10'])->assertSessionHasErrors('month');
        $this->post('/admin/statistics', [...$this->entry(), 'count' => null])->assertSessionHasErrors('count');
        $this->post('/admin/statistics', [...$this->entry(), 'count' => -1])->assertSessionHasErrors('count');
        $this->post('/admin/statistics', [...$this->entry(), 'service_type' => 'mass'])->assertSessionHasErrors('service_type');
        $this->assertDatabaseEmpty('monthly_statistics');
    }

    public function test_stats_pages_render_real_history_and_exports_escape_formula_text(): void
    {
        $this->withoutVite();
        $this->travelTo('2026-10-21');
        $this->artisan('analytics:import-history')->assertSuccessful();
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $this->get('/admin/statistics?year=2025')->assertOk()->assertSee('Annual historical analytics')->assertSee('168');
        $this->get('/admin/statistics?year=2015&service=first_communion')->assertSee('Unavailable');
        $this->get('/admin')->assertOk()->assertSee('Parish historical statistics');
        MonthlyStatistic::factory()->create(['month' => '2026-01-01', 'reference' => '=HYPERLINK("bad")']);
        $response = $this->get('/admin/statistics?year=2026&export=1');
        $response->assertDownload();
        $this->assertStringContainsString("'=HYPERLINK", $response->streamedContent());
    }

    public function test_authentication_mfa_and_permissions_protect_statistics(): void
    {
        $this->get('/admin/statistics')->assertRedirect();
        $user = User::factory()->admin()->withTwoFactor()->create();
        $this->actingAs($user)->get('/admin/statistics')->assertRedirect();
        $this->verified(User::factory()->priest()->withTwoFactor()->create());
        $this->get('/admin/statistics')->assertForbidden();
        $this->post('/admin/statistics', $this->entry())->assertForbidden();
        $this->post('/admin/statistics/import')->assertForbidden();
    }

    public function test_statistics_opens_the_latest_recorded_year_with_a_complete_year_comparison(): void
    {
        $this->withoutVite();
        $this->travelTo('2026-10-21');
        $this->artisan('analytics:import-history')->assertSuccessful();
        $this->verified(User::factory()->admin()->withTwoFactor()->create());

        $response = $this->get('/admin/statistics');

        $response->assertViewHas('year', 2025)->assertSee('14.3%')->assertSee('Fewer than the previous year');
        $summary = $response->viewData('summary');
        $this->assertSame(168, $summary['total']);
        $this->assertSame(12, $summary['months']);
        $this->assertEqualsWithDelta(-14.2857, $summary['change'], 0.001);
    }

    public function test_partial_year_totals_exclude_drafts_and_do_not_show_an_annual_comparison(): void
    {
        $this->withoutVite();
        $this->travelTo('2026-10-21');
        $this->artisan('analytics:import-history')->assertSuccessful();
        MonthlyStatistic::factory()->create(['month' => '2026-01-01', 'count' => 0]);
        MonthlyStatistic::factory()->create(['month' => '2026-02-01', 'count' => 999, 'status' => 'draft']);
        $this->verified(User::factory()->admin()->withTwoFactor()->create());

        $response = $this->get('/admin/statistics?year=2026');

        $response->assertSee('Partial total')->assertViewHas('summary', ['total' => 0, 'months' => 1, 'change' => null]);
        $chart = $response->viewData('monthlyChart');
        $this->assertSame(0, $chart[0]['value']);
        $this->assertNull($chart[1]['value']);
        $this->assertNull($chart[2]['value']);
    }

    public function test_empty_statistics_and_group_ceremonies_have_clear_historical_views(): void
    {
        $this->withoutVite();
        $this->travelTo('2026-10-21');
        $this->verified(User::factory()->admin()->withTwoFactor()->create());

        $this->get('/admin/statistics?service=confirmation')->assertSee('No verified totals to display')->assertDontSee('Monthly activity');
        $this->artisan('analytics:import-history')->assertSuccessful();
        $this->get('/admin/statistics?service=first_communion&year=2015')
            ->assertSee('First Communicants by year')->assertDontSee('Monthly activity')
            ->assertViewHas('summary', ['total' => null, 'months' => 0, 'change' => null]);
    }

    public function test_zero_previous_annual_total_does_not_produce_an_infinite_percentage(): void
    {
        $this->withoutVite();
        $this->travelTo('2026-10-21');
        $this->artisan('analytics:import-history')->assertSuccessful();
        MonthlyStatistic::query()->where('service_type', 'baptism')->whereBetween('month', ['2024-01-01', '2024-12-01'])->update(['count' => 0]);
        $this->verified(User::factory()->admin()->withTwoFactor()->create());

        $response = $this->get('/admin/statistics?year=2025');

        $this->assertNull($response->viewData('summary')['change']);
        $response->assertSee('earlier total is above zero');
    }

    public function test_forecast_uses_confirmed_counts_and_changes_invalidate_saved_results(): void
    {
        $this->withoutVite();
        $this->travelTo('2026-10-21');
        $this->artisan('analytics:import-history')->assertSuccessful();
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        Process::preventStrayProcesses();
        Process::fake(['*' => Process::result(output: json_encode(['count' => [
            'forecast' => array_fill(0, 6, ['estimate' => 2.4, 'lower' => 0, 'upper' => 5.1]),
            'evaluation' => ['outperformed_baselines' => true, 'mae' => 1, 'seasonal_baseline_mae' => 2, 'recent_mean_mae' => 3, 'calibration_start' => '2024-01', 'calibration_end' => '2024-12', 'test_start' => '2025-01', 'test_end' => '2025-12', 'by_horizon' => []],
        ]]))]);
        $this->post('/admin/forecasts', ['service' => 'wedding'])->assertSessionHasNoErrors();
        Process::assertRan(fn ($process) => str_contains($process->input, '"log_transform":true') && ! str_contains($process->input, '"revenue"'));
        $run = ForecastRun::query()->sole();
        $this->assertCount(132, $run->history);
        $this->assertSame('ready', $run->status);
        $this->get('/admin/forecasts?service=wedding')->assertOk()->assertSee('Historical outlook')->assertSee('January 2026')->assertDontSee('95%');
        $export = $this->get('/admin/reports?type=forecast&from=2026-10-01&to=2026-10-31&export=1');
        $export->assertDownload();
        $this->assertStringContainsString('data_through', $export->streamedContent());
        $this->assertStringContainsString('2025-12', $export->streamedContent());
        $this->assertStringContainsString('Confirmed monthly parish statistics', $export->streamedContent());
        MonthlyStatistic::query()->where('service_type', 'wedding')->where('month', '2025-12-01')->update(['count' => 99]);
        $this->get('/admin/forecasts?service=wedding')->assertSee('saved forecast is outdated')->assertDontSee('January 2026');
    }

    public function test_internal_gap_stops_forecasting_and_annual_only_services_cannot_forecast(): void
    {
        $this->travelTo('2026-10-21');
        $this->artisan('analytics:import-history')->assertSuccessful();
        MonthlyStatistic::query()->where('month', '2020-01-01')->where('service_type', 'baptism')->update(['status' => 'draft']);
        $this->mock(SarimaEngine::class)->shouldNotReceive('forecast');
        $run = app(GenerateForecast::class)->handle('baptism');
        $this->assertSame('insufficient_data', $run->status);
        $this->assertNull($run->results);
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $this->post('/admin/forecasts', ['service' => 'confirmation'])->assertSessionHasErrors('service');
        $this->artisan('analytics:forecast', ['--service' => 'first_communion'])->assertFailed();
    }

    public function test_runtime_failure_is_saved_without_exposing_server_details(): void
    {
        $this->artisan('analytics:import-history')->assertSuccessful();
        Process::fake(['*' => Process::result(errorOutput: 'private server detail', exitCode: 1)]);
        $run = app(GenerateForecast::class)->handle('funeral');
        $this->assertSame('unavailable', $run->status);
        $this->assertStringNotContainsString('private server detail', $run->message);
        Process::assertRan(fn ($process) => str_contains($process->input, '"count"'));
    }

    /** @return array<string, mixed> */
    private function entry(): array
    {
        return ['month' => '2026-09', 'service_type' => 'baptism', 'count' => 0, 'status' => 'confirmed', 'reference' => 'Register 2026 p. 3', 'reason' => 'Verified register', 'version' => 0];
    }

    private function verified(User $user): static
    {
        return $this->actingAs($user)->withSession(['admin.mfa_user_id' => $user->id, 'admin.mfa_fingerprint' => $user->adminSessionFingerprint()]);
    }
}
