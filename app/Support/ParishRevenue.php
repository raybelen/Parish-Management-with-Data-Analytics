<?php

namespace App\Support;

use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;

class ParishRevenue
{
    /** @param array{from: string, to: string, service?: ?string, method?: ?string} $filters */
    public function payments(User $user, array $filters, bool $sandbox = false): Builder
    {
        $appointments = OfficeAccess::appointments($user)
            ->when($filters['service'] ?? null, fn (Builder $query, string $service): Builder => $query->where('service_type', $service));

        return Payment::query()->whereIn('appointment_id', $appointments->select('appointments.id'))
            ->when($sandbox, fn (Builder $query): Builder => $query->sandboxEntries())
            ->whereBetween('paid_at', [CarbonImmutable::parse($filters['from'])->startOfDay(), CarbonImmutable::parse($filters['to'])->endOfDay()])
            ->when($filters['method'] ?? null, fn (Builder $query, string $method): Builder => $method === 'xendit_sandbox'
                ? $query->sandboxEntries()
                : $query->where('method', $method)->whereDoesntHave('originalPayment', fn (Builder $original): Builder => $original->where('method', 'xendit_sandbox')));
    }

    /** @return array{received: int, refunded: int, net: int} */
    public function totals(Builder $payments): array
    {
        $totals = (clone $payments)->toBase()
            ->selectRaw("COALESCE(SUM(CASE WHEN kind = 'payment' THEN amount_cents ELSE 0 END), 0) AS received")
            ->selectRaw("COALESCE(SUM(CASE WHEN kind = 'refund' THEN amount_cents ELSE 0 END), 0) AS refunded")->first();

        return ['received' => (int) $totals->received, 'refunded' => (int) $totals->refunded, 'net' => (int) $totals->received - (int) $totals->refunded];
    }

    public function outstanding(User $user, ?string $service = null): Builder
    {
        $payments = Payment::query()->select('appointment_id')
            ->selectRaw("SUM(CASE WHEN kind = 'refund' THEN -amount_cents ELSE amount_cents END) AS net_paid_cents")
            ->groupBy('appointment_id');

        return OfficeAccess::appointments($user)
            ->whereIn('appointments.status', ['pending', 'confirmed', 'completed'])
            ->when($service, fn (Builder $query): Builder => $query->where('service_type', $service))
            ->leftJoinSub($payments, 'collections', fn (JoinClause $join) => $join->on('appointments.id', '=', 'collections.appointment_id'))
            ->whereRaw('appointments.fee_cents > COALESCE(collections.net_paid_cents, 0)')
            ->select('appointments.*')
            ->selectRaw('appointments.fee_cents - COALESCE(collections.net_paid_cents, 0) AS outstanding_cents');
    }
}
