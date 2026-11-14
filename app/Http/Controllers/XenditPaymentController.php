<?php

namespace App\Http\Controllers;

use App\Actions\StartXenditPayment;
use App\Models\Appointment;
use App\Models\PaymentTransaction;
use App\Support\AppointmentAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
                'pageTitle' => 'Online payment unavailable',
                'appointment' => $appointment,
                'transaction' => null,
                'error' => 'Online payment could not be started. Your appointment is still reserved, and you may pay at the parish office.',
            ], 503)->withHeaders($this->privateHeaders());
        }

        return redirect()->away((string) $transaction->checkout_url);
    }

    public function show(
        Request $request,
        PaymentTransaction $paymentTransaction,
        AppointmentAccess $appointmentAccess,
    ): RedirectResponse|Response {
        $paymentTransaction->load('appointment');

        if ($paymentTransaction->status === 'paid') {
            return redirect()->to(
                $appointmentAccess->issue($request, $paymentTransaction->appointment),
            )->with('appointment_message', 'Payment confirmed. Status: Paid · Test transaction.');
        }

        return response()->view('client-side.appointments.payment-return', [
            'pageTitle' => 'Payment status',
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
