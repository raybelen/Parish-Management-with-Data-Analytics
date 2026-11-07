<?php

namespace App\Actions;

use App\Models\Appointment;
use App\Models\PaymentTransaction;
use App\Services\XenditPaymentGateway;
use App\Support\OfficeAudit;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class StartXenditPayment
{
    public function __construct(private XenditPaymentGateway $gateway) {}

    public function handle(Appointment $appointment): PaymentTransaction
    {
        $transaction = DB::transaction(function () use ($appointment): PaymentTransaction {
            $lockedAppointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            $lockedAppointment->load('payments');

            if ($lockedAppointment->payment_option !== 'online') {
                throw ValidationException::withMessages(['payment_option' => 'This appointment is set to walk-in payment.']);
            }

            if (! in_array($lockedAppointment->status, ['pending', 'confirmed', 'completed'], true)) {
                throw ValidationException::withMessages(['payment_option' => 'Online payment is not available for this appointment status.']);
            }

            if ($lockedAppointment->balanceCents() < 1) {
                $lockedAppointment->update(['payment_status' => 'not_required']);
                throw ValidationException::withMessages(['payment_option' => 'No payment balance is due for this appointment.']);
            }

            $activeTransaction = $lockedAppointment->paymentTransactions()
                ->where('status', 'pending')
                ->where('amount_cents', $lockedAppointment->balanceCents())
                ->where('expires_at', '>', now())
                ->whereNotNull('checkout_url')
                ->latest('id')
                ->first();
            if ($activeTransaction !== null) {
                return $activeTransaction;
            }

            $publicId = (string) Str::uuid();

            return $lockedAppointment->paymentTransactions()->create([
                'public_id' => $publicId,
                'provider' => 'xendit',
                'environment' => 'sandbox',
                'reference_id' => 'pay'.str_replace('-', '', $publicId),
                'amount_cents' => $lockedAppointment->balanceCents(),
                'currency' => 'PHP',
                'status' => 'creating',
            ]);
        });

        if ($transaction->status === 'pending') {
            return $transaction;
        }

        $transaction->load('appointment');
        $returnUrl = URL::route('appointments.payments.return', $transaction);

        try {
            $session = $this->gateway->createSession($transaction, $returnUrl);
        } catch (Throwable $exception) {
            $errorCode = $exception instanceof RequestException
                ? $exception->response->json('error_code')
                : null;

            DB::transaction(function () use ($transaction, $exception, $errorCode): void {
                $lockedTransaction = PaymentTransaction::query()->lockForUpdate()->findOrFail($transaction->id);
                $lockedTransaction->update([
                    'status' => 'failed',
                    'failure_code' => is_string($errorCode) ? Str::limit($errorCode, 255, '') : null,
                    'failure_message' => Str::limit($exception->getMessage(), 1000, ''),
                ]);
                $lockedAppointment = Appointment::query()->lockForUpdate()->findOrFail($lockedTransaction->appointment_id);
                $lockedAppointment->load('payments');
                $lockedAppointment->update(['payment_status' => match (true) {
                    $lockedAppointment->fee_cents === 0 => 'not_required',
                    $lockedAppointment->balanceCents() === 0 => 'paid',
                    $lockedAppointment->netPaidCents() > 0 => 'partial',
                    default => 'failed',
                }]);
            });

            throw $exception;
        }

        return DB::transaction(function () use ($transaction, $session): PaymentTransaction {
            $lockedTransaction = PaymentTransaction::query()->lockForUpdate()->findOrFail($transaction->id);
            $lockedTransaction->update([
                'provider_session_id' => $session['payment_session_id'],
                'provider_business_id' => $session['business_id'],
                'checkout_url' => $session['payment_link_url'],
                'expires_at' => CarbonImmutable::parse($session['expires_at']),
                'status' => 'pending',
                'failure_code' => null,
                'failure_message' => null,
            ]);
            $lockedAppointment = Appointment::query()->lockForUpdate()->findOrFail($lockedTransaction->appointment_id);
            $lockedAppointment->load('payments');
            $lockedAppointment->update(['payment_status' => match (true) {
                $lockedAppointment->fee_cents === 0 => 'not_required',
                $lockedAppointment->balanceCents() === 0 => 'paid',
                $lockedAppointment->netPaidCents() > 0 => 'partial',
                default => 'pending',
            }]);
            OfficeAudit::record('payment.xendit_started', $lockedTransaction, [], [
                'appointment_id' => $lockedTransaction->appointment_id,
                'amount_cents' => $lockedTransaction->amount_cents,
                'environment' => 'sandbox',
            ]);

            return $lockedTransaction->refresh();
        });
    }
}
