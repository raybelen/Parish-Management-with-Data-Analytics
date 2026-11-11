<?php

namespace Tests\Feature;

use App\Models\Donation;
use App\Models\DonationAdjustment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MassCollectionTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo('2026-11-09 12:00:00');
    }

    public function test_anonymous_option_is_removed_and_named_individual_donations_are_required(): void
    {
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $this->get('/admin/donations/create')->assertDontSee('Anonymous donor')->assertSee('Donor name / organization');
        $this->get('/admin/donations')->assertDontSee('Donor privacy');
        $this->post('/admin/donations', [...$this->individual(), 'is_anonymous' => true])->assertSessionHasErrors('is_anonymous');
        $payload = $this->individual();
        unset($payload['donor_name']);
        $this->post('/admin/donations', $payload)->assertSessionHasErrors('donor_name');
        $this->assertDatabaseEmpty('donations');
        $this->post('/admin/donations', $this->individual())->assertSessionHasNoErrors();
        $this->assertFalse(Donation::query()->sole()->is_anonymous);
    }

    public function test_existing_anonymous_records_are_preserved_and_can_be_corrected_to_named_donations(): void
    {
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $donation = Donation::factory()->create(['donor_name' => 'Anonymous', 'is_anonymous' => true]);
        $this->get(route('admin.donations.receipt', $donation))->assertSee('Anonymous');
        $this->get(route('admin.donations.edit', $donation))->assertDontSee('Anonymous donor');
        $this->put(route('admin.donations.update', $donation), [...$this->individual(), 'version' => 1, 'reason' => 'Donor identified'])->assertSessionHasNoErrors();
        $this->assertTrue($donation->refresh()->is_anonymous);
        $this->approve(DonationAdjustment::query()->sole());
        $this->assertFalse($donation->refresh()->is_anonymous);
        $this->assertSame('Donor One', $donation->donor_name);
    }

    public function test_old_pending_anonymization_cannot_be_approved(): void
    {
        $donation = Donation::factory()->create();
        $adjustment = DonationAdjustment::factory()->for($donation)->create([
            'kind' => 'correction', 'payload' => [...$donation->details(), 'is_anonymous' => true, 'donor_name' => 'Anonymous'],
        ]);
        $this->verified(User::factory()->superAdmin()->withTwoFactor()->create());
        $this->patch(route('admin.donation-adjustments.update', $adjustment), ['decision' => 'approved', 'review_reason' => 'Review'])->assertSessionHasErrors('decision');
        $this->assertFalse($donation->refresh()->is_anonymous);
        $this->assertSame('pending', $adjustment->refresh()->status);
    }

    public function test_mass_collection_records_collective_donor_and_printable_count_information(): void
    {
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $this->get('/admin/donations/create?kind=mass_collection')->assertSee('Record Mass collection')->assertSee('Counted by')->assertDontSee('Donor name / organization');
        $payload = [...$this->mass(), 'donor_name' => 'Discard name', 'donor_email' => 'discard@example.com'];
        $this->post('/admin/donations', $payload)->assertSessionHasNoErrors();
        $donation = Donation::query()->sole();
        $this->assertTrue($donation->isMassCollection());
        $this->assertFalse($donation->is_anonymous);
        $this->assertSame('Mass congregation', $donation->donor_name);
        $this->assertNull($donation->donor_email);
        $this->assertSame('mass_offering', $donation->category);
        $this->assertStringStartsWith('MASS-', $donation->receipt_number);
        $this->assertNotNull($donation->collection_key);
        $this->get(route('admin.donations.show', $donation))->assertSee('Without donor receipts:')->assertSee('10,000.00');
        $this->get(route('admin.donations.receipt', $donation))->assertSee('Mass collection receipt')
            ->assertSee('Mass congregation')->assertSee('Counter One')->assertSee('Verifier Two')->assertSee('07:00')->assertDontSee('Discard name');
        $this->post('/admin/donations', $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('donations', 1);
    }

    #[DataProvider('invalidCollections')]
    public function test_mass_collection_validation_rejects_invalid_details(string $field, mixed $value): void
    {
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $this->post('/admin/donations', [...$this->mass(), $field => $value])->assertSessionHasErrors($field);
        $this->assertDatabaseEmpty('donations');
    }

    public static function invalidCollections(): array
    {
        return [
            'church missing' => ['church', ''], 'unknown church' => ['church', 'Unknown church'],
            'time missing' => ['mass_time', ''], 'invalid time' => ['mass_time', '25:99'],
            'future Mass' => ['mass_time', '23:00'], 'unknown type' => ['collection_type', 'unknown'],
            'missing counter' => ['counted_by', ''], 'same verifier' => ['verified_by', 'COUNTER ONE'],
            'missing verifier' => ['verified_by', ''],
        ];
    }

    public function test_special_collection_requires_a_purpose_and_different_collections_or_methods_are_allowed(): void
    {
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $this->post('/admin/donations', [...$this->mass(), 'collection_type' => 'special'])->assertSessionHasErrors('collection_purpose');
        $this->post('/admin/donations', $this->mass())->assertSessionHasNoErrors();
        $this->post('/admin/donations', [...$this->mass(), 'collection_type' => 'special', 'collection_purpose' => 'Disaster relief'])->assertSessionHasNoErrors();
        $this->post('/admin/donations', [...$this->mass(), 'method' => 'gcash', 'reference' => 'GCASH-BATCH-123'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('donations', 3);
        $this->get('/admin/donations?collection_type=special')->assertViewHas('donations', fn ($rows): bool => $rows->total() === 1);
    }

    public function test_duplicate_mass_collection_with_a_new_submission_key_is_rejected(): void
    {
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $this->post('/admin/donations', $this->mass())->assertSessionHasNoErrors();
        $this->post('/admin/donations', $this->mass())->assertSessionHasErrors('mass_time');
        $this->assertDatabaseCount('donations', 1);
    }

    public function test_linked_individual_receipts_are_included_once_in_all_totals_and_exports(): void
    {
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $mass = $this->recordMass();
        $this->post('/admin/donations', [...$this->individual(), 'amount' => '2000.00', 'mass_collection_id' => $mass->id])->assertSessionHasNoErrors();
        $receipt = Donation::query()->where('kind', 'individual')->sole();
        $this->post('/admin/donations', [...$this->individual(), 'amount' => '500.00'])->assertSessionHasNoErrors();
        $this->get('/admin/donations')->assertViewHas('totals', fn ($totals): bool => (int) $totals->received_cents === 1050000 && (int) $totals->included_cents === 200000 && (int) $totals->mass_count === 1)
            ->assertViewHas('monthly', fn ($rows): bool => (int) $rows->first()->total_cents === 1050000);
        $this->get(route('admin.donations.show', $mass))->assertSee('8,000.00')->assertSee($receipt->receipt_number);
        $this->get(route('admin.donations.receipt', $receipt))->assertSee('not an additional collection')->assertSee($mass->receipt_number);
        foreach (['category', 'method', 'monthly'] as $type) {
            $csv = $this->get('/admin/donations?category=mass_offering&export='.$type)->streamedContent();
            $this->assertStringContainsString('10000.00', $csv);
            $this->assertStringNotContainsString('12000.00', $csv);
        }
        $csv = $this->get('/admin/donations?export=records')->streamedContent();
        $this->assertStringContainsString('counted_received_php', $csv);
        $this->assertStringContainsString($mass->receipt_number.',0.00', $csv);
        $this->get('/admin/donations?kind=individual')->assertViewHas('totals', fn ($totals): bool => (int) $totals->received_cents === 50000 && (int) $totals->included_cents === 200000);
    }

    public function test_included_donor_is_named_and_collection_is_bound_in_the_form(): void
    {
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $mass = $this->recordMass();
        $this->get(route('admin.donations.create', ['mass_collection_id' => $mass->id]))->assertSee('already counted')->assertSee($mass->receipt_number)->assertSee('Donor name / organization')->assertDontSee('Anonymous donor');
        $this->post('/admin/donations', [...$this->individual(), 'mass_collection_id' => $mass->id])->assertSessionHasNoErrors();
        $receipt = Donation::query()->where('kind', 'individual')->sole();
        $this->assertSame('Donor One', $receipt->donor_name);
        $this->assertSame($mass->id, $receipt->mass_collection_id);
    }

    public function test_individual_receipts_cannot_exceed_the_collection_or_use_a_different_payment_date_or_method(): void
    {
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $mass = $this->recordMass();
        $this->post('/admin/donations', [...$this->individual(), 'mass_collection_id' => $mass->id, 'amount' => '10000.01'])->assertSessionHasErrors('amount');
        $this->post('/admin/donations', [...$this->individual(), 'mass_collection_id' => $mass->id, 'donated_on' => '2026-11-08'])->assertSessionHasErrors('mass_collection_id');
        $this->post('/admin/donations', [...$this->individual(), 'mass_collection_id' => $mass->id, 'method' => 'gcash', 'reference' => 'REF'])->assertSessionHasErrors('mass_collection_id');
        $this->post('/admin/donations', [...$this->individual(), 'mass_collection_id' => $mass->id, 'amount' => '10000'])->assertSessionHasNoErrors();
        $this->post('/admin/donations', [...$this->individual(), 'mass_collection_id' => $mass->id, 'amount' => '0.01'])->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('donations', 2);
    }

    public function test_receipt_link_cannot_target_other_datasets_non_collections_or_voided_collections(): void
    {
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $ordinary = Donation::factory()->create();
        $hidden = Donation::factory()->create(['source' => 'synthetic', 'kind' => 'mass_collection']);
        foreach ([$ordinary, $hidden] as $target) {
            $this->get(route('admin.donations.create', ['mass_collection_id' => $target->id]))->assertNotFound();
            $this->post('/admin/donations', [...$this->individual(), 'mass_collection_id' => $target->id])->assertNotFound();
        }
        $mass = $this->recordMass();
        $mass->update(['status' => 'voided', 'collection_key' => null]);
        $this->post('/admin/donations', [...$this->individual(), 'mass_collection_id' => $mass->id])->assertSessionHasErrors('mass_collection_id');
    }

    public function test_mass_cannot_be_reduced_below_receipts_changed_to_another_mass_or_voided_with_active_receipts(): void
    {
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $mass = $this->recordMass();
        $this->post('/admin/donations', [...$this->individual(), 'mass_collection_id' => $mass->id, 'amount' => '2000'])->assertSessionHasNoErrors();
        $correction = [...$this->mass(), 'version' => 1, 'reason' => 'Correction'];
        $this->put(route('admin.donations.update', $mass), [...$correction, 'amount' => '1999'])->assertSessionHasErrors('amount');
        $this->put(route('admin.donations.update', $mass), [...$correction, 'mass_time' => '08:00'])->assertSessionHasErrors('mass_time');
        $this->post(route('admin.donations.void', $mass), ['idempotency_key' => (string) Str::uuid(), 'version' => 1, 'reason' => 'Duplicate'])->assertSessionHasErrors('reason');
        $this->assertDatabaseEmpty('donation_adjustments');
    }

    public function test_mass_amount_is_revalidated_at_approval_when_new_receipts_were_added(): void
    {
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $mass = $this->recordMass();
        $this->put(route('admin.donations.update', $mass), [...$this->mass(), 'amount' => '1000', 'version' => 1, 'reason' => 'Recount'])->assertSessionHasNoErrors();
        $this->post('/admin/donations', [...$this->individual(), 'mass_collection_id' => $mass->id, 'amount' => '2000'])->assertSessionHasNoErrors();
        $adjustment = DonationAdjustment::query()->sole();
        $this->verified(User::factory()->superAdmin()->withTwoFactor()->create());
        $this->patch(route('admin.donation-adjustments.update', $adjustment), ['decision' => 'approved', 'review_reason' => 'Checked'])->assertSessionHasErrors('amount');
        $this->assertSame('pending', $adjustment->refresh()->status);
        $this->assertSame(1000000, $mass->refresh()->amount_cents);
    }

    public function test_individual_correction_is_revalidated_against_other_receipts_at_approval(): void
    {
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $mass = $this->recordMass();
        $this->post('/admin/donations', [...$this->individual(), 'mass_collection_id' => $mass->id, 'amount' => '2000'])->assertSessionHasNoErrors();
        $receipt = Donation::query()->where('kind', 'individual')->sole();
        $this->put(route('admin.donations.update', $receipt), [...$this->individual(), 'amount' => '7000', 'version' => 1, 'reason' => 'Correct amount'])->assertSessionHasNoErrors();
        $this->post('/admin/donations', [...$this->individual(), 'mass_collection_id' => $mass->id, 'amount' => '4000'])->assertSessionHasNoErrors();
        $adjustment = DonationAdjustment::query()->sole();
        $this->verified(User::factory()->superAdmin()->withTwoFactor()->create());
        $this->patch(route('admin.donation-adjustments.update', $adjustment), ['decision' => 'approved', 'review_reason' => 'Checked'])->assertSessionHasErrors('amount');
        $this->assertSame(200000, $receipt->refresh()->amount_cents);
    }

    public function test_voiding_an_individual_receipt_releases_itemization_without_reducing_collection_total(): void
    {
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $mass = $this->recordMass();
        $this->post('/admin/donations', [...$this->individual(), 'mass_collection_id' => $mass->id])->assertSessionHasNoErrors();
        $receipt = Donation::query()->where('kind', 'individual')->sole();
        $this->post(route('admin.donations.void', $receipt), ['idempotency_key' => (string) Str::uuid(), 'version' => 1, 'reason' => 'Duplicate individual receipt'])->assertSessionHasNoErrors();
        $this->approve(DonationAdjustment::query()->sole());
        $this->get('/admin/donations')->assertViewHas('totals', fn ($totals): bool => (int) $totals->received_cents === 1000000 && (int) $totals->included_cents === 0);
        $this->get(route('admin.donations.show', $mass))->assertSee('10,000.00');
    }

    public function test_void_approval_rechecks_children_and_valid_void_releases_duplicate_collection_key(): void
    {
        $maker = User::factory()->admin()->withTwoFactor()->create();
        $this->verified($maker);
        $mass = $this->recordMass();
        $this->post(route('admin.donations.void', $mass), ['idempotency_key' => (string) Str::uuid(), 'version' => 1, 'reason' => 'Wrong collection'])->assertSessionHasNoErrors();
        $adjustment = DonationAdjustment::query()->sole();
        $this->approve($adjustment);
        $this->assertNull($mass->refresh()->collection_key);
        $this->verified($maker);
        $replacement = $this->recordMass();
        $this->assertNotSame($mass->id, $replacement->id);
        $this->post(route('admin.donations.void', $replacement), ['idempotency_key' => (string) Str::uuid(), 'version' => 1, 'reason' => 'Wrong collection'])->assertSessionHasNoErrors();
        $this->post('/admin/donations', [...$this->individual(), 'mass_collection_id' => $replacement->id])->assertSessionHasNoErrors();
        $pending = DonationAdjustment::query()->where('status', 'pending')->sole();
        $this->verified(User::factory()->superAdmin()->withTwoFactor()->create());
        $this->patch(route('admin.donation-adjustments.update', $pending), ['decision' => 'approved', 'review_reason' => 'Checked'])->assertSessionHasErrors('reason');
        $this->assertSame('recorded', $replacement->refresh()->status);
    }

    public function test_church_and_mass_reports_filter_collections_and_exclude_voids_and_other_datasets(): void
    {
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $mass = $this->recordMass();
        $this->post('/admin/donations', [...$this->mass(), 'church' => config('parish.churches.1'), 'amount' => '500'])->assertSessionHasNoErrors();
        Donation::factory()->create(['kind' => 'mass_collection', 'source' => 'synthetic', 'church' => $mass->church, 'amount_cents' => 9900000]);
        Donation::factory()->create(['kind' => 'mass_collection', 'status' => 'voided', 'church' => $mass->church, 'amount_cents' => 8800000]);
        $filters = ['church' => $mass->church, 'from' => '2026-11-09', 'to' => '2026-11-09', 'collection_type' => 'regular'];
        $this->get(route('admin.donations.index', $filters))->assertViewHas('byChurch', fn ($rows): bool => $rows->count() === 1 && (int) $rows->first()->total_cents === 1000000)
            ->assertViewHas('masses', fn ($rows): bool => $rows->total() === 1);
        foreach (['church', 'masses'] as $export) {
            $csv = $this->get(route('admin.donations.index', [...$filters, 'export' => $export]))->assertDownload('donations-'.$export.'.csv')->streamedContent();
            $this->assertStringContainsString('10000.00', $csv);
            $this->assertStringNotContainsString('99000.00', $csv);
            $this->assertStringNotContainsString('88000.00', $csv);
        }
    }

    public function test_mass_correction_is_applied_only_after_review_and_rechecks_duplicate_mass_identity(): void
    {
        $maker = User::factory()->admin()->withTwoFactor()->create();
        $this->verified($maker);
        $mass = $this->recordMass();
        $this->put(route('admin.donations.update', $mass), [...$this->mass(), 'amount' => '9000', 'version' => 1, 'reason' => 'Correct count'])->assertSessionHasNoErrors();
        $this->assertSame(1000000, $mass->refresh()->amount_cents);
        $this->approve(DonationAdjustment::query()->sole());
        $this->assertSame(900000, $mass->refresh()->amount_cents);
        $this->assertNotNull($mass->collection_key);

        $this->verified($maker);
        $this->put(route('admin.donations.update', $mass), [...$this->mass(), 'mass_time' => '08:00', 'version' => 2, 'reason' => 'Correct Mass time'])->assertSessionHasNoErrors();
        $this->post('/admin/donations', [...$this->mass(), 'mass_time' => '08:00'])->assertSessionHasNoErrors();
        $pending = DonationAdjustment::query()->where('status', 'pending')->sole();
        $this->verified(User::factory()->superAdmin()->withTwoFactor()->create());
        $this->patch(route('admin.donation-adjustments.update', $pending), ['decision' => 'approved', 'review_reason' => 'Checked'])->assertSessionHasErrors('mass_time');
        $this->assertSame('07:00', $mass->refresh()->mass_time);
    }

    public function test_named_donation_cannot_request_anonymization(): void
    {
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $donation = Donation::factory()->create();
        $this->put(route('admin.donations.update', $donation), [...$this->individual(), 'is_anonymous' => true, 'version' => 1, 'reason' => 'Hide donor'])->assertSessionHasErrors('is_anonymous');
        $this->assertDatabaseEmpty('donation_adjustments');
    }

    public function test_corrections_cannot_change_record_kind_or_reassign_an_included_receipt(): void
    {
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $mass = $this->recordMass();
        $this->post('/admin/donations', [...$this->individual(), 'mass_collection_id' => $mass->id])->assertSessionHasNoErrors();
        $receipt = Donation::query()->where('kind', 'individual')->sole();
        $this->put(route('admin.donations.update', $receipt), [...$this->individual(), 'kind' => 'mass_collection', 'mass_collection_id' => null, 'version' => 1, 'reason' => 'Correct donor name'])->assertSessionHasNoErrors();
        $adjustment = DonationAdjustment::query()->sole();
        $this->assertSame('individual', $adjustment->payload['kind']);
        $this->assertSame($mass->id, $adjustment->payload['mass_collection_id']);
        $this->approve($adjustment);
        $this->assertSame($mass->id, $receipt->refresh()->mass_collection_id);
        $this->get('/admin/donations')->assertViewHas('totals', fn ($totals): bool => (int) $totals->received_cents === 1000000);
    }

    /** @return array<string, mixed> */
    private function mass(): array
    {
        return [
            'idempotency_key' => (string) Str::uuid(), 'kind' => 'mass_collection', 'amount' => '10000',
            'donated_on' => '2026-11-09', 'mass_time' => '07:00', 'church' => config('parish.churches.0'),
            'collection_type' => 'regular', 'counted_by' => 'Counter One', 'verified_by' => 'Verifier Two', 'method' => 'cash',
        ];
    }

    /** @return array<string, mixed> */
    private function individual(): array
    {
        return [
            'idempotency_key' => (string) Str::uuid(), 'donor_name' => 'Donor One', 'amount' => '100',
            'donated_on' => '2026-11-09', 'category' => 'general', 'method' => 'cash',
        ];
    }

    private function recordMass(): Donation
    {
        $payload = $this->mass();
        $this->post('/admin/donations', $payload)->assertSessionHasNoErrors();

        return Donation::query()->where('idempotency_key', $payload['idempotency_key'])->sole();
    }

    private function approve(DonationAdjustment $adjustment): void
    {
        $this->verified(User::factory()->superAdmin()->withTwoFactor()->create());
        $this->patch(route('admin.donation-adjustments.update', $adjustment), ['decision' => 'approved', 'review_reason' => 'Verified source records'])->assertSessionHasNoErrors();
    }

    private function verified(User $user): static
    {
        return $this->actingAs($user)->withSession(['admin.mfa_user_id' => $user->id, 'admin.mfa_fingerprint' => $user->adminSessionFingerprint()]);
    }
}
