<?php

namespace App\Support;

use App\Models\AppointmentInvoice;
use App\Models\Payment;

class FinancialDocumentSeal
{
    public static function sign(AppointmentInvoice|Payment $document): string
    {
        $key = (string) config('app.key');
        if ($key === '') {
            throw new \LogicException('A signing key is required for financial documents.');
        }

        return hash_hmac('sha256', self::payload($document), $key);
    }

    public static function valid(AppointmentInvoice|Payment $document): bool
    {
        if (! is_string($document->integrity_seal) || strlen($document->integrity_seal) !== 64) {
            return false;
        }
        try {
            $payload = self::payload($document);
        } catch (\Throwable) {
            return false;
        }
        foreach (array_filter([config('app.key'), ...config('app.previous_keys', [])]) as $key) {
            if (hash_equals($document->integrity_seal, hash_hmac('sha256', $payload, $key))) {
                return true;
            }
        }

        return false;
    }

    private static function payload(AppointmentInvoice|Payment $document): string
    {
        if ($document instanceof AppointmentInvoice) {
            $data = $document->only(['appointment_id', 'issued_by', 'number', 'revision', 'parish_name', 'client_name', 'appointment_reference', 'service_label', 'source', 'amount_cents']);
            $data['issued_at'] = $document->issued_at?->format('Y-m-d H:i:s');
            $type = 'invoice';
        } else {
            $data = $document->only(['appointment_id', 'recorded_by', 'original_payment_id', 'idempotency_key', 'kind', 'amount_cents', 'method', 'reference', 'notes', 'receipt_snapshot']);
            $data['paid_at'] = $document->paid_at?->format('Y-m-d H:i:s');
            $type = 'payment';
        }

        return 'parish-financial-document:v1:'.$type.':'.json_encode(self::canonical($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function canonical(array $data): array
    {
        ksort($data);
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::canonical($value);
            }
        }

        return $data;
    }
}
