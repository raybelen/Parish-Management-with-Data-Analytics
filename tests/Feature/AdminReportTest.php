<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\ForecastRun;
use App\Models\ParishSetting;
use App\Models\Payment;
use App\Models\ServiceRecord;
use App\Models\ServiceType;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AdminReportTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_appointment_preview_and_csv_match_saved_records_and_filters(): void
    {
        $this->signIn();
        $appointment = Appointment::factory()->create(['client_full_name' => 'María, Santos', 'service_type' => 'baptism', 'status' => 'completed', 'created_at' => '2026-11-01 00:00:00', 'completed_at' => '2026-11-10 12:00:00']);
        Appointment::factory()->create(['service_type' => 'wedding', 'status' => 'completed']);
        Appointment::factory()->create(['service_type' => 'baptism', 'status' => 'pending']);
        Appointment::factory()->create(['service_type' => 'baptism', 'status' => 'completed', 'created_at' => '2026-10-31 23:59:59']);
        Appointment::factory()->create(['service_type' => 'baptism', 'status' => 'completed', 'source' => 'synthetic']);
        $filters = ['type' => 'appointments', 'service' => 'baptism', 'status' => 'completed', 'from' => '2026-11-01', 'to' => '2026-11-25'];

        $rows = $this->matchingPreviewAndCsv($filters);
        $this->assertCount(1, $rows);
        $this->assertSame($appointment->reference_number, $rows[0]['appointment']);
        $this->assertSame('María, Santos', $rows[0]['contact']);
        $this->assertSame($appointment->preferred_date->toDateString(), $rows[0]['requested_date']);
        $this->assertSame('2026-11-10 12:00:00', $rows[0]['completed_at']);
    }

    public function test_payments_and_refunds_reference_saved_receipts_and_label_test_entries(): void
    {
        $this->signIn();
        $appointment = Appointment::factory()->create(['service_type' => 'baptism']);
        $real = Payment::factory()->for($appointment)->create(['amount_cents' => 12345, 'method' => 'cash', 'paid_at' => '2026-11-01 00:00:00']);
        $test = Payment::factory()->for($appointment)->create(['amount_cents' => 40000, 'method' => 'xendit_sandbox']);
        $refund = Payment::factory()->for($appointment)->create(['kind' => 'refund', 'amount_cents' => 5000, 'method' => 'cash', 'original_payment_id' => $test->id, 'paid_at' => '2026-11-25 23:59:59']);
        Payment::factory()->for($appointment)->create(['paid_at' => '2026-11-26 00:00:00']);
        Payment::factory()->for(Appointment::factory()->create(['source' => 'synthetic']))->create();
        $rows = $this->matchingPreviewAndCsv(['type' => 'payments', 'from' => '2026-11-01', 'to' => '2026-11-25', 'status' => 'invalid-ignored']);

        $this->assertCount(3, $rows);
        $this->assertSame(['No', 'Yes', 'Yes'], array_column($rows, 'test_transaction'));
        $this->assertSame('123.45', $rows[0]['amount']);
        $this->assertSame($real->receiptNumber(), $rows[0]['receipt_number']);
        $this->assertSame($test->receiptNumber(), $rows[2]['original_payment_receipt']);
        $this->assertSame($refund->receiptNumber(), $rows[2]['receipt_number']);
        $this->assertSame('refund', $rows[2]['kind']);
        $this->assertSame($appointment->reference_number, $rows[2]['appointment']);
    }

    public function test_record_export_preserves_register_references_dates_and_archival_sources(): void
    {
        $this->signIn();
        $record = ServiceRecord::factory()->create(['service_type' => 'baptism', 'source' => 'archival', 'service_date' => '2020-01-31', 'participant_names' => "José Santos, Ana Reyes\nSecond line", 'register_book' => 'B-2', 'register_page' => '12', 'register_entry' => '45', 'archived_at' => now()]);
        ServiceRecord::factory()->create(['service_type' => 'baptism', 'service_date' => '2020-02-01']);
        $rows = $this->matchingPreviewAndCsv(['type' => 'records', 'from' => '2020-01-01', 'to' => '2020-01-31', 'service' => 'baptism']);
        $this->assertCount(1, $rows);
        $this->assertSame($record->record_number, $rows[0]['record_number']);
        $this->assertSame('2020-01-31', $rows[0]['service_date']);
        $this->assertSame($record->participant_names, $rows[0]['participant_names']);
        $this->assertSame(['B-2', '12', '45'], [$rows[0]['register_book'], $rows[0]['register_page'], $rows[0]['register_entry']]);
        $this->assertSame('archival', $rows[0]['source']);
        $this->assertSame($record->archived_at->toDateTimeString(), $rows[0]['archived_at']);
    }

    public function test_outstanding_balances_use_all_dates_and_saved_payment_refund_amounts(): void
    {
        $this->signIn();
        $appointment = Appointment::factory()->create(['fee_cents' => 100000, 'status' => 'confirmed', 'created_at' => '2020-01-01']);
        $payment = Payment::factory()->for($appointment)->create(['amount_cents' => 40000]);
        Payment::factory()->for($appointment)->create(['kind' => 'refund', 'original_payment_id' => $payment->id, 'amount_cents' => 10000]);
        Appointment::factory()->create(['fee_cents' => 900000, 'status' => 'cancelled']);
        $rows = $this->matchingPreviewAndCsv(['type' => 'outstanding', 'from' => 'invalid-ignored', 'to' => 'invalid-ignored']);
        $this->assertCount(1, $rows);
        $this->assertSame($appointment->reference_number, $rows[0]['appointment']);
        $this->assertSame('300', $rows[0]['net_paid']);
        $this->assertSame('700', $rows[0]['balance']);
        $this->get(route('admin.reports.index', ['type' => 'outstanding', 'export' => 1]))->assertDownload('outstanding-all-dates.csv');
    }

    public function test_monthly_report_uses_completion_dates_and_net_payments_recorded_to_date(): void
    {
        $this->signIn();
        $appointment = Appointment::factory()->create(['service_type' => 'baptism', 'status' => 'completed', 'completed_at' => '2026-10-31 23:59:59']);
        $payment = Payment::factory()->for($appointment)->create(['amount_cents' => 50000, 'paid_at' => '2026-11-01']);
        Payment::factory()->for($appointment)->create(['kind' => 'refund', 'original_payment_id' => $payment->id, 'amount_cents' => 12500]);
        Appointment::factory()->create(['service_type' => 'baptism', 'status' => 'completed', 'completed_at' => '2026-11-01']);
        $rows = $this->matchingPreviewAndCsv(['type' => 'monthly', 'service' => 'baptism', 'from' => '2026-10-01', 'to' => '2026-10-31']);
        $this->assertCount(1, $rows);
        $this->assertSame('2026-10', $rows[0]['month']);
        $this->assertSame('1', $rows[0]['completed_services']);
        $this->assertSame('375', $rows[0]['net_collections_for_completed_services']);
    }

    public function test_forecast_csv_expands_saved_predictions_and_keeps_unavailable_runs_blank(): void
    {
        $this->signIn();
        $run = ForecastRun::factory()->create(['service_type' => 'baptism', 'status' => 'ready', 'history' => [['month' => '2025-12', 'count' => 10]], 'configuration' => ['data_source' => 'confirmed_monthly_statistics', 'forecast_horizon' => 2], 'results' => ['count' => ['forecast' => [['estimate' => 12.5, 'lower' => 4.2, 'upper' => 20.8], ['estimate' => 13, 'lower' => 5, 'upper' => 21]]]], 'evaluation' => ['mae' => 2.5]]);
        $empty = ForecastRun::factory()->create(['service_type' => 'baptism', 'status' => 'insufficient_data', 'message' => 'More history needed.']);
        ForecastRun::factory()->create(['service_type' => 'wedding']);
        $this->get(route('admin.reports.index', ['type' => 'forecast', 'service' => 'baptism', 'preview' => 1]))
            ->assertOk()
            ->assertSee('Expected count')
            ->assertSee('Planning range')
            ->assertSee('Jan 2026')
            ->assertSee('View saved forecast details')
            ->assertDontSee('Planning Lower')
            ->assertDontSee('forecast_horizon')
            ->assertDontSee('confirmed_monthly_statistics');
        $rows = $this->matchingPreviewAndCsv(['type' => 'forecast', 'service' => 'baptism', 'from' => '2026-11-01', 'to' => '2026-11-25']);
        $this->assertCount(3, $rows);
        $this->assertSame(['Service', 'Forecast month', 'Expected count', 'Planning range', 'Generated', 'Status', 'Records through', 'Saved run', 'Data source'], array_keys($rows[0]));
        $this->assertSame((string) $empty->id, $rows[0]['Saved run']);
        $this->assertSame('', $rows[0]['Expected count']);
        $this->assertSame('', $rows[0]['Planning range']);
        $this->assertSame('More records needed', $rows[0]['Status']);
        $this->assertSame((string) $run->id, $rows[1]['Saved run']);
        $this->assertSame(['Jan 2026', 'Feb 2026'], array_column(array_slice($rows, 1), 'Forecast month'));
        $this->assertSame('13', $rows[1]['Expected count']);
        $this->assertSame('4 to 21', $rows[1]['Planning range']);
        $this->assertSame('Estimates prepared', $rows[1]['Status']);
        $this->assertSame('Nov 25, 2026 12:00 PM', $rows[1]['Generated']);
        $this->assertSame('Dec 2025', $rows[1]['Records through']);
        $this->assertSame(ServiceType::query()->where('slug', 'baptism')->value('name'), $rows[1]['Service']);
        $this->assertSame(12.5, $run->fresh()->results['count']['forecast'][0]['estimate']);
    }

    public function test_preview_is_limited_empty_safe_and_requires_export_permissions(): void
    {
        $this->signIn();
        Appointment::factory()->count(12)->create();
        $this->get(route('admin.reports.index', ['preview' => 1]))->assertOk()
            ->assertViewHas('preview', fn (array $preview): bool => $preview['count'] === 12 && count($preview['rows']) === 10);
        $this->get(route('admin.reports.index', ['preview' => 1, 'from' => '2000-01-01', 'to' => '2000-01-31']))
            ->assertOk()->assertSee('No records match these filters.');
        ParishSetting::query()->where('key', 'office')->update(['value' => ['permissions' => ['admin' => ['reports.view']]]]);
        foreach (['appointments', 'records', 'payments', 'outstanding', 'monthly', 'forecast'] as $type) {
            $this->get(route('admin.reports.index', ['type' => $type, 'preview' => 1]))->assertForbidden();
            $this->get(route('admin.reports.index', ['type' => $type, 'export' => 1]))->assertForbidden();
        }
        $this->get(route('admin.reports.index'))->assertOk()->assertSee('Your account does not have permission');
    }

    public function test_default_dates_cannot_produce_an_inverted_reporting_period(): void
    {
        $this->signIn();
        $this->get(route('admin.reports.index', ['to' => '2025-12-31', 'preview' => 1]))
            ->assertSessionHasErrors(['to' => 'The through date must be on or after the from date.']);
    }

    /** @param array<string, string> $filters
     * @return list<array<string, string>>
     */
    private function matchingPreviewAndCsv(array $filters): array
    {
        $preview = $this->get(route('admin.reports.index', [...$filters, 'preview' => 1]))->assertOk()->viewData('preview');
        $csv = $this->get(route('admin.reports.index', [...$filters, 'export' => 1]))->assertDownload()->streamedContent();
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, $csv);
        rewind($stream);
        $columns = fgetcsv($stream, 0, ',', '"', '');
        $rows = [];
        while (($values = fgetcsv($stream, 0, ',', '"', '')) !== false) {
            $this->assertCount(count($columns), $values);
            $rows[] = array_combine($columns, $values);
        }
        fclose($stream);
        $this->assertSame($preview['count'], count($rows));
        if (($filters['type'] ?? '') !== 'forecast') {
            $this->assertSame($preview['columns'], $columns);
            foreach ($preview['rows'] as $index => $row) {
                $this->assertSame(array_map(fn ($value): string => (string) ($value ?? ''), $row), array_values($rows[$index]));
            }
        }

        return $rows;
    }

    private function signIn(): void
    {
        $this->withoutVite();
        $this->travelTo('2026-11-25 12:00:00');
        $user = User::factory()->admin()->withTwoFactor()->create();
        $this->actingAs($user)->withSession(['admin.mfa_user_id' => $user->id, 'admin.mfa_fingerprint' => $user->adminSessionFingerprint()]);
    }
}
