<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Personnel;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AdminRevenueTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_finance_page_requires_login_mfa_and_finance_permission(): void
    {
        $this->get('/admin/revenue')->assertRedirect('/login');
        $this->actingAs(User::factory()->staff()->create())->get('/admin/revenue')->assertRedirect('/admin/security');
        $this->verified(User::factory()->priest()->withTwoFactor()->create())->get('/admin/revenue')->assertForbidden();
        $this->get('/admin/revenue?export=collections')->assertForbidden();
    }

    public function test_collections_include_sandbox_and_refunds_but_exclude_other_datasets(): void
    {
        $this->withoutVite();
        $this->travelTo('2026-11-06 12:00');
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $appointment = Appointment::factory()->create(['fee_cents' => 100000, 'service_type' => 'baptism']);
        $real = Payment::factory()->for($appointment)->create(['amount_cents' => 40000, 'paid_at' => '2026-11-01 00:00:00']);
        Payment::factory()->for($appointment)->create(['kind' => 'refund', 'original_payment_id' => $real->id, 'amount_cents' => 5000, 'paid_at' => '2026-11-06 23:59:59']);
        $sandbox = Payment::factory()->for($appointment)->create(['method' => 'xendit_sandbox', 'amount_cents' => 100000]);
        Payment::factory()->for($appointment)->create(['kind' => 'refund', 'original_payment_id' => $sandbox->id, 'amount_cents' => 20000]);
        Payment::factory()->for(Appointment::factory()->create(['source' => 'synthetic']))->create(['amount_cents' => 900000]);
        Payment::factory()->for(Appointment::factory()->create(['source' => 'archival']))->create(['amount_cents' => 800000]);

        $this->get('/admin/revenue?from=2026-11-01&to=2026-11-06')
            ->assertViewHas('totals', ['received' => 140000, 'refunded' => 25000, 'net' => 115000])
            ->assertViewHas('sandboxTotals', ['received' => 100000, 'refunded' => 20000, 'net' => 80000])
            ->assertViewHas('outstandingTotal', 0)
            ->assertViewHas('payments', fn ($payments): bool => $payments->total() === 4)
            ->assertViewHas('byService', fn ($rows): bool => (int) $rows->sole()->net_cents === 115000)
            ->assertSee('Parish Revenue');

        $this->get('/admin/reports?from=2026-11-01&to=2026-11-06')
            ->assertViewHas('totals', fn (array $totals): bool => $totals['cash_collected'] == 1400 && $totals['refunds'] == 250 && $totals['outstanding'] == 0);
    }

    public function test_filters_use_payment_dates_but_outstanding_uses_all_dates_and_methods(): void
    {
        $this->withoutVite();
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $appointment = Appointment::factory()->create(['fee_cents' => 100000, 'service_type' => 'baptism']);
        $original = Payment::factory()->for($appointment)->create(['amount_cents' => 40000, 'method' => 'cash', 'paid_at' => '2026-10-31 23:59:59']);
        Payment::factory()->for($appointment)->create(['kind' => 'refund', 'original_payment_id' => $original->id, 'amount_cents' => 10000, 'method' => 'cash', 'paid_at' => '2026-11-01']);
        Payment::factory()->for($appointment)->create(['amount_cents' => 20000, 'method' => 'gcash', 'paid_at' => '2026-11-03']);
        Payment::factory()->for(Appointment::factory()->create(['service_type' => 'wedding']))->create(['paid_at' => '2026-11-03']);
        Appointment::factory()->create(['status' => 'cancelled', 'fee_cents' => 900000]);

        $this->get('/admin/revenue?from=2026-11-01&to=2026-11-06&service=baptism&method=cash')
            ->assertViewHas('totals', ['received' => 0, 'refunded' => 10000, 'net' => -10000])
            ->assertViewHas('outstandingTotal', 50000)
            ->assertViewHas('payments', fn ($payments): bool => $payments->total() === 1);
    }

    public function test_exports_match_filters_escape_spreadsheet_formulas_and_are_audited(): void
    {
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $appointment = Appointment::factory()->create(['fee_cents' => 100000, 'service_type' => 'baptism']);
        Payment::factory()->for($appointment)->create(['amount_cents' => 40000, 'reference' => '=DANGEROUS()', 'paid_at' => '2026-11-02']);
        Payment::factory()->for($appointment)->create(['method' => 'xendit_sandbox', 'reference' => 'SANDBOX-INCLUDED', 'paid_at' => '2026-11-02']);

        $response = $this->get('/admin/revenue?from=2026-11-01&to=2026-11-06&service=baptism&export=collections');

        $response->assertDownload('parish-collections-2026-11-01-2026-11-06.csv');
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=DANGEROUS()", $csv);
        $this->assertStringContainsString('SANDBOX-INCLUDED', $csv);
        $this->assertStringContainsString('400.00', $csv);
        $this->assertDatabaseHas('audit_logs', ['action' => 'revenue.exported']);

        $outstanding = $this->get('/admin/revenue?from=2026-11-01&to=2026-11-06&export=outstanding');
        $outstanding->assertDownload('parish-outstanding-2026-11-01-2026-11-06.csv');
        $this->assertStringContainsString('500.00', $outstanding->streamedContent());
    }

    public function test_invalid_filters_are_rejected_and_empty_dashboard_renders(): void
    {
        $this->withoutVite();
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $this->get('/admin/revenue?from=2026-11-06&to=2026-11-01&method=invalid&service=missing&export=invalid')
            ->assertSessionHasErrors(['to', 'method', 'service', 'export']);
        $this->get('/admin/revenue')->assertViewHas('totals', ['received' => 0, 'refunded' => 0, 'net' => 0])->assertSee('No collections match these filters.');
    }

    public function test_demo_workspace_is_separate_and_clearly_labelled(): void
    {
        $this->withoutVite();
        $this->travelTo('2026-11-06');
        config(['office.demo' => true]);
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        Payment::factory()->for(Appointment::factory()->create(['source' => 'operational']))->create(['amount_cents' => 70000]);
        Payment::factory()->for(Appointment::factory()->create(['source' => 'synthetic']))->create(['amount_cents' => 20000]);

        $this->get('/admin/revenue')->assertViewHas('totals', ['received' => 20000, 'refunded' => 0, 'net' => 20000])->assertSee('Demo workspace');
        $this->get('/admin/revenue?export=collections')->assertDownload('DEMO-parish-collections-2026-11-01-2026-11-06.csv');
    }

    public function test_totals_cover_all_pages_and_export_requires_report_permission(): void
    {
        $this->withoutVite();
        $this->travelTo('2026-11-06');
        config(['office.permissions.staff' => ['finance.view']]);
        $this->verified(User::factory()->staff()->withTwoFactor()->create());
        $appointment = Appointment::factory()->create(['fee_cents' => 300000]);
        Payment::factory()->count(21)->for($appointment)->create(['amount_cents' => 10000]);

        $this->get('/admin/revenue?page=2')->assertViewHas('totals', ['received' => 210000, 'refunded' => 0, 'net' => 210000])
            ->assertViewHas('payments', fn ($payments): bool => $payments->count() === 1 && $payments->total() === 21)
            ->assertDontSee('Download collections CSV');
        $this->get('/admin/revenue?export=collections')->assertForbidden();
    }

    public function test_unpaid_checkout_attempts_are_visible_without_inflating_collections(): void
    {
        $this->withoutVite();
        $this->travelTo('2026-11-06');
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $appointment = Appointment::factory()->create(['fee_cents' => 10000]);
        PaymentTransaction::factory()->count(3)->for($appointment)->sequence(['status' => 'pending'], ['status' => 'failed'], ['status' => 'expired'])->create();
        PaymentTransaction::factory()->for(Appointment::factory()->create(['source' => 'synthetic']))->create(['reference_id' => 'hidden-demo-checkout']);
        PaymentTransaction::factory()->for(Appointment::factory()->create(['service_type' => 'wedding']))->create(['reference_id' => 'hidden-wedding-checkout']);
        PaymentTransaction::factory()->for($appointment)->create(['created_at' => '2026-10-01', 'reference_id' => 'hidden-old-checkout']);

        $this->get('/admin/revenue?service=baptism&method=xendit_sandbox')
            ->assertViewHas('transactions', fn ($transactions): bool => $transactions->total() === 3)
            ->assertViewHas('totals', ['received' => 0, 'refunded' => 0, 'net' => 0])->assertViewHas('outstandingTotal', 10000)
            ->assertSee('Pending')->assertSee('Failed')->assertSee('Expired')
            ->assertDontSee('hidden-demo-checkout')->assertDontSee('hidden-wedding-checkout')->assertDontSee('hidden-old-checkout');
        $this->get('/admin/revenue?method=cash')->assertViewHas('transactions', fn ($transactions): bool => $transactions->total() === 0);
    }

    private function verified(User $user): static
    {
        return $this->actingAs($user)->withSession(['admin.mfa_user_id' => $user->id, 'admin.mfa_fingerprint' => $user->adminSessionFingerprint()]);
    }

    public function test_legacy_sandbox_refunds_do_not_appear_as_cash_collections(): void
    {
        $this->withoutVite();
        $this->travelTo('2026-11-07 12:00');
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $appointment = Appointment::factory()->create();
        $sandbox = Payment::factory()->for($appointment)->create(['method' => 'xendit_sandbox', 'amount_cents' => 10000]);
        Payment::factory()->for($appointment)->create(['kind' => 'refund', 'original_payment_id' => $sandbox->id,
            'method' => 'cash', 'amount_cents' => 2500, 'reference' => 'LEGACY-SANDBOX-REFUND']);
        Payment::factory()->for($appointment)->create(['method' => 'cash', 'amount_cents' => 5000]);

        $this->get('/admin/revenue?method=cash')->assertViewHas('totals', ['received' => 5000, 'refunded' => 0, 'net' => 5000])
            ->assertViewHas('sandboxTotals', ['received' => 0, 'refunded' => 0, 'net' => 0]);
        $this->assertStringNotContainsString('LEGACY-SANDBOX-REFUND', $this->get('/admin/revenue?method=cash&export=collections')->streamedContent());
        $this->get('/admin/revenue?method=xendit_sandbox')->assertViewHas('totals', ['received' => 10000, 'refunded' => 2500, 'net' => 7500]);
    }

    public function test_financial_reports_follow_the_same_assigned_appointment_scope_as_revenue(): void
    {
        $this->withoutVite();
        $this->travelTo('2026-11-07 12:00');
        config(['office.permissions.priest' => ['appointments.view', 'finance.view', 'reports.view']]);
        $priest = User::factory()->priest()->withTwoFactor()->create();
        $personnel = Personnel::factory()->create(['user_id' => $priest->id]);
        $assigned = Appointment::factory()->create(['personnel_id' => $personnel->id, 'fee_cents' => 10000,
            'status' => 'completed', 'completed_at' => '2026-11-06']);
        $other = Appointment::factory()->create(['fee_cents' => 900000, 'client_full_name' => 'Unassigned Private Client',
            'status' => 'completed', 'completed_at' => '2026-11-06']);
        Payment::factory()->for($assigned)->create(['amount_cents' => 4000, 'reference' => 'ASSIGNED-PAYMENT']);
        Payment::factory()->for($other)->create(['amount_cents' => 800000, 'reference' => 'UNASSIGNED-PAYMENT']);
        $this->verified($priest);

        $this->get('/admin/revenue')->assertViewHas('totals', ['received' => 4000, 'refunded' => 0, 'net' => 4000])
            ->assertViewHas('outstandingTotal', 6000);
        foreach (['/admin', '/admin/reports'] as $path) {
            $this->get($path)->assertViewHas('totals', fn (array $totals): bool => $totals['cash_collected'] == 40
                && $totals['outstanding'] == 60 && $totals['completed_revenue'] == 40 && $totals['completed'] === 1);
        }
        foreach (['payments', 'outstanding', 'appointments'] as $type) {
            $csv = $this->get('/admin/reports?export=1&type='.$type)->streamedContent();
            $this->assertStringContainsString($assigned->reference_number, $csv);
            $this->assertStringNotContainsString($other->reference_number, $csv);
        }
        $csv = $this->get('/admin/reports?export=1&type=monthly&from=2026-11-01&to=2026-11-07')->streamedContent();
        $this->assertStringContainsString('2026-11,1,40', $csv);
        $this->get(route('admin.appointments.billing', $other))->assertNotFound();
    }
}
