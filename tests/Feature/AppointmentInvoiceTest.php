<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentInvoice;
use App\Models\Payment;
use App\Models\PaymentAdjustment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class AppointmentInvoiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function verified(User $user): static
    {
        return $this->actingAs($user)->withSession(['admin.mfa_user_id' => $user->id, 'admin.mfa_fingerprint' => $user->adminSessionFingerprint()]);
    }

    public function test_invoice_can_be_issued_before_payment_and_repeated_submission_reuses_it(): void
    {
        $this->withoutVite();
        $this->freezeTime();
        $user = User::factory()->admin()->withTwoFactor()->create();
        $appointment = Appointment::factory()->create(['fee_cents' => 12345, 'client_full_name' => 'Original Client']);
        $this->verified($user);

        $response = $this->post(route('admin.invoices.store', $appointment), ['fee_cents' => 12345]);

        $invoice = AppointmentInvoice::query()->sole();
        $response->assertRedirect(route('admin.appointments.billing', $appointment).'#invoice-'.$invoice->id);
        $this->assertDatabaseHas('appointment_invoices', ['appointment_id' => $appointment->id, 'amount_cents' => 12345, 'client_name' => 'Original Client', 'issued_by' => $user->id, 'revision' => 1]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'invoice.issued', 'entity_id' => $invoice->id, 'actor_id' => $user->id]);
        $this->assertDatabaseCount('payments', 0);
        $this->post(route('admin.invoices.store', $appointment), ['fee_cents' => 12345])->assertRedirect();
        $this->assertDatabaseCount('appointment_invoices', 1);
        $this->get(route('admin.appointments.billing', $appointment))->assertSee($invoice->number)->assertSee('123.45')->assertSee('Print / Save invoice PDF');
    }

    public function test_approved_fee_change_requires_replacement_and_retains_original_snapshot(): void
    {
        $this->withoutVite();
        $appointment = Appointment::factory()->create(['fee_cents' => 10000, 'client_full_name' => 'Original Client']);
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $this->post(route('admin.invoices.store', $appointment), ['fee_cents' => 10000])->assertRedirect();
        $original = AppointmentInvoice::query()->sole();
        $this->patch(route('admin.payments.fee', $appointment), ['idempotency_key' => (string) Str::uuid(), 'fee' => '50.00', 'version' => $appointment->refresh()->version, 'reason' => 'Approved reduction'])->assertSessionHasNoErrors();
        $this->post(route('admin.invoices.store', $appointment), ['fee_cents' => 10000])->assertRedirect();
        $this->assertDatabaseCount('appointment_invoices', 1);

        $this->verified(User::factory()->superAdmin()->withTwoFactor()->create())
            ->patch(route('admin.payment-adjustments.update', PaymentAdjustment::query()->sole()), ['decision' => 'approved', 'reason' => 'Reviewed'])->assertSessionHasNoErrors();

        $this->get(route('admin.appointments.billing', $appointment))->assertSee('FEE CHANGED');
        $appointment->update(['client_full_name' => 'Updated Client']);
        $this->post(route('admin.invoices.store', $appointment), ['fee_cents' => 5000])->assertRedirect();
        $this->assertDatabaseHas('appointment_invoices', ['id' => $original->id, 'amount_cents' => 10000, 'client_name' => 'Original Client']);
        $this->assertDatabaseHas('appointment_invoices', ['appointment_id' => $appointment->id, 'amount_cents' => 5000, 'revision' => 2, 'client_name' => 'Updated Client']);
        $this->get(route('admin.appointments.billing', $appointment))->assertSee('SUPERSEDED')->assertSee('50.00')->assertSee('Original Client');
    }

    public function test_stale_or_invalid_fee_cannot_issue_an_invoice_or_change_charges(): void
    {
        $appointment = Appointment::factory()->create(['fee_cents' => 10000]);
        $this->verified(User::factory()->admin()->withTwoFactor()->create());

        $this->post(route('admin.invoices.store', $appointment), ['fee_cents' => 1])->assertConflict();
        $this->post(route('admin.invoices.store', $appointment), [])->assertSessionHasErrors('fee_cents');
        $this->post(route('admin.invoices.store', $appointment), ['fee_cents' => -1])->assertSessionHasErrors('fee_cents');

        $this->assertDatabaseCount('appointment_invoices', 0);
        $this->assertSame(10000, $appointment->refresh()->fee_cents);
    }

    public function test_invoice_issuance_requires_login_mfa_permission_and_matching_dataset(): void
    {
        $appointment = Appointment::factory()->create(['fee_cents' => 10000]);
        $url = route('admin.invoices.store', $appointment);
        $this->post($url, ['fee_cents' => 10000])->assertRedirect('/login');
        $this->actingAs(User::factory()->admin()->create())->post($url, ['fee_cents' => 10000])->assertRedirect('/admin/security');
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        Gate::define('payments.manage', fn (User $user): bool => false);
        $this->post($url, ['fee_cents' => 10000])->assertForbidden();
        Gate::define('payments.manage', fn (User $user): bool => true);
        $demo = Appointment::factory()->create(['source' => 'synthetic', 'fee_cents' => 10000]);
        $this->post(route('admin.invoices.store', $demo), ['fee_cents' => 10000])->assertNotFound();
        $this->assertDatabaseCount('appointment_invoices', 0);
    }

    public function test_signed_billing_page_exposes_only_own_invoice_and_receipts_without_private_notes(): void
    {
        $this->withoutVite();
        $appointment = Appointment::factory()->create(['fee_cents' => 10000]);
        $invoice = AppointmentInvoice::factory()->for($appointment)->create(['client_name' => '<script>bad()</script>']);
        $other = AppointmentInvoice::factory()->create();
        $payment = Payment::factory()->for($appointment)->create(['amount_cents' => 8000, 'notes' => 'PRIVATE NOTE', 'reference' => '<script>receipt()</script>']);
        Payment::factory()->for($appointment)->create(['kind' => 'refund', 'original_payment_id' => $payment->id, 'amount_cents' => 1000]);
        $url = URL::temporarySignedRoute('appointments.billing', now()->addMinutes(5), ['appointment' => $appointment, 'access_token' => 'invoice-token']);

        $this->withSession(['appointment_access_tokens' => ['invoice-token' => $appointment->id]])->get($url)
            ->assertSee($invoice->number)->assertDontSee($other->number)
            ->assertSee($payment->receiptNumber())->assertSee('80.00')->assertSee('10.00')->assertSee('30.00')
            ->assertSee('Print / Save receipt PDF')->assertDontSee('PRIVATE NOTE')
            ->assertDontSee('<script>bad()</script>', false)->assertDontSee('<script>receipt()</script>', false)
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');

        $this->get($url)->assertRedirect(route('appointments.lookup.create'));
        $this->assertDatabaseCount('appointment_invoices', 2);
    }

    public function test_sandbox_receipts_and_invoice_balances_are_marked_as_demonstration(): void
    {
        $this->withoutVite();
        $appointment = Appointment::factory()->create(['fee_cents' => 10000]);
        AppointmentInvoice::factory()->for($appointment)->create();
        Payment::factory()->for($appointment)->create(['method' => 'xendit_sandbox']);
        $this->verified(User::factory()->admin()->withTwoFactor()->create());

        $this->get(route('admin.appointments.billing', $appointment))->assertSee('No actual funds received for this entry.')->assertSee('Demonstration balance includes sandbox entries.');
    }

    public function test_waived_fee_invoice_shows_no_payment_due_without_creating_receipt(): void
    {
        $this->withoutVite();
        $appointment = Appointment::factory()->create(['fee_cents' => 0]);
        $this->verified(User::factory()->admin()->withTwoFactor()->create());
        $this->post(route('admin.invoices.store', $appointment), ['fee_cents' => 0])->assertRedirect();

        $this->get(route('admin.appointments.billing', $appointment))->assertSee('No payment due')->assertDontSee('Print / Save receipt PDF');
        $this->assertDatabaseHas('appointment_invoices', ['appointment_id' => $appointment->id, 'amount_cents' => 0]);
    }

    public function test_issued_invoice_cannot_be_overwritten(): void
    {
        $invoice = AppointmentInvoice::factory()->create();
        $this->expectException(\LogicException::class);

        $invoice->update(['amount_cents' => 1]);
    }

    public function test_issued_invoice_cannot_be_deleted(): void
    {
        $invoice = AppointmentInvoice::factory()->create();
        $this->expectException(\LogicException::class);

        $invoice->delete();
    }
}
