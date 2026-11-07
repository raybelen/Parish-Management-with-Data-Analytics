<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\ServiceType;
use App\Support\CsvExport;
use App\Support\OfficeAccess;
use App\Support\OfficeAudit;
use App\Support\OfficeSettings;
use App\Support\ParishRevenue;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminRevenueController extends Controller
{
    public function index(Request $request, ParishRevenue $revenue): View|StreamedResponse
    {
        Gate::authorize('finance.view');
        $request->merge([
            'from' => $request->input('from') ?: now()->startOfMonth()->toDateString(),
            'to' => $request->input('to') ?: now()->toDateString(),
        ]);
        $filters = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'service' => ['nullable', 'string', Rule::exists('service_types', 'slug')],
            'method' => ['nullable', Rule::in(['cash', 'bank_transfer', 'gcash', 'check', 'other', 'xendit_sandbox'])],
            'export' => ['nullable', Rule::in(['collections', 'outstanding'])],
        ]);
        $payments = $revenue->payments($request->user(), $filters);
        $outstanding = $revenue->outstanding($request->user(), $filters['service'] ?? null);
        if ($request->filled('export')) {
            Gate::authorize('reports.view');
            $type = $filters['export'];
            $dataset = OfficeSettings::demo() ? 'DEMO - simulated data' : 'Demonstration collections - includes sandbox payments';
            if ($type === 'outstanding') {
                $columns = ['dataset', 'appointment', 'client', 'service', 'fee', 'outstanding'];
                $rows = $outstanding->orderBy('appointments.id')->lazy()->map(fn ($appointment): array => [
                    $dataset, $appointment->reference_number, $appointment->client_full_name, $appointment->service_type,
                    number_format($appointment->fee_cents / 100, 2, '.', ''), number_format($appointment->outstanding_cents / 100, 2, '.', ''),
                ]);
            } else {
                $columns = ['dataset', 'date', 'appointment', 'service', 'entry', 'amount', 'method', 'reference', 'recorded_by', 'environment', 'status', 'channel', 'gateway_session'];
                $rows = $payments->with(['appointment', 'recordedBy', 'originalPayment', 'transaction'])->orderBy('paid_at')->orderBy('id')->lazy()->map(fn (Payment $payment): array => [
                    $dataset, $payment->paid_at->toDateTimeString(), $payment->appointment->reference_number, $payment->appointment->service_type,
                    $payment->kind, number_format($payment->amount_cents / 100, 2, '.', ''), $payment->method, $payment->reference, $payment->recordedBy?->name ?? 'System / legacy entry',
                    $payment->isSandbox() ? 'sandbox' : 'walk-in', $payment->kind === 'refund' ? 'refund recorded' : 'paid', $payment->transaction?->payment_method, $payment->transaction?->provider_session_id,
                ]);
            }
            OfficeAudit::record('revenue.exported', null, [], [...$filters, 'source' => OfficeSettings::source()]);

            return CsvExport::download((OfficeSettings::demo() ? 'DEMO-' : '').'parish-'.$type.'-'.$filters['from'].'-'.$filters['to'].'.csv', $columns, $rows);
        }

        $byService = (clone $payments)->join('appointments', 'appointments.id', '=', 'payments.appointment_id')
            ->select('appointments.service_type')
            ->selectRaw("SUM(CASE WHEN payments.kind = 'refund' THEN -payments.amount_cents ELSE payments.amount_cents END) AS net_cents")
            ->groupBy('appointments.service_type')->orderBy('appointments.service_type')->get();
        $outstandingTotal = (clone $outstanding)->toBase()->sum(DB::raw('appointments.fee_cents - COALESCE(collections.net_paid_cents, 0)'));
        $transactions = PaymentTransaction::query()->whereIn('appointment_id', OfficeAccess::appointments($request->user())
            ->when($filters['service'] ?? null, fn (Builder $query, string $service): Builder => $query->where('service_type', $service))->select('appointments.id'))
            ->whereDate('created_at', '>=', $filters['from'])->whereDate('created_at', '<=', $filters['to'])
            ->when(filled($filters['method'] ?? null) && $filters['method'] !== 'xendit_sandbox', fn (Builder $query): Builder => $query->whereRaw('1 = 0'));

        return view('admin.revenue', [
            'filters' => $filters,
            'totals' => $revenue->totals($payments),
            'sandboxTotals' => $revenue->totals($revenue->payments($request->user(), $filters, true)),
            'outstandingTotal' => (int) $outstandingTotal,
            'byService' => $byService,
            'services' => ServiceType::query()->orderBy('name')->pluck('name', 'slug'),
            'payments' => $payments->with(['appointment', 'recordedBy', 'originalPayment', 'transaction'])->orderByDesc('paid_at')->orderByDesc('id')->paginate(20)->withQueryString(),
            'transactions' => $transactions->with('appointment')->orderByDesc('created_at')->orderByDesc('id')->paginate(10, ['*'], 'transactions_page')->withQueryString(),
            'outstanding' => $outstanding->orderByDesc('outstanding_cents')->orderBy('appointments.id')->paginate(10, ['*'], 'balances_page')->withQueryString(),
        ]);
    }
}
