<?php

namespace Tests\Feature;

use App\Models\ParishSetting;
use App\Models\ServiceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ServiceRecordCertificateTest extends TestCase
{
    use LazilyRefreshDatabase;

    public static function certificates(): array
    {
        return [
            'baptism' => ['baptism', 'Certificate of Baptism', ['child_full_name' => 'Maria Santos', 'child_sex' => 'female', 'child_date_of_birth' => '2019-12-01'], ['Maria Santos', 'Female', 'December 1, 2019']],
            'marriage' => ['wedding', 'Certificate of Marriage', ['groom_full_name' => 'Juan Santos', 'bride_full_name' => 'Ana Reyes', 'groom_status' => 'single'], ['Juan Santos', 'Ana Reyes', 'Single']],
            'burial' => ['funeral', 'Certificate of Burial', ['deceased_full_name' => 'Pedro Santos', 'date_of_burial' => '2020-01-03', 'place_of_death' => 'Legazpi', 'family_relationship' => 'Married to Rosa', 'sacraments_received' => 'Anointing of the sick'], ['Pedro Santos', 'January 3, 2020', 'Legazpi', 'Married to Rosa', 'Anointing of the sick']],
            'confirmation' => ['confirmation', 'Certificate of Confirmation', ['recipient_full_name' => 'Jose Reyes'], ['Jose Reyes', 'January 1, 2020']],
        ];
    }

    #[DataProvider('certificates')]
    public function test_certificate_uses_saved_service_details(string $service, string $title, array $details, array $expected): void
    {
        $this->withoutVite();
        $this->verified();
        $record = ServiceRecord::factory()->create(['service_type' => $service, 'details' => $details, 'officiant' => 'Fr. Test', 'register_book' => 'Book 2', 'register_page' => '41', 'register_entry' => '117']);
        $before = $record->fresh()->getRawOriginal();

        $this->get(route('admin.records.certificate', $record))
            ->assertSee($title)->assertSee($expected)->assertSee(['Book 2', 'Fr. Test', 'Print / Save PDF', 'Parish seal']);

        $this->assertSame($before, $record->fresh()->getRawOriginal());
        $this->assertDatabaseMissing('audit_logs', ['entity_type' => ServiceRecord::class, 'entity_id' => $record->id]);
        $this->get(route('admin.records.show', $record))->assertSee('Print Certificate');
        $this->get(route('admin.records.all'))->assertSee(route('admin.records.certificate', $record));
        $this->get('/admin/records/services/'.$service.'/book?book=Book%202')->assertSee(route('admin.records.certificate', $record));
    }

    public function test_certificate_requires_login(): void
    {
        $record = ServiceRecord::factory()->create();

        $this->get(route('admin.records.certificate', $record))->assertRedirect(route('login'));
    }

    public function test_operational_certificate_uses_preserved_participants_when_details_are_missing(): void
    {
        $this->withoutVite();
        $this->verified();
        $record = ServiceRecord::factory()->create([
            'source' => 'operational',
            'participants' => [['role' => 'child', 'full_name' => 'Preserved Child', 'date_of_birth' => '2019-11-05']],
        ]);

        $this->get(route('admin.records.certificate', $record))->assertSee(['Preserved Child', 'November 5, 2019']);
    }

    public function test_certificate_requires_record_view_permission(): void
    {
        $this->verified();
        ParishSetting::query()->where('key', 'office')->update(['value' => ['permissions' => ['admin' => []]]]);
        $record = ServiceRecord::factory()->create();

        $this->get(route('admin.records.certificate', $record))->assertForbidden();
    }

    public function test_other_sources_and_unsupported_services_are_not_printable(): void
    {
        $this->withoutVite();
        $this->verified();
        $hidden = ServiceRecord::factory()->create(['source' => 'synthetic']);
        $unsupported = ServiceRecord::factory()->create(['service_type' => 'others']);

        $this->get(route('admin.records.certificate', $hidden))->assertNotFound();
        $this->get(route('admin.records.certificate', $unsupported))->assertNotFound();
        $this->get(route('admin.records.show', $unsupported))->assertDontSee('Print Certificate');
    }

    public function test_missing_burial_details_are_blank_and_not_inferred_from_funeral_date_or_informant(): void
    {
        $this->withoutVite();
        $this->verified();
        $record = ServiceRecord::factory()->create(['service_type' => 'funeral', 'details' => ['contact_full_name' => 'Informant Only', 'anointing_of_sick' => 'yes']]);

        $this->get(route('admin.records.certificate', $record))
            ->assertSee('Date of burial')->assertDontSee('January 1, 2020')->assertDontSee('Informant Only')
            ->assertViewHas('sections', fn (array $sections): bool => $sections['Burial record']['Date of burial'] === '' && $sections['Burial record']['Sacraments received'] === '');
    }

    public function test_certificate_escapes_saved_content_and_marks_archived_records(): void
    {
        $this->withoutVite();
        $this->verified();
        $unsafe = '<script>alert("x")</script>';
        $record = ServiceRecord::factory()->create(['details' => ['child_full_name' => $unsafe], 'church' => $unsafe, 'officiant' => $unsafe, 'archived_at' => now()]);

        $this->get(route('admin.records.certificate', $record))->assertSee($unsafe)->assertDontSee($unsafe, false)->assertSee('Archived record');
    }

    public function test_certificate_only_fields_can_be_saved_with_audited_record_corrections(): void
    {
        $this->verified();
        $record = ServiceRecord::factory()->create(['service_type' => 'funeral']);
        $fields = ['family_relationship' => 'Child of Juan', 'place_of_death' => 'Legazpi', 'sacraments_received' => 'Holy Communion'];

        $this->put(route('admin.records.update', $record), [
            ...$record->only(['record_number', 'service_type', 'participant_names', 'church']),
            'service_date' => '2020-01-01', 'version' => 1, 'reason' => 'Verified against burial register', 'register_details' => $fields,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Holy Communion', $record->fresh()->details['sacraments_received']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'record.corrected', 'entity_id' => $record->id]);
    }

    private function verified(): static
    {
        $user = User::factory()->admin()->withTwoFactor()->create();

        return $this->actingAs($user)->withSession(['admin.mfa_user_id' => $user->id, 'admin.mfa_fingerprint' => $user->adminSessionFingerprint()]);
    }
}
