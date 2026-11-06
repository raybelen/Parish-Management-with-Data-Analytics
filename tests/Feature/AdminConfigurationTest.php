<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\ForecastRun;
use App\Models\ParishSetting;
use App\Models\Personnel;
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

    public function test_builtin_services_have_an_example_fee_for_sandbox_payment_testing(): void
    {
        $fees = ServiceType::query()
            ->whereIn('slug', ['baptism', 'wedding', 'funeral'])
            ->pluck('fee_cents', 'slug')
            ->all();

        $this->assertSame([
            'baptism' => 10000,
            'funeral' => 10000,
            'wedding' => 10000,
        ], $fees);
    }

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
        $personnel = Personnel::factory()->create();
        $this->verified(User::factory()->staff()->withTwoFactor()->create());

        $this->get('/admin/settings')->assertForbidden();
        $this->put('/admin/settings', [])->assertForbidden();
        $this->put('/admin/permissions', ['password' => 'password'])->assertForbidden();
        $this->delete('/admin/personnel/'.$personnel->id)->assertForbidden();
        $this->assertModelExists($personnel);
    }

    public function test_admin_can_remove_unused_personnel(): void
    {
        $personnel = Personnel::factory()->create(['name' => 'Unused personnel']);
        $this->withoutVite();
        $this->verified(User::factory()->admin()->withTwoFactor()->create());

        $this->get('/admin/settings')
            ->assertOk()
            ->assertSee(route('admin.personnel.destroy', $personnel), false)
            ->assertSee('Remove');
        $this->delete('/admin/personnel/'.$personnel->id)
            ->assertRedirect()
            ->assertSessionHas('status', 'Personnel removed.');

        $this->assertModelMissing($personnel);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'personnel.removed',
            'entity_id' => $personnel->id,
        ]);
    }

    public function test_personnel_with_history_or_a_rotation_position_cannot_be_removed(): void
    {
        $assignedPersonnel = Personnel::factory()->create();
        Appointment::factory()->create(['personnel_id' => $assignedPersonnel->id]);
        $rotationPersonnel = Personnel::factory()->rotationPosition(1)->create();
        $this->verified(User::factory()->admin()->withTwoFactor()->create());

        $this->delete('/admin/personnel/'.$assignedPersonnel->id)
            ->assertRedirect()
            ->assertSessionHasErrors(['personnel' => 'Personnel with appointment or schedule history cannot be removed. Set their availability to Inactive instead.']);
        $this->delete('/admin/personnel/'.$rotationPersonnel->id)
            ->assertRedirect()
            ->assertSessionHasErrors(['personnel' => 'This person is part of the priest rotation. Assign another priest to that position before removing them.']);

        $this->assertModelExists($assignedPersonnel);
        $this->assertModelExists($rotationPersonnel);
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
        $this->get('/admin/appointments/'.$existingAppointment->reference_number)
            ->assertSee('San Isidro Labrador Chapel')
            ->assertDontSee('name="confirmed_church"', false);
        $this->assertSame('San Isidro Labrador Chapel', $existingAppointment->refresh()->preferred_church);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.updated']);

        $data['active_venues'] = [];
        $this->put('/admin/settings', $data)->assertSessionHasNoErrors();
        $this->assertSame([], OfficeSettings::activeVenues());
        $this->get('/appointments/new')
            ->assertSee('No venues currently available')
            ->assertSee('Please contact the parish office.');
    }

    public function test_new_service_lists_requirements_and_creates_recipient_without_registration(): void
    {
        $this->withoutVite();
        $service = ServiceType::factory()->create(['slug' => 'blessing', 'name' => 'House blessing', 'requirements' => ['letter' => ['label' => 'Request letter', 'required' => true, 'multiple' => false]]]);
        $payload = ['client_full_name' => 'Sample Contact', 'client_contact_number' => '09123456789', 'client_address' => 'Sample Address', 'preferred_contact_method' => 'phone', 'service_type' => $service->slug, 'preferred_date' => now()->addWeek()->toDateString(), 'preferred_time' => '09:00', 'preferred_church' => 'San Isidro Labrador Chapel', 'payment_option' => 'walk_in', 'service' => ['recipient_full_name' => 'Sample Recipient']];

        $this->get('/appointments/new')
            ->assertSee('House blessing')
            ->assertSee('Request letter')
            ->assertDontSee('type="file"', false);
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

        $this->put('/admin/service-types/'.$service->id, ['name' => 'Baptism', 'duration_minutes' => 60, 'buffer_minutes' => 0, 'fee' => '200.50', 'active' => 1, 'requirements' => [['label' => 'Office form', 'required' => 1]]])->assertSessionHasNoErrors();

        $this->assertSame(20050, $service->refresh()->fee_cents);
        $this->assertSame(60, $service->duration_minutes);
        $this->assertSame(15000, $appointment->refresh()->fee_cents);
        $this->assertArrayHasKey('birth_certificate', $appointment->requirements);
    }

    public function test_unavailable_time_cannot_overwrite_confirmed_service(): void
    {
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        Appointment::factory()->create(['status' => 'confirmed', 'starts_at' => '2026-11-01 10:00', 'ends_at' => '2026-11-01 11:00', 'reserved_until' => '2026-11-01 11:30', 'confirmed_church' => 'San Isidro Labrador Chapel']);

        $this->post('/admin/schedule-blocks', ['title' => 'Maintenance', 'church' => 'San Isidro Labrador Chapel', 'starts_at' => '2026-11-01T10:45', 'ends_at' => '2026-11-01T12:00'])->assertSessionHasErrors('starts_at');

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

    public function test_expiry_command_releases_only_overdue_unverified_reservations(): void
    {
        $this->travelTo('2026-10-04 09:00:00');
        $overdue = Appointment::factory()->create([
            'source' => OfficeSettings::source(),
            'status' => 'pending',
            'documents_due_at' => '2026-10-04 08:59:00',
        ]);
        $future = Appointment::factory()->create([
            'source' => OfficeSettings::source(),
            'status' => 'pending',
            'documents_due_at' => '2026-10-04 09:01:00',
            'starts_at' => '2026-10-04 09:01:00',
        ]);
        $pastAppointment = Appointment::factory()->create([
            'source' => OfficeSettings::source(),
            'status' => 'pending',
            'documents_due_at' => '2026-10-04 12:00:00',
            'starts_at' => '2026-10-04 08:59:00',
        ]);
        $confirmed = Appointment::factory()->create([
            'source' => OfficeSettings::source(),
            'status' => 'confirmed',
            'documents_due_at' => '2026-10-04 08:59:00',
        ]);

        $this->artisan('appointments:expire-unverified')
            ->expectsOutput('2 unverified appointment reservation(s) expired.')
            ->assertSuccessful();

        $this->assertSame('expired', $overdue->refresh()->status);
        $this->assertSame('pending', $future->refresh()->status);
        $this->assertSame('expired', $pastAppointment->refresh()->status);
        $this->assertSame(
            'The scheduled appointment time passed before physical document verification was completed.',
            $pastAppointment->outcome,
        );
        $this->assertSame('confirmed', $confirmed->refresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'appointment.expired', 'entity_id' => $overdue->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'appointment.expired', 'entity_id' => $pastAppointment->id]);
    }

    public function test_office_creation_form_uses_the_updated_service_fields(): void
    {
        $this->withoutVite();
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
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
    }

    public function test_staff_cannot_edit_client_or_participant_details_after_creation(): void
    {
        $this->withoutVite();
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $appointment = Appointment::factory()->create();
        $before = $appointment->refresh()->getAttributes();

        $this->get('/admin/appointments/'.$appointment->reference_number.'/edit')->assertNotFound();
        $this->patch('/admin/appointments/'.$appointment->reference_number.'/details', [
            'client_full_name' => 'Changed by staff',
        ])->assertNotFound();

        $this->assertSame($before, $appointment->refresh()->getAttributes());
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

        $this->get('/admin')->assertOk()->assertDontSee('Parish historical statistics')->assertDontSee('Service Analytics');
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
