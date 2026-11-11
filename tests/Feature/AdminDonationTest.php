<?php

namespace Tests\Feature;

use App\Models\Donation;
use App\Models\DonationAdjustment;
use App\Models\ParishSetting;
use App\Models\User;
use App\Support\OfficeAccess;
use App\Support\OfficeSettings;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminDonationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo('2026-11-09 12:00:00');
    }

    public function test_donations_require_authentication_mfa_and_explicit_permissions(): void
    {
        $donation = Donation::factory()->create();
        $adjustment = DonationAdjustment::factory()->for($donation)->create();
        $this->get('/admin/donations')->assertRedirect('/login');
        $this->actingAs(User::factory()->admin()->create())->get('/admin/donations')->assertRedirect('/admin/security');
        $this->verified(User::factory()->create())->get('/admin/donations')->assertForbidden();
        $admin = User::factory()->admin()->withTwoFactor()->create();
        $this->permissions([]);
        $this->verified($admin);
        foreach (['/admin/donations', '/admin/donations/create', '/admin/donations/'.$donation->id, '/admin/donations/'.$donation->id.'/edit', '/admin/donations/'.$donation->id.'/receipt', '/admin/donations?export=records'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post('/admin/donations', $this->payload())->assertForbidden();
        $this->put(route('admin.donations.update', $donation), $this->payload())->assertForbidden();
        $this->post(route('admin.donations.void', $donation), [])->assertForbidden();
        $this->patch(route('admin.donation-adjustments.update', $adjustment), [])->assertForbidden();
        $this->assertDatabaseCount('donations', 1);
    }

    public function test_view_only_permission_cannot_record_correct_void_or_export(): void
    {
        $this->permissions(['donations.view']);
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $donation = Donation::factory()->create();
        $this->get('/admin/donations')->assertSee('Donations')->assertDontSee('Record donation');
        $this->get(route('admin.donations.show', $donation))->assertSee($donation->receipt_number)->assertDontSee('Request correction');
        $this->get(route('admin.donations.receipt', $donation))->assertSee('Donation receipt');
        $this->post('/admin/donations', $this->payload())->assertForbidden();
        $this->put(route('admin.donations.update', $donation), $this->payload())->assertForbidden();
        $this->post(route('admin.donations.void', $donation), [])->assertForbidden();
        $this->get('/admin/donations?export=records')->assertForbidden();
    }

    public function test_donation_permissions_follow_configured_admin_access_and_super_admin_boundary(): void
    {
        $this->permissions(['donations.view', 'donations.manage', 'payments.approve']);
        $admin = User::factory()->admin()->create();
        $this->assertTrue(OfficeAccess::allows($admin, 'donations.view'));
        $this->assertTrue(OfficeAccess::allows($admin, 'donations.manage'));
        $this->assertFalse(OfficeAccess::allows($admin, 'payments.approve'));
        $this->assertFalse(OfficeAccess::allows(User::factory()->create(), 'donations.view'));
        $this->assertTrue(OfficeAccess::allows(User::factory()->superAdmin()->create(), 'payments.approve'));
    }

    public function test_recording_is_audited_idempotent_and_ignores_privileged_fields(): void
    {
        $admin = User::factory()->admin()->withTwoFactor()->create();
        $this->verified($admin);
        $payload = $this->payload();
        $this->get('/admin/donations/create')->assertSee('Donor information');
        $this->post('/admin/donations', [...$payload, 'amount_cents' => 1, 'source' => 'synthetic', 'status' => 'voided', 'recorded_by' => 999, 'version' => 99])->assertSessionHasNoErrors();
        $donation = Donation::query()->sole();
        $this->assertSame(12345, $donation->amount_cents);
        $this->assertSame($admin->id, $donation->recorded_by);
        $this->assertSame('operational', $donation->source);
        $this->assertSame('recorded', $donation->status);
        $this->assertSame(1, $donation->version);
        $this->post('/admin/donations', $payload)->assertRedirect(route('admin.donations.show', $donation));
        $this->post('/admin/donations', [...$payload, 'amount' => '99.00'])->assertConflict();
        $this->assertDatabaseCount('donations', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'donation.recorded', 'actor_id' => $admin->id]);
        $this->get(route('admin.donations.receipt', $donation))->assertSee('123.45')->assertSee($donation->receipt_number)->assertHeader('Cache-Control', 'no-store, private');
    }

    #[DataProvider('invalidDonations')]
    public function test_rejects_invalid_donation_details(string $field, mixed $value): void
    {
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $this->post('/admin/donations', [...$this->payload(), $field => $value])->assertSessionHasErrors($field);
        $this->assertDatabaseEmpty('donations');
    }

    public static function invalidDonations(): array
    {
        return [
            'zero' => ['amount', '0'], 'negative' => ['amount', '-1'], 'precision' => ['amount', '1.001'],
            'exponent' => ['amount', '1e3'], 'maximum' => ['amount', '10000000'], 'missing name' => ['donor_name', ''],
            'email' => ['donor_email', 'invalid'], 'future date' => ['donated_on', '2026-11-10'],
            'invalid date' => ['donated_on', '2026-02-30'], 'category' => ['category', 'invalid'],
            'method' => ['method', 'xendit_sandbox'], 'submission key' => ['idempotency_key', 'invalid'],
        ];
    }

    public function test_non_cash_requires_reference_and_accepts_exact_small_amount(): void
    {
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $payload = [...$this->payload(), 'method' => 'gcash', 'amount' => '0.01'];
        $this->post('/admin/donations', $payload)->assertSessionHasErrors('reference');
        $this->post('/admin/donations', [...$payload, 'reference' => 'GCASH-123'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('donations', ['amount_cents' => 1, 'method' => 'gcash', 'reference' => 'GCASH-123']);
    }

    public function test_correction_requires_independent_review_and_preserves_receipt_and_original_details(): void
    {
        $maker = User::factory()->superAdmin()->withTwoFactor()->create();
        $donation = Donation::factory()->create();
        $payload = [...$this->payload(), 'amount' => '75.25', 'version' => 1, 'reason' => 'Original amount mistyped'];
        $this->verified($maker);
        $this->get(route('admin.donations.edit', $donation))->assertSee('Request donation correction');
        $this->put(route('admin.donations.update', $donation), $payload)->assertSessionHasNoErrors();
        $this->put(route('admin.donations.update', $donation), $payload)->assertSessionHasNoErrors();
        $adjustment = DonationAdjustment::query()->sole();
        $this->assertSame(10000, $donation->refresh()->amount_cents);
        $this->assertSame(10000, $adjustment->before['amount_cents']);
        $this->assertSame(7525, $adjustment->payload['amount_cents']);
        $this->get(route('admin.donations.show', $donation))->assertSee('Another Super Admin must review your request.');
        $this->patch(route('admin.donation-adjustments.update', $adjustment), ['decision' => 'approved', 'review_reason' => 'Self review'])->assertForbidden();
        $this->verified(User::factory()->superAdmin()->withTwoFactor()->create());
        $this->get(route('admin.donations.show', $donation))->assertSee('Approve correction')->assertSee('Original amount mistyped');
        $this->patch(route('admin.donation-adjustments.update', $adjustment), ['decision' => 'approved', 'review_reason' => 'Checked donor slip', 'payload' => ['amount_cents' => 1]])->assertSessionHasNoErrors();
        $receipt = $donation->receipt_number;
        $this->assertSame(7525, $donation->refresh()->amount_cents);
        $this->assertSame(2, $donation->version);
        $this->assertSame($receipt, $donation->receipt_number);
        $this->assertSame('approved', $adjustment->refresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'donation.corrected', 'entity_id' => $donation->id]);
        $this->get(route('admin.donations.receipt', $donation))->assertSee('75.25')->assertSee('Version 2')->assertSee('replaces earlier copies');
        $this->patch(route('admin.donation-adjustments.update', $adjustment), ['decision' => 'approved', 'review_reason' => 'Retry'])->assertConflict();
    }

    public function test_patch_corrections_require_version_and_reason_and_reject_stale_or_competing_requests(): void
    {
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $donation = Donation::factory()->create();
        $this->patch(route('admin.donations.update', $donation), $this->payload())->assertSessionHasErrors(['version', 'reason']);
        $this->put(route('admin.donations.update', $donation), [...$this->payload(), 'version' => 2, 'reason' => 'Stale'])->assertConflict();
        $payload = [...$this->payload(), 'version' => 1, 'reason' => 'Correct donor'];
        $this->put(route('admin.donations.update', $donation), $payload)->assertSessionHasNoErrors();
        $this->put(route('admin.donations.update', $donation), [...$payload, 'amount' => '50'])->assertConflict();
        $this->post(route('admin.donations.void', $donation), ['idempotency_key' => (string) Str::uuid(), 'version' => 1, 'reason' => 'Competing request'])->assertConflict();
        $this->assertDatabaseCount('donation_adjustments', 1);
    }

    public function test_void_keeps_history_excludes_amounts_and_marks_receipt(): void
    {
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $donation = Donation::factory()->create();
        $payload = ['idempotency_key' => (string) Str::uuid(), 'version' => 1, 'reason' => 'Duplicate receipt'];
        $this->post(route('admin.donations.void', $donation), $payload)->assertSessionHasNoErrors();
        $this->post(route('admin.donations.void', $donation), $payload)->assertSessionHasNoErrors();
        $adjustment = DonationAdjustment::query()->sole();
        $this->patch(route('admin.donation-adjustments.update', $adjustment), ['decision' => 'approved', 'review_reason' => 'Bypass'])->assertForbidden();
        $this->assertSame('recorded', $donation->refresh()->status);
        $this->verified(User::factory()->superAdmin()->withTwoFactor()->create());
        $this->patch(route('admin.donation-adjustments.update', $adjustment), ['decision' => 'approved', 'review_reason' => 'Verified duplicate'])->assertSessionHasNoErrors();
        $this->assertSame('voided', $donation->refresh()->status);
        $this->assertDatabaseCount('donations', 1);
        $this->get('/admin/donations')->assertViewHas('totals', fn ($totals): bool => (int) $totals->received_cents === 0 && (int) $totals->voided_count === 1);
        $this->get(route('admin.donations.receipt', $donation))->assertSee('CANCELED')->assertSee('This receipt is no longer valid');
        $this->get(route('admin.donations.edit', $donation))->assertConflict();
        $this->assertDatabaseHas('audit_logs', ['action' => 'donation.voided']);
    }

    public function test_rejection_and_stale_approval_preserve_donation(): void
    {
        $donation = Donation::factory()->create(['version' => 2]);
        $adjustment = DonationAdjustment::factory()->for($donation)->create();
        $this->verified(User::factory()->superAdmin()->withTwoFactor()->create());
        $url = route('admin.donation-adjustments.update', $adjustment);
        $this->patch($url, ['decision' => 'approved', 'review_reason' => 'Stale'])->assertConflict();
        $this->patch($url, ['decision' => 'rejected', 'review_reason' => 'Donation already changed'])->assertSessionHasNoErrors();
        $this->assertSame('rejected', $adjustment->refresh()->status);
        $this->assertSame('recorded', $donation->refresh()->status);
        $this->assertSame(10000, $donation->amount_cents);
        $this->get(route('admin.donations.show', $donation))->assertSee('Donation already changed');
    }

    public function test_filters_summaries_and_all_report_exports_use_the_same_records(): void
    {
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $first = Donation::factory()->create(['donor_name' => 'Maria Santos', 'donated_on' => '2026-10-01', 'amount_cents' => 12345, 'category' => 'charity', 'method' => 'gcash']);
        Donation::factory()->create(['donor_name' => 'Maria Reyes', 'donated_on' => '2026-10-31', 'amount_cents' => 10000, 'category' => 'charity', 'method' => 'gcash']);
        Donation::factory()->create(['donor_name' => 'Maria Voided', 'donated_on' => '2026-10-15', 'amount_cents' => 99999, 'category' => 'charity', 'method' => 'gcash', 'status' => 'voided']);
        Donation::factory()->create(['donor_name' => 'Outside dates', 'donated_on' => '2026-11-01', 'category' => 'charity', 'method' => 'gcash']);
        Donation::factory()->create(['donor_name' => 'Other category', 'donated_on' => '2026-10-15']);
        Donation::factory()->create(['donor_name' => 'Maria Hidden', 'source' => 'synthetic', 'donated_on' => '2026-10-10', 'category' => 'charity', 'method' => 'gcash']);
        $filters = ['q' => 'maria', 'from' => '2026-10-01', 'to' => '2026-10-31', 'category' => 'charity', 'method' => 'gcash'];
        $this->get(route('admin.donations.index', $filters))->assertSee($first->receipt_number)->assertDontSee('Maria Hidden')
            ->assertViewHas('totals', fn ($totals): bool => (int) $totals->received_cents === 22345 && (int) $totals->received_count === 2 && (int) $totals->voided_count === 1)
            ->assertViewHas('monthly', fn ($rows): bool => $rows->count() === 1 && $rows->first()->month === '2026-10' && (int) $rows->first()->total_cents === 22345);
        $csv = $this->get(route('admin.donations.index', [...$filters, 'export' => 'records']))->assertDownload('donations-records.csv')->streamedContent();
        $this->assertStringContainsString('Maria Santos', $csv);
        $this->assertStringContainsString('Maria Voided', $csv);
        $this->assertStringNotContainsString('Outside dates', $csv);
        $this->assertStringNotContainsString('Maria Hidden', $csv);
        foreach (['category', 'method', 'monthly'] as $type) {
            $csv = $this->get(route('admin.donations.index', [...$filters, 'export' => $type]))->streamedContent();
            $this->assertStringContainsString('223.45', $csv);
            $this->assertStringNotContainsString('999.99', $csv);
        }
        $this->get(route('admin.donations.index', [...$filters, 'status' => 'voided']))->assertViewHas('donations', fn ($rows): bool => $rows->total() === 1);
        $this->get('/admin/donations?from=2026-11-09&to=2026-10-01')->assertSessionHasErrors('to');
        $this->assertDatabaseHas('audit_logs', ['action' => 'donations.exported']);
    }

    public function test_search_is_literal_case_insensitive_and_matches_receipts_and_contacts(): void
    {
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $donation = Donation::factory()->create(['donor_name' => '100% Kind', 'donor_phone' => '09123456789', 'donor_email' => 'helper@example.com']);
        Donation::factory()->create(['donor_name' => 'Other donor', 'receipt_number' => 'DON-OTHER']);
        foreach (['0', '100%', 'KIND', '091234', 'HELPER@EXAMPLE', $donation->receipt_number] as $query) {
            $this->get(route('admin.donations.index', ['q' => $query]))->assertViewHas('donations', fn ($rows): bool => $rows->total() === 1 && $rows->first()->id === $donation->id);
        }
    }

    public function test_pagination_preserves_filters_and_totals_cover_all_pages(): void
    {
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        Donation::factory()->count(26)->create(['category' => 'charity']);
        $this->get('/admin/donations?category=charity')->assertViewHas('totals', fn ($totals): bool => (int) $totals->received_cents === 260000)
            ->assertSee('category=charity&amp;page=2', false);
        $this->get('/admin/donations?category=charity&page=2')->assertViewHas('donations', fn ($rows): bool => $rows->count() === 1 && $rows->total() === 26);
    }

    public function test_pending_filter_and_cross_dataset_routes_protect_private_records(): void
    {
        $this->verified(User::factory()->superAdmin()->withTwoFactor()->create());
        $pending = DonationAdjustment::factory()->create();
        Donation::factory()->create();
        $hidden = Donation::factory()->create(['source' => 'synthetic']);
        $hiddenAdjustment = DonationAdjustment::factory()->for($hidden)->create();
        $this->get('/admin/donations?review=pending')->assertViewHas('donations', fn ($rows): bool => $rows->total() === 1 && $rows->first()->id === $pending->donation_id);
        foreach (['show', 'edit', 'receipt'] as $action) {
            $this->get(route('admin.donations.'.$action, $hidden))->assertNotFound();
        }
        $this->put(route('admin.donations.update', $hidden), [...$this->payload(), 'version' => 1, 'reason' => 'Cross dataset'])->assertNotFound();
        $this->post(route('admin.donations.void', $hidden), [])->assertNotFound();
        $this->patch(route('admin.donation-adjustments.update', $hiddenAdjustment), ['decision' => 'approved', 'review_reason' => 'Cross dataset'])->assertNotFound();
    }

    public function test_receipts_escape_donor_content_exclude_private_notes_and_csv_neutralizes_formulas(): void
    {
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $donation = Donation::factory()->create(['donor_name' => '<script>alert(1)</script>', 'reference' => '<img src=x onerror=alert(1)>', 'notes' => 'Private office note']);
        $this->get(route('admin.donations.receipt', $donation))->assertSee($donation->donor_name)->assertDontSee($donation->donor_name, false)
            ->assertSee($donation->reference)->assertDontSee($donation->reference, false)->assertDontSee('Private office note');
        $this->get(route('admin.donations.show', $donation))->assertSee('Private office note')->assertDontSee($donation->donor_name, false);
        Donation::factory()->create(['donor_name' => '=1+1']);
        $csv = $this->get('/admin/donations?export=records')->streamedContent();
        $this->assertStringContainsString("'=1+1", $csv);
        $this->assertDatabaseHas('audit_logs', ['action' => 'donation.receipt_viewed', 'entity_id' => $donation->id]);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return ['idempotency_key' => (string) Str::uuid(), 'donor_name' => 'Ana Santos', 'amount' => '123.45', 'donated_on' => '2026-11-09', 'category' => 'general', 'method' => 'cash'];
    }

    private function verified(User $user): static
    {
        return $this->actingAs($user)->withSession(['admin.mfa_user_id' => $user->id, 'admin.mfa_fingerprint' => $user->adminSessionFingerprint()]);
    }

    /** @param list<string> $permissions */
    private function permissions(array $permissions): void
    {
        ParishSetting::query()->updateOrCreate(['key' => 'office'], ['value' => ['permissions' => ['admin' => $permissions]]]);
        OfficeSettings::forgetMemoizedValues();
    }
}
