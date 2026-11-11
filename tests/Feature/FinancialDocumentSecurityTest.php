<?php

namespace Tests\Feature;

use App\Actions\CreateOfficeBackup;
use App\Actions\IssueAppointmentInvoice;
use App\Mail\AppointmentReserved;
use App\Models\Appointment;
use App\Models\AppointmentInvoice;
use App\Models\Payment;
use App\Models\User;
use App\Support\FinancialDocumentSeal;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinancialDocumentSecurityTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_invoice_verification_checks_details_without_disclosing_client_contact_or_access(): void
    {
        $this->withoutVite();
        $appointment = Appointment::factory()->create(['fee_cents' => 12345, 'client_email' => 'private-client@example.test', 'client_contact_number' => '09128887777']);
        $invoice = AppointmentInvoice::factory()->for($appointment)->create(['client_name' => 'Private Client', 'amount_cents' => 12345]);

        $this->post(route('documents.verify.store'), $this->details($invoice))
            ->assertOk()->assertSee('Record verified')->assertSee('123.45')->assertSee('not proof of payment')
            ->assertDontSee('Private Client')->assertDontSee('private-client@example.test')->assertDontSee('09128887777')->assertDontSee('access_token')
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertTrue(FinancialDocumentSeal::valid($invoice->refresh()));
    }

    public function test_wrong_code_number_and_client_name_are_rejected_without_flashing_secrets(): void
    {
        $this->withoutVite();
        $invoice = AppointmentInvoice::factory()->create();
        foreach ([['number' => 'INV-FORGED'], ['holder' => 'Different Client'], ['code' => str_repeat('0', 64)], ['code' => 'invalid']] as $override) {
            $this->post(route('documents.verify.store'), [...$this->details($invoice), ...$override])
                ->assertUnprocessable()->assertSee('We could not verify')->assertDontSee('Record verified')->assertDontSee($invoice->client_name)
                ->assertSessionMissing('_old_input');
        }
    }

    public function test_direct_database_invoice_tampering_is_detected_by_verification_and_printing(): void
    {
        $this->withoutVite();
        $invoice = AppointmentInvoice::factory()->create();
        $details = $this->details($invoice);
        DB::table('appointment_invoices')->where('id', $invoice->id)->update(['amount_cents' => 1]);

        $this->post(route('documents.verify.store'), $details)->assertUnprocessable();
        $user = User::factory()->admin()->withTwoFactor()->create();
        $this->actingAs($user)->withSession(['admin.mfa_user_id' => $user->id, 'admin.mfa_fingerprint' => $user->adminSessionFingerprint()])
            ->get(route('admin.appointments.billing', $invoice->appointment))->assertConflict();
        $this->assertFalse(FinancialDocumentSeal::valid($invoice->refresh()));
    }

    public function test_receipt_preserves_original_details_after_client_changes_and_detects_tampering(): void
    {
        $this->withoutVite();
        $appointment = Appointment::factory()->create(['client_full_name' => 'Original Client']);
        $payment = Payment::factory()->for($appointment)->create(['amount_cents' => 25000]);
        $appointment->update(['client_full_name' => 'Updated Client']);

        $this->post(route('documents.verify.store'), $this->details($payment))->assertOk()->assertSee('250.00');
        $this->assertSame('Original Client', $payment->refresh()->receipt_snapshot['client_name']);
        DB::table('payments')->where('id', $payment->id)->update(['amount_cents' => 999]);
        $this->post(route('documents.verify.store'), $this->details($payment))->assertUnprocessable();
    }

    public function test_refunds_and_sandbox_status_are_visible_during_receipt_verification(): void
    {
        $this->withoutVite();
        $payment = Payment::factory()->create(['method' => 'xendit_sandbox', 'amount_cents' => 10000]);
        Payment::factory()->for($payment->appointment)->create(['kind' => 'refund', 'original_payment_id' => $payment->id, 'method' => 'xendit_sandbox', 'amount_cents' => 2500]);

        $this->post(route('documents.verify.store'), $this->details($payment))->assertOk()
            ->assertSee('DEMO')->assertSee('with recorded refunds')->assertSee('25.00');
    }

    public function test_unsealed_refund_history_is_not_reported_as_verified(): void
    {
        $this->withoutVite();
        $payment = Payment::factory()->create();
        $refund = Payment::factory()->for($payment->appointment)->create(['kind' => 'refund', 'original_payment_id' => $payment->id, 'amount_cents' => 2500]);
        DB::table('payments')->where('id', $refund->id)->update(['integrity_seal' => null]);

        $this->post(route('documents.verify.store'), $this->details($payment))->assertOk()->assertSee('Refund history requires office review')->assertDontSee('no refunds recorded');
    }

    public function test_replaced_invoice_is_not_reported_as_current(): void
    {
        $this->withoutVite();
        $invoice = AppointmentInvoice::factory()->create();
        AppointmentInvoice::factory()->for($invoice->appointment)->create(['revision' => 2]);

        $this->post(route('documents.verify.store'), $this->details($invoice))->assertOk()->assertSee('Superseded invoice');
    }

    public function test_legacy_invoice_is_not_certified_and_reissue_preserves_original(): void
    {
        $invoice = AppointmentInvoice::factory()->create();
        DB::table('appointment_invoices')->where('id', $invoice->id)->update(['integrity_seal' => null]);

        $replacement = app(IssueAppointmentInvoice::class)->handle($invoice->appointment, $invoice->appointment->fee_cents);

        $this->assertSame(2, $replacement->revision);
        $this->assertTrue(FinancialDocumentSeal::valid($replacement->refresh()));
        $this->assertFalse(FinancialDocumentSeal::valid($invoice->refresh()));
        $this->assertDatabaseCount('appointment_invoices', 2);
    }

    public function test_cancelled_appointment_invoice_is_not_presented_as_payable(): void
    {
        $this->withoutVite();
        $invoice = AppointmentInvoice::factory()->for(Appointment::factory()->create(['status' => 'cancelled']))->create();

        $this->post(route('documents.verify.store'), $this->details($invoice))->assertOk()->assertSee('contact the office before paying');
    }

    public function test_receipt_legacy_copy_and_forged_snapshot_are_not_silently_verified(): void
    {
        $this->withoutVite();
        $payment = Payment::factory()->create();
        $details = $this->details($payment);
        DB::table('payments')->where('id', $payment->id)->update(['receipt_snapshot' => json_encode([...$payment->receipt_snapshot, 'client_name' => 'Another person'])]);
        $this->post(route('documents.verify.store'), [...$details, 'holder' => 'Another person'])->assertUnprocessable();
        DB::table('payments')->where('id', $payment->id)->update(['integrity_seal' => null, 'receipt_snapshot' => null]);
        $this->post(route('documents.verify.store'), $details)->assertUnprocessable();
    }

    public function test_key_rotation_can_verify_previous_documents_without_resigning_them(): void
    {
        $oldKey = config('app.key');
        $invoice = AppointmentInvoice::factory()->create();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32)), 'app.previous_keys' => [$oldKey]]);

        $this->assertTrue(FinancialDocumentSeal::valid($invoice->refresh()));
        config(['app.previous_keys' => []]);
        $this->assertFalse(FinancialDocumentSeal::valid($invoice));
    }

    public function test_walk_in_payment_creates_one_sealed_receipt_and_preserves_replay_protection(): void
    {
        $this->travelTo('2026-11-10 10:00');
        $user = User::factory()->admin()->withTwoFactor()->create();
        $this->actingAs($user)->withSession(['admin.mfa_user_id' => $user->id, 'admin.mfa_fingerprint' => $user->adminSessionFingerprint()]);
        $appointment = Appointment::factory()->create();
        $payload = ['idempotency_key' => (string) Str::uuid(), 'kind' => 'payment', 'amount' => '100.00', 'method' => 'cash', 'paid_at' => '2026-11-10T09:00', 'notes' => 'Cash collected', 'integrity_seal' => 'forged', 'receipt_snapshot' => ['client_name' => 'Attacker']];

        $this->post(route('admin.payments.store', $appointment), $payload)->assertSessionHasNoErrors();
        $this->post(route('admin.payments.store', $appointment), $payload)->assertSessionHasNoErrors();

        $payment = Payment::query()->sole();
        $this->assertTrue(FinancialDocumentSeal::valid($payment));
        $this->assertSame($appointment->client_full_name, $payment->receipt_snapshot['client_name']);
        $this->assertSame($user->id, $payment->recorded_by);
        $this->post(route('admin.payments.store', $appointment), [...$payload, 'amount' => '200.00'])->assertConflict();
    }

    public function test_tampered_invoice_is_not_rendered_in_email(): void
    {
        $invoice = AppointmentInvoice::factory()->create();
        DB::table('appointment_invoices')->where('id', $invoice->id)->update(['client_name' => 'Attacker']);
        $mail = new AppointmentReserved($invoice->appointment, $invoice->refresh());
        $this->expectException(\LogicException::class);

        $mail->content();
    }

    public function test_verification_attempts_are_rate_limited(): void
    {
        $this->withoutVite();
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->post(route('documents.verify.store'), [])->assertUnprocessable();
        }
        $this->post(route('documents.verify.store'), [])->assertTooManyRequests();
    }

    public function test_encrypted_backup_includes_invoice_seals_and_receipt_snapshots(): void
    {
        Storage::fake('local');
        $invoice = AppointmentInvoice::factory()->create();
        $payment = Payment::factory()->for($invoice->appointment)->create();

        $path = app(CreateOfficeBackup::class)->handle();

        $backup = json_decode(Crypt::decryptString(Storage::disk('local')->get($path)), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($invoice->integrity_seal, $backup['tables']['appointment_invoices'][0]['integrity_seal']);
        $this->assertSame($payment->integrity_seal, $backup['tables']['payments'][0]['integrity_seal']);
        $this->assertNotEmpty($backup['tables']['payments'][0]['receipt_snapshot']);
    }

    /** @return array{number: string, code: string, holder: string} */
    private function details(AppointmentInvoice|Payment $document): array
    {
        return [
            'number' => $document instanceof AppointmentInvoice ? $document->number : $document->receiptNumber(),
            'code' => $document->integrity_seal,
            'holder' => $document instanceof AppointmentInvoice ? $document->client_name : $document->receipt_snapshot['client_name'],
        ];
    }
}
