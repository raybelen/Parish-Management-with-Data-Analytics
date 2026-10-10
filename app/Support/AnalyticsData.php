<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class AnalyticsData
{
    public function appointments(?string $service = null): Builder
    {
        return Appointment::query()->where('source', OfficeSettings::source())
            ->when($service, fn (Builder $query) => $query->where('service_type', $service));
    }

    /** @return list<array{month: string, count: ?int, revenue: ?float}> */
    public function monthly(?string $service = null): array
    {
        $settings = OfficeSettings::all();
        if (! $settings['analytics_start']) {
            return [];
        }
        $end = CarbonImmutable::now()->startOfMonth();
        $start = CarbonImmutable::parse($settings['analytics_start'])->startOfMonth()->max($end->subMonths((int) $settings['training_months']));
        $rows = [];
        for ($month = $start; $month < $end; $month = $month->addMonth()) {
            $unknown = in_array($month->format('Y-m'), $settings['missing_months'], true);
            $rows[$month->format('Y-m')] = ['month' => $month->format('Y-m'), 'count' => $unknown ? null : 0, 'revenue' => $unknown ? null : 0.0];
        }
        foreach ($this->appointments($service)->where('status', 'completed')->whereBetween('completed_at', [$start, $end->subSecond()])->with('payments')->lazyById(250) as $appointment) {
            $key = $appointment->completed_at->format('Y-m');
            if (isset($rows[$key]) && $rows[$key]['count'] !== null) {
                $rows[$key]['count']++;
                $rows[$key]['revenue'] += $appointment->netPaidCents() / 100;
            }
        }

        return array_values($rows);
    }

    /** @return list<array{month: string, count: ?int, revenue: ?float}> */
    public function monthlyReport(string $from, string $to, ?string $service = null): array
    {
        $settings = OfficeSettings::all();
        $start = CarbonImmutable::parse($from)->startOfDay();
        $end = CarbonImmutable::parse($to)->endOfDay();
        $rows = [];
        for ($month = $start->startOfMonth(); $month <= $end; $month = $month->addMonth()) {
            $unknown = in_array($month->format('Y-m'), $settings['missing_months'], true);
            $rows[$month->format('Y-m')] = ['month' => $month->format('Y-m'), 'count' => $unknown ? null : 0, 'revenue' => $unknown ? null : 0.0];
        }
        foreach ($this->appointments($service)->where('status', 'completed')->whereBetween('completed_at', [$start, $end])->with('payments')->lazyById(250) as $appointment) {
            $key = $appointment->completed_at->format('Y-m');
            if (isset($rows[$key]) && $rows[$key]['count'] !== null) {
                $rows[$key]['count']++;
                $rows[$key]['revenue'] += $appointment->netPaidCents() / 100;
            }
        }

        return array_values($rows);
    }

    /** @return array<string, int|float> */
    public function totals(string $from, string $to): array
    {
        $appointments = $this->appointments()->whereDate('completed_at', '>=', $from)->whereDate('completed_at', '<=', $to)->where('status', 'completed')->with('payments')->get();
        $payments = Payment::query()->whereHas('appointment', fn (Builder $q) => $q->where('source', OfficeSettings::source()))
            ->whereDate('paid_at', '>=', $from)->whereDate('paid_at', '<=', $to)->get(['kind', 'amount_cents']);
        $outstanding = 0;
        foreach ($this->appointments()->whereIn('status', ['pending', 'confirmed', 'completed'])->with('payments')->lazyById(250) as $appointment) {
            $outstanding += $appointment->balanceCents();
        }

        return [
            'completed' => $appointments->count(),
            'completed_revenue' => $appointments->sum(fn (Appointment $appointment) => $appointment->netPaidCents()) / 100,
            'cash_collected' => $payments->where('kind', 'payment')->sum('amount_cents') / 100,
            'refunds' => $payments->where('kind', 'refund')->sum('amount_cents') / 100,
            'outstanding' => $outstanding / 100,
        ];
    }
}
