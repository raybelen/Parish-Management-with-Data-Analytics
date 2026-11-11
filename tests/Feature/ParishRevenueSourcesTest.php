<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Donation;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ParishRevenueSourcesTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo('2026-11-10 12:00');
    }

    public function test_combined_revenue_and_monthly_export_count_each_source_once(): void
    {
        $this->verified();
        $appointment = Appointment::factory()->create();
        $payment = Payment::factory()->for($appointment)->create(['amount_cents' => 10000, 'paid_at' => '2026-10-01', 'method' => 'cash']);
        Payment::factory()->for($appointment)->create(['kind' => 'refund', 'original_payment_id' => $payment->id, 'amount_cents' => 2000, 'paid_at' => '2026-11-10']);
        Donation::factory()->create(['amount_cents' => 30000, 'donated_on' => '2026-10-31']);
        $mass = Donation::factory()->create(['kind' => 'mass_collection', 'amount_cents' => 50000, 'donated_on' => '2026-11-10']);
        Donation::factory()->create(['mass_collection_id' => $mass->id, 'amount_cents' => 10000, 'donated_on' => '2026-11-10']);
        Donation::factory()->create(['status' => 'voided', 'amount_cents' => 900000]);
        Donation::factory()->create(['source' => 'synthetic', 'amount_cents' => 800000]);
        Donation::factory()->create(['donated_on' => '2026-09-30', 'amount_cents' => 700000]);

        $expected = [
            'sources' => ['services' => 8000, 'donations' => 30000, 'masses' => 50000],
            'monthly' => [
                ['month' => '2026-10', 'services' => 10000, 'donations' => 30000, 'masses' => 0, 'total' => 40000],
                ['month' => '2026-11', 'services' => -2000, 'donations' => 0, 'masses' => 50000, 'total' => 48000],
            ], 'combined' => 88000,
        ];
        $this->get('/admin/revenue?from=2026-10-01&to=2026-11-10')->assertViewHas('overview', $expected)
            ->assertSee('Revenue by source')->assertSee('Monthly revenue trend')->assertSee('880.00');
        $csv = $this->get('/admin/revenue?from=2026-10-01&to=2026-11-10&export=revenue')
            ->assertDownload('parish-revenue-2026-10-01-2026-11-10.csv')->streamedContent();
        $this->assertStringContainsString('2026-10,100.00,300.00,0.00,400.00', $csv);
        $this->assertStringContainsString("2026-11,'-20.00,0.00,500.00,480.00", $csv);
        $this->assertDatabaseHas('audit_logs', ['action' => 'revenue.exported']);
    }

    public function test_method_filters_all_sources_and_service_filters_only_service_payments(): void
    {
        $this->verified();
        Payment::factory()->for(Appointment::factory()->create(['service_type' => 'baptism']))->create(['amount_cents' => 10000, 'method' => 'cash']);
        Payment::factory()->for(Appointment::factory()->create(['service_type' => 'wedding']))->create(['amount_cents' => 20000, 'method' => 'cash']);
        Payment::factory()->create(['amount_cents' => 30000, 'method' => 'xendit_sandbox']);
        Donation::factory()->create(['amount_cents' => 40000, 'method' => 'cash']);
        Donation::factory()->create(['kind' => 'mass_collection', 'amount_cents' => 50000, 'method' => 'gcash']);
        $this->get('/admin/revenue?service=baptism&method=cash')->assertViewHas('overview', fn (array $data): bool => $data['sources'] === ['services' => 10000, 'donations' => 40000, 'masses' => 0] && $data['combined'] === 50000);
        $this->get('/admin/revenue?method=gcash')->assertViewHas('overview', fn (array $data): bool => $data['combined'] === 50000);
        $this->get('/admin/revenue?method=xendit_sandbox')->assertViewHas('overview', fn (array $data): bool => $data['sources'] === ['services' => 30000, 'donations' => 0, 'masses' => 0])
            ->assertSee('Test payments included');
    }

    public function test_finance_aggregates_do_not_expose_donor_details_and_export_requires_reports_permission(): void
    {
        config(['office.permissions.admin' => ['finance.view']]);
        $this->verified();
        Donation::factory()->create(['donor_name' => 'Private Donor', 'donor_email' => 'secret@example.com', 'amount_cents' => 25000]);
        $this->get('/admin/revenue')->assertViewHas('overview', fn (array $data): bool => $data['combined'] === 25000)
            ->assertDontSee('Private Donor')->assertDontSee('secret@example.com')->assertDontSee('Download revenue report (CSV)');
        $this->get('/admin/revenue?export=revenue')->assertForbidden();
        $this->get('/admin/donations')->assertForbidden();
    }

    public function test_demo_revenue_uses_only_the_demo_dataset(): void
    {
        config(['office.demo' => true]);
        $this->verified();
        Donation::factory()->create(['amount_cents' => 90000]);
        Donation::factory()->create(['source' => 'synthetic', 'amount_cents' => 10000]);
        Donation::factory()->create(['source' => 'synthetic', 'kind' => 'mass_collection', 'amount_cents' => 20000]);
        $this->get('/admin/revenue')->assertViewHas('overview', fn (array $data): bool => $data['combined'] === 30000)->assertSee('Demo workspace');
        $this->get('/admin/revenue?export=revenue')->assertDownload('DEMO-parish-revenue-2026-11-01-2026-11-10.csv');
    }

    public function test_empty_and_refund_only_charts_render_without_invalid_dimensions(): void
    {
        $this->verified();
        $this->get('/admin/revenue')->assertViewHas('overview', ['sources' => ['services' => 0, 'donations' => 0, 'masses' => 0], 'monthly' => [], 'combined' => 0])
            ->assertSee('No revenue activity matches these dates and payment method.');
        $payment = Payment::factory()->create(['paid_at' => '2026-10-01']);
        Payment::factory()->create(['appointment_id' => $payment->appointment_id, 'kind' => 'refund', 'original_payment_id' => $payment->id, 'amount_cents' => 5000]);
        $this->get('/admin/revenue')->assertViewHas('overview', fn (array $data): bool => $data['combined'] === -5000)
            ->assertSee('-50.00')->assertDontSee('height="-', false)->assertDontSee('NaN');
    }

    private function verified(): void
    {
        $user = User::factory()->admin()->withTwoFactor()->create();
        $this->actingAs($user)->withSession(['admin.mfa_user_id' => $user->id, 'admin.mfa_fingerprint' => $user->adminSessionFingerprint()]);
    }
}
