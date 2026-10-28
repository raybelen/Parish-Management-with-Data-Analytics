<?php

namespace Tests\Feature;

use App\Models\MonthlyStatistic;
use App\Models\User;
use App\Support\ParishStatistics;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class Partial2026StatisticsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_partial_report_extends_history_preserving_zeros_unknowns_and_annual_only_services(): void
    {
        $this->travelTo('2026-10-27');
        $this->artisan('analytics:import-history')->assertSuccessful();

        $this->artisan('analytics:import-history', ['--dataset' => '2026-partial'])->assertSuccessful();
        $this->artisan('analytics:import-history', ['--dataset' => '2026-partial'])->expectsOutput('0 verified monthly totals imported. Matching existing totals were left unchanged.')->assertSuccessful();

        $this->assertDatabaseCount('monthly_statistics', 660);
        $this->assertDatabaseCount('audit_logs', 2);
        $this->assertDatabaseEmpty('appointments');
        $this->assertDatabaseEmpty('service_records');
        $this->assertDatabaseHas('monthly_statistics', ['month' => '2026-01-01', 'service_type' => 'wedding', 'count' => 2, 'status' => 'confirmed']);
        $this->assertDatabaseHas('monthly_statistics', ['month' => '2026-01-01', 'service_type' => 'confirmation', 'count' => 0, 'status' => 'confirmed']);
        $this->assertDatabaseHas('monthly_statistics', ['month' => '2026-02-01', 'service_type' => 'wedding', 'count' => 0, 'status' => 'confirmed']);
        $this->assertDatabaseMissing('monthly_statistics', ['month' => '2026-01-01', 'service_type' => 'first_communion']);
        $this->assertDatabaseMissing('monthly_statistics', ['month' => '2026-10-01']);
        $statistics = app(ParishStatistics::class);
        $annual = $statistics->annual()[0];
        $this->assertSame(2026, $annual['year']);
        $this->assertSame([
            'baptism' => ['total' => 133, 'months' => 9],
            'wedding' => ['total' => 13, 'months' => 9],
            'funeral' => ['total' => 48, 'months' => 9],
            'confirmation' => ['total' => 187, 'months' => 9],
            'first_communion' => ['total' => null, 'months' => 0],
        ], $annual['services']);
        foreach (['baptism' => 22, 'wedding' => 2, 'funeral' => 6] as $service => $lastCount) {
            $history = $statistics->history($service);
            $this->assertCount(141, $history);
            $this->assertSame(['month' => '2026-09', 'count' => $lastCount], $history[140]);
            $this->assertNotContains(null, array_column($history, 'count'));
        }
    }

    public function test_partial_report_conflict_preserves_existing_corrections_and_inserts_nothing(): void
    {
        $this->travelTo('2026-10-27');
        MonthlyStatistic::factory()->create(['month' => '2026-09-01', 'service_type' => 'baptism', 'count' => 99]);

        $this->artisan('analytics:import-history', ['--dataset' => '2026-partial'])->assertFailed();

        $this->assertDatabaseCount('monthly_statistics', 1);
        $this->assertDatabaseHas('monthly_statistics', ['month' => '2026-09-01', 'count' => 99]);
        $this->assertDatabaseEmpty('audit_logs');
    }

    public function test_import_rejects_unfinished_report_months_unknown_datasets_and_demo_mode(): void
    {
        $this->travelTo('2026-09-30');

        $this->artisan('analytics:import-history', ['--dataset' => '2026-partial'])->assertFailed();
        $this->artisan('analytics:import-history', ['--dataset' => '../untrusted'])->assertFailed();
        config(['office.demo' => true]);
        $this->artisan('analytics:import-history', ['--dataset' => '2026-partial'])->assertFailed();

        $this->assertDatabaseEmpty('monthly_statistics');
    }

    public function test_statistics_shows_2026_as_partial_and_leaves_unsupplied_months_unknown(): void
    {
        $this->withoutVite();
        $this->travelTo('2026-10-27');
        $this->artisan('analytics:import-history')->assertSuccessful();
        $this->artisan('analytics:import-history', ['--dataset' => '2026-partial'])->assertSuccessful();
        $user = User::factory()->admin()->withTwoFactor()->create();
        $this->actingAs($user)->withSession(['admin.mfa_user_id' => $user->id, 'admin.mfa_fingerprint' => $user->adminSessionFingerprint()]);

        $response = $this->get('/admin/statistics');

        $response->assertOk()->assertViewHas('year', 2026)->assertViewHas('summary', ['total' => 133, 'months' => 9, 'change' => null]);
        $chart = $response->viewData('monthlyChart');
        $this->assertSame(0, $chart[6]['value']);
        $this->assertNull($chart[9]['value']);
        $this->get('/admin/statistics?service=confirmation')->assertSee('Confirmations by year')->assertDontSee('Monthly activity');
    }
}
