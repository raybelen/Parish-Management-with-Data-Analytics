<?php

namespace Tests\Feature;

use App\Actions\ImportServiceRecords;
use App\Models\Appointment;
use App\Models\ForecastRun;
use App\Models\MonthlyStatistic;
use App\Models\ParishSetting;
use App\Models\Payment;
use App\Models\ServiceRecord;
use App\Models\User;
use App\Support\AnalyticsData;
use App\Support\SarimaEngine;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AdminRecordsAnalyticsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_archival_entry_cannot_opt_into_analytics_or_link_itself_to_an_appointment(): void
    {
        $this->travelTo('2026-10-04');
        $admin = User::factory()->admin()->withTwoFactor()->create();
        $data = ServiceRecord::factory()->make()->only(['record_number', 'service_type', 'service_date', 'participant_names', 'church']);
        $data['service_date'] = '2020-01-01';

        $this->verified($admin)->post('/admin/records', [...$data, 'reason' => 'Transcribed from register', 'source' => 'operational', 'appointment_id' => 123])->assertSessionHasNoErrors();

        $record = ServiceRecord::query()->sole();
        $this->assertSame('archival', $record->source);
        $this->assertNull($record->appointment_id);
        $this->assertDatabaseEmpty('appointments');
    }

    public function test_record_import_is_atomic_and_excludes_duplicates(): void
    {
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $header = implode(',', ImportServiceRecords::COLUMNS)."\n";
        $good = "OLD-1,baptism,2020-01-01,Sample Name,San Isidro Labrador Chapel,Fr Sample,1,2,3,Register copy\n";
        $bad = "OLD-2,wedding,invalid,Sample Couple,San Isidro Labrador Chapel,,,,,\n";

        $this->post('/admin/records/import', ['file' => UploadedFile::fake()->createWithContent('records.csv', $header.$good.$bad), 'reason' => 'Register import'])->assertSessionHasErrors('file');
        $this->assertDatabaseEmpty('service_records');
        $this->post('/admin/records/import', ['file' => UploadedFile::fake()->createWithContent('records.csv', $header.$good), 'reason' => 'Register import'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('service_records', ['record_number' => 'OLD-1', 'source' => 'archival']);
        $this->post('/admin/records/import', ['file' => UploadedFile::fake()->createWithContent('records.csv', $header.$good), 'reason' => 'Repeated file'])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('service_records', 1);
    }

    public function test_record_correction_and_archival_preserve_history(): void
    {
        $this->withoutVite();
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $record = ServiceRecord::factory()->create();
        $data = $record->only(['record_number', 'service_type', 'participant_names', 'church']);
        $this->get('/admin/records/'.$record->id)->assertSee($record->record_number);

        $this->put('/admin/records/'.$record->id, [...$data, 'service_date' => '2020-01-01', 'participant_names' => 'Corrected name', 'reason' => 'Compared to original register', 'version' => 1])->assertSessionHasNoErrors();
        $this->delete('/admin/records/'.$record->id, ['reason' => 'Duplicate register transcription', 'archive' => 1, 'version' => 2])->assertSessionHasNoErrors();

        $this->assertNotNull($record->refresh()->archived_at);
        $this->assertSame('Corrected name', $record->participant_names);
        $this->assertDatabaseHas('audit_logs', ['action' => 'record.corrected', 'entity_id' => $record->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'record.archive_changed', 'entity_id' => $record->id]);
    }

    public function test_records_can_be_browsed_by_service_and_register_book(): void
    {
        $this->withoutVite();
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        ServiceRecord::factory()->create(['record_number' => 'BAP-BOOK-1', 'service_type' => 'baptism', 'register_book' => 'Book 1', 'participant_names' => 'Baptism Book One']);
        ServiceRecord::factory()->create(['record_number' => 'BAP-UNASSIGNED', 'service_type' => 'baptism', 'register_book' => null, 'participant_names' => 'Baptism Unassigned']);
        ServiceRecord::factory()->create(['record_number' => 'WED-BOOK-1', 'service_type' => 'wedding', 'register_book' => 'Book 1', 'participant_names' => 'Wedding Book One']);

        $this->get('/admin/records')
            ->assertSee('Baptism')
            ->assertSee('Wedding')
            ->assertDontSee('Baptism Book One');
        $this->get('/admin/records/services/baptism')
            ->assertSee('Book 1')
            ->assertDontSee('Unassigned book')
            ->assertDontSee('Wedding Book One');
        $this->get('/admin/records/services/baptism/book?book=Book%201')
            ->assertSee('Baptism Book One')
            ->assertDontSee('Wedding Book One')
            ->assertSee('Baptism Unassigned');
        $this->get('/admin/records/services/baptism/book?unassigned=1')
            ->assertSee('Baptism Unassigned')
            ->assertDontSee('Baptism Book One');
        $this->get('/admin/records/services/baptism/book')->assertNotFound();
    }

    public function test_register_details_can_be_saved_corrected_and_displayed_without_losing_snapshot_details(): void
    {
        $this->withoutVite();
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $record = ServiceRecord::factory()->create(['service_type' => 'baptism', 'register_book' => null, 'details' => ['child_sex' => 'male', 'father_name' => 'Original father']]);
        $data = $record->only(['record_number', 'service_type', 'participant_names', 'church']);
        $this->put('/admin/records/'.$record->id, [...$data, 'service_date' => '2020-01-01', 'reason' => 'Verified register', 'version' => 1, 'register_details' => ['father_name' => 'Corrected father', 'child_date_of_birth' => '2019-12-05', 'godmother_address' => '<script>alert(1)</script>']])->assertSessionHasNoErrors();

        $this->assertSame('male', $record->refresh()->details['child_sex']);
        $this->assertSame('Corrected father', $record->details['father_name']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'record.corrected', 'entity_id' => $record->id]);
        $this->get('/admin/records/services/baptism/book?book=Book%201')->assertOk()
            ->assertSee('Birthday year')->assertSee('2019')->assertSee('12/05')
            ->assertSee('Corrected father')->assertSee('<script>alert(1)</script>')
            ->assertDontSee('<script>alert(1)</script>', false);
        $this->get('/admin/records/'.$record->id)->assertOk()->assertSee('register_details[father_name]', false)->assertSee('Corrected father');
    }

    public function test_each_register_displays_its_service_specific_information(): void
    {
        $this->withoutVite();
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        foreach ([
            ['wedding', ['groom_full_name' => 'Groom Sample', 'bride_full_name' => 'Bride Sample', 'bride_mother_maiden_name' => 'Mother Maiden'], ['Marriage year', 'Groom Sample', 'Bride Sample', 'Mother Maiden']],
            ['funeral', ['deceased_full_name' => 'Deceased Sample', 'date_of_death' => '2019-12-31', 'date_of_burial' => '2020-01-03', 'contact_relationship' => 'Daughter'], ['Year of death', '2019', '12/31', '2020-01-03', 'Daughter']],
            ['confirmation', ['recipient_full_name' => 'Candidate Sample', 'place_of_baptism' => 'Baptism Parish Sample', 'age' => 0], ['Confirmation year', 'Candidate Sample', 'Baptism Parish Sample']],
        ] as [$service, $details, $expected]) {
            $record = ServiceRecord::factory()->create(['service_type' => $service, 'register_book' => 'Book 1', 'details' => $details, 'officiant' => 'Fr. Register Sample']);
            $this->get('/admin/records/services/'.$service.'/book?book=Book%201')->assertOk()->assertSee($expected)->assertSee('Fr. Register Sample');
            $this->get('/admin/records/'.$record->id)->assertOk();
        }
    }

    public function test_historical_register_details_are_validated_and_persisted(): void
    {
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $data = ['record_number' => 'DEATH-NEW', 'service_type' => 'funeral', 'service_date' => '2020-01-03', 'participant_names' => 'Historical deceased', 'church' => 'Parish church', 'reason' => 'Register transcription'];
        $this->post('/admin/records', [...$data, 'register_details' => ['date_of_burial' => 'invalid', 'age' => -1]])->assertSessionHasErrors(['register_details.date_of_burial', 'register_details.age']);
        $this->assertDatabaseEmpty('service_records');
        $this->post('/admin/records', [...$data, 'register_details' => ['unexpected' => 'value']])->assertSessionHasErrors('register_details');
        $this->post('/admin/records', [...$data, 'register_details' => ['deceased_full_name' => 'Verified deceased name', 'date_of_burial' => '2020-01-03', 'age' => 80]])->assertSessionHasNoErrors();
        $record = ServiceRecord::query()->sole();
        $this->assertSame('2020-01-03', $record->details['date_of_burial']);
        $this->assertSame('Verified deceased name', $record->participant_names);
        $this->assertSame('archival', $record->source);
    }

    public function test_analytics_excludes_synthetic_archival_and_failed_services_but_tracks_cash_separately(): void
    {
        $this->travelTo('2026-10-04');
        ParishSetting::query()->where('key', 'office')->update(['value' => ['analytics_start' => '2026-08-01']]);
        $completed = Appointment::factory()->create(['status' => 'completed', 'completed_at' => '2026-08-15 10:00', 'fee_cents' => 20000]);
        Payment::factory()->create(['appointment_id' => $completed->id, 'amount_cents' => 10000, 'paid_at' => '2026-08-01']);
        Payment::factory()->create(['appointment_id' => $completed->id, 'kind' => 'refund', 'amount_cents' => 2000, 'paid_at' => '2026-08-20']);
        foreach (['cancelled', 'declined', 'no_show'] as $status) {
            $other = Appointment::factory()->create(['status' => $status, 'completed_at' => '2026-08-15']);
            Payment::factory()->create(['appointment_id' => $other->id, 'amount_cents' => 5000, 'paid_at' => '2026-08-01']);
        }
        Appointment::factory()->create(['source' => 'synthetic', 'status' => 'completed', 'completed_at' => '2026-08-15']);
        ServiceRecord::factory()->create(['source' => 'archival', 'service_date' => '2026-08-15']);

        $analytics = new AnalyticsData;
        $history = $analytics->monthly();
        $totals = $analytics->totals('2026-08-01', '2026-08-31');

        $this->assertSame(1, $history[0]['count']);
        $this->assertSame(80.0, $history[0]['revenue']);
        $this->assertSame(0, $history[1]['count']);
        $this->assertSame(250, (int) $totals['cash_collected']);
        $this->assertSame(120, (int) $totals['outstanding']);
    }

    public function test_unknown_months_are_not_zero_and_insufficient_data_does_not_call_sarima(): void
    {
        $this->travelTo('2026-10-04');
        ParishSetting::query()->where('key', 'office')->update(['value' => ['analytics_start' => '2026-08-01', 'missing_months' => ['2026-09']]]);
        $this->mock(SarimaEngine::class)->shouldNotReceive('forecast');
        MonthlyStatistic::factory()->create(['month' => '2026-06-01']);
        MonthlyStatistic::factory()->create(['month' => '2026-08-01']);
        $this->verified(User::factory()->admin()->withTwoFactor()->create());

        $this->post('/admin/forecasts')->assertRedirect();

        $run = ForecastRun::query()->sole();
        $this->assertSame('insufficient_data', $run->status);
        $this->assertNull($run->history[1]['count']);
        $this->assertNull($run->results);
    }

    public function test_exports_enforce_permissions_and_neutralize_spreadsheet_formulas(): void
    {
        $admin = User::factory()->admin()->withTwoFactor()->create();
        $record = ServiceRecord::factory()->create(['participant_names' => '=HYPERLINK("example")']);
        $this->verified($admin);

        $response = $this->get('/admin/reports?type=records&from=2019-01-01&to=2021-01-01&export=1');

        $response->assertDownload();
        $this->assertStringContainsString("'=HYPERLINK", $response->streamedContent());
        $this->assertDatabaseHas('audit_logs', ['action' => 'report.exported']);
        $settings = ParishSetting::query()->where('key', 'office')->firstOrFail();
        $settings->update(['value' => ['permissions' => ['admin' => ['reports.view']]]]);
        $this->get('/admin/reports?type=payments&export=1')->assertForbidden();
        $this->get('/admin/reports?type=records&export=1')->assertForbidden();
    }

    public function test_demo_seeding_refuses_live_mode_and_existing_records(): void
    {
        $this->artisan('analytics:seed-demo')->assertFailed();
        config(['office.demo' => true]);
        Appointment::factory()->create();

        $this->artisan('analytics:seed-demo')->assertFailed();

        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_monthly_export_includes_current_and_older_months_with_exact_date_boundaries(): void
    {
        $this->travelTo('2026-10-04');
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        ParishSetting::query()->where('key', 'office')->update(['value' => ['analytics_start' => '2026-09-01', 'training_months' => 36]]);
        Appointment::factory()->create(['status' => 'completed', 'completed_at' => '2021-01-15']);
        Appointment::factory()->create(['status' => 'completed', 'completed_at' => '2026-10-02']);
        Appointment::factory()->create(['status' => 'completed', 'completed_at' => '2026-10-04']);
        Appointment::factory()->create(['status' => 'completed', 'completed_at' => '2026-10-01', 'source' => 'synthetic']);

        $response = $this->get('/admin/reports?type=monthly&from=2021-01-01&to=2026-10-03&export=1');

        $response->assertDownload();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('2021-01,1,0', $csv);
        $this->assertStringContainsString('2026-10,1,0', $csv);
    }

    public function test_restricted_permissions_do_not_expose_forecast_finances_or_dashboard_appointments(): void
    {
        $this->withoutVite();
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        ParishSetting::query()->where('key', 'office')->update(['value' => ['permissions' => ['admin' => ['forecasts.manage']]]]);
        Appointment::factory()->create(['client_full_name' => 'Hidden booking contact', 'status' => 'confirmed', 'starts_at' => now()->addDay(), 'reserved_until' => now()->addDays(2)]);

        $this->get('/admin/forecasts')->assertForbidden();
        $this->post('/admin/forecasts')->assertForbidden();
        $this->get('/admin')->assertDontSee('Hidden booking contact')->assertDontSee('Upcoming services');

        $this->assertDatabaseEmpty('forecast_runs');
    }

    private function verified(User $user): static
    {
        return $this->actingAs($user)->withSession(['admin.mfa_user_id' => $user->id, 'admin.mfa_fingerprint' => $user->adminSessionFingerprint()]);
    }
}
