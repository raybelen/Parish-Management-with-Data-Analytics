<?php

namespace App\Http\Controllers;

use App\Actions\StartXenditPayment;
use App\Models\Appointment;
use App\Models\PaymentTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Throwable;

class XenditPaymentController extends Controller
{
    public function store(Appointment $appointment, StartXenditPayment $startPayment): RedirectResponse|Response
    {
        try {
            $transaction = $startPayment->handle($appointment);
        } catch (Throwable $exception) {
            report($exception);

            return response()->view('client-side.appointments.payment-return', [
                'pageTitle' => 'Online Payment Unavailable',
                'appointment' => $appointment,
                'transaction' => null,
                'error' => 'Xendit Sandbox checkout could not be started. Your appointment is still reserved, and you may pay at the parish office.',
            ], 503)->withHeaders($this->privateHeaders());
        }

        return redirect()->away((string) $transaction->checkout_url);
    }

    public function show(PaymentTransaction $paymentTransaction): Response
    {
        $paymentTransaction->load('appointment');

        return response()->view('client-side.appointments.payment-return', [
            'pageTitle' => 'Sandbox Payment Status',
            'appointment' => $paymentTransaction->appointment,
            'transaction' => $paymentTransaction,
            'error' => null,
        ])->withHeaders($this->privateHeaders());
    }

    /** @return array<string, string> */
    private function privateHeaders(): array
    {
        return [
            'Cache-Control' => 'no-store, private, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];
    }
}
