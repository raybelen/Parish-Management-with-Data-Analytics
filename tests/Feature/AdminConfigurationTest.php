<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\ForecastRun;
use App\Models\ParishSetting;
use App\Models\ServiceType;
use App\Models\User;
use App\Support\OfficeSettings;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminConfigurationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_office_settings_are_loaded_once_per_execution(): void
    {
        ParishSetting::query()->count();
        OfficeSettings::forgetMemoizedValues();
        DB::enableQueryLog();

        $first = OfficeSettings::all();
        $second = OfficeSettings::all();

        $settingQueries = array_filter(
            DB::getQueryLog(),
            fn (array $query): bool => str_contains($query['query'], 'parish_settings'),
        );
        $this->assertSame($first, $second);
        $this->assertCount(1, $settingQueries);
    }

    public function test_staff_cannot_change_settings_or_permissions(): void
    {
        $this->verified(User::factory()->staff()->withTwoFactor()->create());

        $this->get('/admin/settings')->assertForbidden();
        $this->put('/admin/settings', [])->assertForbidden();
        $this->put('/admin/permissions', ['password' => 'password'])->assertForbidden();
    }

    public function test_super_admin_can_configure_staff_permissions_without_granting_role_management(): void
    {
        $super = User::factory()->superAdmin()->withTwoFactor()->create();
        $staff = User::factory()->staff()->create();

        $this->verified($super)->put('/admin/permissions', ['password' => 'password', 'permissions' => ['staff' => ['appointments.view']]])->assertSessionHasNoErrors();

        $this->assertTrue($staff->can('appointments.view'));
        $this->assertFalse($staff->can('payments.manage'));
        $this->assertFalse($staff->can('manage-admin-roles'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'permissions.updated']);
    }

    public function test_settings_validate_operating_hours_and_keep_missing_months_explicit(): void
    {
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $data = [...config('office.defaults'), 'analytics_start' => '2020-01-01', 'missing_months_text' => '2020-03, 2020-04'];
        unset($data['missing_months']);

        $this->put('/admin/settings', [...$data, 'closes_at' => '05:00'])->assertSessionHasErrors('closes_at');
        $this->put('/admin/settings', $data)->assertSessionHasNoErrors();

        $this->assertSame(['2020-03', '2020-04'], OfficeSettings::all()['missing_months']);
    }

    public function test_admin_can_control_appointment_venue_availability(): void
    {
        $this->withoutVite();
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $existingAppointment = Appointment::factory()->create([
            'preferred_church' => 'San Isidro Labrador Chapel',
        ]);
        $data = [
            ...config('office.defaults'),
            'analytics_start' => '2020-01-01',
            'missing_months_text' => '',
            'active_venues_present' => '1',
            'active_venues' => ['St. John Nepomucene Parish', 'San Fernando Chapel'],
        ];
        unset($data['missing_months']);

        $this->get('/admin/settings')
            ->assertSee('Appointment venue availability')
            ->assertSee('St. John Nepomucene Parish')
            ->assertSee('San Isidro Labrador Chapel')
            ->assertSee('Nuestra Señora de los Desamparados')
            ->assertSee('San Fernando Chapel')
            ->assertSee('Santa Misericordia Chapel');
        $this->put('/admin/settings', $data)->assertSessionHasNoErrors();

        $this->assertSame(['St. John Nepomucene Parish', 'San Fernando Chapel'], OfficeSettings::activeVenues());
        $this->get('/appointments/new')
            ->assertSee('St. John Nepomucene Parish')
            ->assertSee('San Fernando Chapel')
            ->assertDontSee('San Isidro Labrador Chapel');
        $this->get('/admin/appointments/create')
            ->assertSee('San Fernando Chapel')
            ->assertDontSee('San Isidro Labrador Chapel');
        $this->get('/admin/appointments/'.$existingAppointment->reference_number.'/edit')
            ->assertSee('San Isidro Labrador Chapel (currently unavailable)');
        $this->assertSame('San Isidro Labrador Chapel', $existingAppointment->refresh()->preferred_church);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.updated']);

        $data['active_venues'] = [];
        $this->put('/admin/settings', $data)->assertSessionHasNoErrors();
        $this->assertSame([], OfficeSettings::activeVenues());
        $this->get('/appointments/new')
            ->assertSee('No venues currently available')
            ->assertSee('Please contact the parish office.');
    }

    public function test_new_service_accepts_optional_uploads_and_creates_recipient_without_registration(): void
    {
        $this->withoutVite();
        $service = ServiceType::factory()->create(['slug' => 'blessing', 'name' => 'House blessing', 'requirements' => ['letter' => ['label' => 'Request letter', 'required' => true, 'multiple' => false]]]);
        $payload = ['client_full_name' => 'Sample Contact', 'client_contact_number' => '09123456789', 'client_address' => 'Sample Address', 'preferred_contact_method' => 'phone', 'service_type' => $service->slug, 'preferred_date' => now()->addWeek()->toDateString(), 'preferred_time' => '09:00', 'preferred_church' => 'San Isidro Labrador Chapel', 'service' => ['recipient_full_name' => 'Sample Recipient']];

        $this->get('/appointments/new')->assertSee('House blessing')->assertSee('Online uploads are optional');
        $response = $this->post('/appointments', $payload)->assertSessionHasNoErrors();

        $appointment = Appointment::query()->sole();
        $this->assertDatabaseHas('appointment_participants', ['appointment_id' => $appointment->id, 'full_name' => 'Sample Recipient', 'role' => 'recipient']);
        $this->assertFalse($appointment->physicalDocumentsComplete());
        $this->get($response->headers->get('Location'))->assertSee('Sample Recipient');
        $this->assertDatabaseEmpty('users');
    }

    public function test_service_changes_preserve_existing_fee_and_document_snapshot(): void
    {
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $service = ServiceType::query()->where('slug', 'baptism')->firstOrFail();
        $appointment = Appointment::factory()->create(['fee_cents' => 15000, 'requirements' => $service->requirements]);

        $this->put('/admin/service-types/'.$service->id, ['name' => 'Baptism', 'duration_minutes' => 60, 'buffer_minutes' => 45, 'fee' => '200.50', 'active' => 1, 'requirements' => [['label' => 'Office form', 'required' => 1]]])->assertSessionHasNoErrors();

        $this->assertSame(20050, $service->refresh()->fee_cents);
        $this->assertSame(60, $service->duration_minutes);
        $this->assertSame(15000, $appointment->refresh()->fee_cents);
        $this->assertArrayHasKey('birth_certificate', $appointment->requirements);
    }

    public function test_unavailable_time_cannot_overwrite_confirmed_service(): void
    {
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        Appointment::factory()->create(['status' => 'confirmed', 'starts_at' => '2026-11-01 10:00', 'reserved_until' => '2026-11-01 11:30', 'confirmed_church' => 'San Isidro Labrador Chapel']);

        $this->post('/admin/schedule-blocks', ['title' => 'Maintenance', 'church' => 'San Isidro Labrador Chapel', 'starts_at' => '2026-11-01T11:15', 'ends_at' => '2026-11-01T12:00'])->assertSessionHasErrors('starts_at');

        $this->assertDatabaseEmpty('schedule_blocks');
    }

    public function test_backup_is_encrypted_and_restoration_refuses_populated_database(): void
    {
        Storage::fake('local');
        $appointment = Appointment::factory()->create();
        $this->verified(User::factory()->superAdmin()->withTwoFactor()->create());

        $this->post('/admin/backups', ['password' => 'password'])->assertDownload();

        $files = Storage::disk('local')->files('office-backups');
        $this->assertCount(1, $files);
        $contents = Storage::disk('local')->get($files[0]);
        $this->assertStringNotContainsString($appointment->client_full_name, $contents);
        $payload = json_decode(Crypt::decryptString($contents), true);
        $this->assertSame($appointment->id, $payload['tables']['appointments'][0]['id']);
        $this->artisan('office:restore', ['path' => $files[0]])->assertFailed();
        $this->assertModelExists($appointment);
    }

    public function test_reminders_are_deduplicated_per_day(): void
    {
        Appointment::factory()->create(['fee_cents' => 50000]);

        $this->artisan('office:reminders')->assertSuccessful();
        $this->artisan('office:reminders')->assertSuccessful();

        $this->assertDatabaseCount('office_notifications', 2);
    }

    public function test_office_forms_use_the_updated_service_fields_and_preserve_saved_values(): void
    {
        $this->withoutVite();
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $appointment = Appointment::factory()->create([
            'service_type' => 'wedding',
            'service_details' => [
                'groom_mother_maiden_name' => 'Ana Cruz',
                'bride_mother_maiden_name' => 'Luz Santos',
                'godfather_address' => '123 Parish Road',
            ],
        ]);

        $this->get('/admin/appointments/create')
            ->assertSee('name="service[father_origin_address]"', false)
            ->assertSee('name="service[mother_origin_address]"', false)
            ->assertSee('name="service[parents_current_residence]"', false)
            ->assertSee('name="service[godfather_address]"', false)
            ->assertSee('name="service[godmother_address]"', false)
            ->assertDontSee('name="service[parents_address]"', false)
            ->assertDontSee('name="service[godparents_address]"', false)
            ->assertSee('name="service[groom_mother_maiden_name]"', false)
            ->assertSee('name="service[bride_mother_maiden_name]"', false)
            ->assertSee('name="service[anointing_of_sick]"', false)
            ->assertDontSee('name="service[minister_name]"', false)
            ->assertDontSee('name="service[bride_contact_number]"', false);

        $this->get('/admin/appointments/'.$appointment->reference_number.'/edit')
            ->assertSee('Ana Cruz')
            ->assertSee('Luz Santos')
            ->assertSee('123 Parish Road')
            ->assertDontSee('name="service[minister_name]"', false);
    }

    public function test_contact_corrections_update_participants_and_are_audited(): void
    {
        $this->withoutVite();
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $appointment = Appointment::factory()->create();
        $appointment->update(['service_details' => [...$appointment->service_details, 'special_requests' => 'Retain this request']]);
        ServiceType::query()->where('slug', 'baptism')->update(['active' => false]);
        $payload = $appointment->only(['client_full_name', 'client_contact_number', 'client_address', 'preferred_contact_method', 'client_email', 'service_type', 'preferred_church']);
        $payload = [...$payload, 'preferred_date' => $appointment->preferred_date->toDateString(), 'preferred_time' => '09:00', 'service' => [...$appointment->service_details, 'child_full_name' => 'Correct Child'], 'change_reason' => 'Original certificate checked', 'version' => 1];
        unset($payload['service']['special_requests']);
        $this->get('/admin/appointments/'.$appointment->reference_number.'/edit')->assertSee($appointment->client_full_name);

        $this->patch('/admin/appointments/'.$appointment->reference_number.'/details', $payload)->assertSessionHasNoErrors();

        $this->assertDatabaseHas('appointment_participants', ['appointment_id' => $appointment->id, 'role' => 'child', 'full_name' => 'Correct Child']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'appointment.details_corrected', 'entity_id' => $appointment->id]);
        $this->assertSame('Retain this request', $appointment->refresh()->service_details['special_requests']);
    }

    public function test_disabled_analytics_are_hidden_without_removing_configuration_or_results(): void
    {
        $this->withoutVite();
        $this->withoutMiddleware(ThrottleRequests::class);
        config(['office.analytics_enabled' => false]);
        $user = User::factory()->superAdmin()->withTwoFactor()->create();
        $settings = ParishSetting::query()->where('key', 'office')->firstOrFail();
        $settings->update(['value' => ['analytics_start' => '2021-01-01', 'forecast_horizon' => 6]]);
        ForecastRun::factory()->create();
        $this->verified($user);

        $this->get('/admin')->assertOk()->assertDontSee('Parish historical statistics')->assertDontSee('Service forecasts');
        $this->get('/admin/settings')->assertOk()->assertDontSee('Advanced SARIMA parameters')->assertDontSee('Forecasts Manage');
        $this->get('/admin/reports')->assertOk()->assertDontSee('Monthly completed-service statistics')->assertDontSee('Forecast results and configuration');
        $this->get('/admin/forecasts')->assertNotFound();
        $this->post('/admin/forecasts')->assertNotFound();
        $this->get('/admin/statistics')->assertNotFound();
        $this->post('/admin/statistics')->assertNotFound();
        $this->post('/admin/statistics/import')->assertNotFound();
        $this->get('/admin/reports?type=monthly&export=1')->assertSessionHasErrors('type');
        $this->artisan('analytics:forecast')->assertFailed();

        $this->put('/admin/settings', [
            'parish_name' => 'St. John Nepomucene Parish', 'opens_at' => '06:00', 'closes_at' => '20:00',
            'operating_days' => [1, 2, 3, 4, 5, 6, 7],
        ])->assertSessionHasNoErrors();

        $this->assertSame('2021-01-01', $settings->refresh()->value['analytics_start']);
        $this->assertDatabaseCount('forecast_runs', 1);
        $this->assertFalse($user->can('forecasts.manage'));
    }

    private function verified(User $user): static
    {
        return $this->actingAs($user)->withSession(['admin.mfa_user_id' => $user->id, 'admin.mfa_fingerprint' => $user->adminSessionFingerprint()]);
    }
}
