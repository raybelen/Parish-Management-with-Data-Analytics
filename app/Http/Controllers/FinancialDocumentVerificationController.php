<?php

namespace App\Http\Controllers;

use App\Models\AppointmentInvoice;
use App\Models\Payment;
use App\Support\FinancialDocumentSeal;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class FinancialDocumentVerificationController extends Controller
{
    public function create(): Response
    {
        return $this->page();
    }

    public function store(Request $request): Response
    {
        $validator = Validator::make($request->only(['number', 'code', 'holder']), [
            'number' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'holder' => ['required', 'string', 'max:255'],
        ]);
        if ($validator->fails()) {
            return $this->page(error: true);
        }
        $data = $validator->validated();
        $document = AppointmentInvoice::query()->where('integrity_seal', $data['code'])->first()
            ?? Payment::query()->where('kind', 'payment')->where('integrity_seal', $data['code'])->first();
        if (! $document || ! FinancialDocumentSeal::valid($document)) {
            return $this->page(error: true);
        }
        $isInvoice = $document instanceof AppointmentInvoice;
        $number = $isInvoice ? $document->number : $document->receiptNumber();
        $holder = $isInvoice ? $document->client_name : ($document->receipt_snapshot['client_name'] ?? '');
        if (! hash_equals($number, $data['number']) || Str::lower(Str::squish($holder)) !== Str::lower(Str::squish($data['holder']))) {
            return $this->page(error: true);
        }

        if ($isInvoice) {
            $latest = $document->appointment->invoices()->latest('revision')->first();
            if (! $latest || ! FinancialDocumentSeal::valid($latest)) {
                return $this->page(error: true);
            }
            $status = match (true) {
                $latest->id !== $document->id => 'Superseded invoice — do not pay separately.',
                in_array($document->appointment->status, ['cancelled', 'declined', 'expired', 'no_show'], true) => 'Appointment is not active — contact the office before paying.',
                $document->amount_cents !== $document->appointment->fee_cents => 'Fee changed — request a replacement invoice.',
                default => 'Issued invoice — not proof of payment.',
            };
            $demo = $document->source === 'synthetic';
            $date = $document->issued_at;
            $refundedCents = null;
        } else {
            $refunds = Payment::query()->where('kind', 'refund')->where('original_payment_id', $document->id)->get();
            $refundsVerified = $refunds->every(fn (Payment $refund): bool => FinancialDocumentSeal::valid($refund));
            $refundedCents = $refundsVerified ? (int) $refunds->sum('amount_cents') : null;
            $status = ! $refundsVerified ? 'Refund history requires office review.' : ($refundedCents > 0 ? 'Payment receipt with recorded refunds.' : 'Payment receipt — no refunds recorded.');
            $demo = $document->method === 'xendit_sandbox' || ($document->receipt_snapshot['source'] ?? null) === 'synthetic';
            $date = $document->paid_at;
        }

        return $this->page([
            'number' => $number, 'type' => $isInvoice ? 'Invoice' : 'Payment receipt',
            'amount_cents' => $document->amount_cents, 'date' => $date,
            'status' => $status, 'demo' => $demo, 'refunded_cents' => $refundedCents,
            'parish' => $isInvoice ? $document->parish_name : $document->receipt_snapshot['parish_name'],
            'service' => $isInvoice ? $document->service_label : $document->receipt_snapshot['service_label'],
            'method' => $isInvoice ? null : ($document->method === 'xendit_sandbox' ? 'Online Payment' : Str::headline($document->method)),
        ]);
    }

    /** @param array{number: string, type: string, amount_cents: int, date: CarbonInterface, status: string, demo: bool, refunded_cents: ?int, parish: string, service: string, method: ?string}|null $result */
    private function page(?array $result = null, bool $error = false): Response
    {
        return response()->view('client-side.appointments.verify-document', [
            'pageTitle' => 'Verify an invoice or payment receipt', 'result' => $result, 'verificationError' => $error,
        ], $error ? 422 : 200)->withHeaders([
            'Cache-Control' => 'no-store, private, max-age=0', 'Pragma' => 'no-cache',
            'Referrer-Policy' => 'no-referrer', 'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
