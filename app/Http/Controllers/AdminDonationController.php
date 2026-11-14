<?php

namespace App\Http\Controllers;

use App\Actions\ManageDonation;
use App\Http\Requests\StoreDonationRequest;
use App\Models\Donation;
use App\Support\CsvExport;
use App\Support\OfficeAudit;
use App\Support\OfficeSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminDonationController extends Controller
{
    public function index(Request $request): View|StreamedResponse
    {
        Gate::authorize('donations.view');
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('from'), 'after_or_equal:from')],
            'category' => ['nullable', Rule::in(array_keys(Donation::CATEGORIES))],
            'method' => ['nullable', Rule::in(array_keys(Donation::METHODS))],
            'status' => ['nullable', Rule::in(['recorded', 'voided'])],
            'review' => ['nullable', Rule::in(['pending'])],
            'kind' => ['nullable', Rule::in(['individual', 'mass_collection'])],
            'church' => ['nullable', 'string', Rule::in(config('parish.churches'))],
            'collection_type' => ['nullable', Rule::in(array_keys(Donation::COLLECTION_TYPES))],
            'export' => ['nullable', Rule::in(['records', 'category', 'method', 'monthly', 'church', 'masses'])],
        ]);
        $query = Donation::query()->where('source', OfficeSettings::source())
            ->when(filled($filters['q'] ?? null), function (Builder $query) use ($filters): void {
                $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($filters['q'])).'%';
                $query->where(function (Builder $query) use ($term): void {
                    foreach (['donor_name', 'donor_email', 'donor_phone', 'receipt_number', 'reference'] as $column) {
                        $query->orWhereRaw("LOWER({$column}) LIKE ? ESCAPE '!'", [$term]);
                    }
                });
            })
            ->when($filters['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('donated_on', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('donated_on', '<=', $date))
            ->when($filters['category'] ?? null, fn (Builder $query, string $category): Builder => $query->where('category', $category))
            ->when($filters['method'] ?? null, fn (Builder $query, string $method): Builder => $query->where('method', $method))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->when($filters['kind'] ?? null, fn (Builder $query, string $kind): Builder => $query->where('kind', $kind))
            ->when($filters['church'] ?? null, fn (Builder $query, string $church): Builder => $query->where(fn (Builder $query): Builder => $query->where('church', $church)->orWhereHas('massCollection', fn (Builder $parent): Builder => $parent->where('church', $church))))
            ->when($filters['collection_type'] ?? null, fn (Builder $query, string $type): Builder => $query->where(fn (Builder $query): Builder => $query->where('collection_type', $type)->orWhereHas('massCollection', fn (Builder $parent): Builder => $parent->where('collection_type', $type))))
            ->when($filters['review'] ?? null, fn (Builder $query): Builder => $query->whereHas('adjustments', fn (Builder $query): Builder => $query->where('status', 'pending')));
        $totals = (clone $query)->toBase()->selectRaw("COUNT(*) AS records, COALESCE(SUM(CASE WHEN status = 'recorded' AND mass_collection_id IS NULL THEN amount_cents ELSE 0 END), 0) AS received_cents, COUNT(CASE WHEN status = 'recorded' AND mass_collection_id IS NULL THEN 1 END) AS received_count, COUNT(CASE WHEN status = 'voided' THEN 1 END) AS voided_count, COUNT(CASE WHEN status = 'recorded' AND kind = 'mass_collection' THEN 1 END) AS mass_count, COALESCE(SUM(CASE WHEN status = 'recorded' AND mass_collection_id IS NOT NULL THEN amount_cents ELSE 0 END), 0) AS included_cents")->first();
        $received = (clone $query)->where('status', 'recorded')->whereNull('mass_collection_id');
        $byCategory = (clone $received)->select('category')->selectRaw('COUNT(*) AS entries, SUM(amount_cents) AS total_cents')->groupBy('category')->orderBy('category')->get();
        $byMethod = (clone $received)->select('method')->selectRaw('COUNT(*) AS entries, SUM(amount_cents) AS total_cents')->groupBy('method')->orderBy('method')->get();
        $month = 'SUBSTR(CAST(donated_on AS CHAR(10)), 1, 7)';
        $monthly = (clone $received)->selectRaw($month.' AS month, COUNT(*) AS entries, SUM(amount_cents) AS total_cents')->groupByRaw($month)->orderBy('month')->get();
        $masses = (clone $received)->where('kind', 'mass_collection');
        $byChurch = (clone $masses)->select('church')->selectRaw('COUNT(*) AS entries, SUM(amount_cents) AS total_cents')->groupBy('church')->orderBy('church')->get();

        if ($request->filled('export')) {
            Gate::authorize('reports.view');
            $type = $filters['export'];
            $dataset = OfficeSettings::demo() ? 'Test donations' : 'Recorded donations';
            if ($type === 'records') {
                $columns = ['dataset', 'receipt', 'donor', 'email', 'phone', 'address', 'date', 'category', 'amount_php', 'method', 'reference', 'status', 'version', 'recorded_by', 'notes', 'kind', 'anonymous', 'included_in_collection', 'counted_received_php', 'church', 'mass_time', 'collection_type', 'collection_purpose', 'counted_by', 'verified_by'];
                $rows = (clone $query)->with(['recordedBy', 'massCollection'])->orderBy('donated_on')->orderBy('id')->lazy()->map(fn (Donation $donation): array => [
                    $dataset, $donation->receipt_number, $donation->donorLabel(), $donation->is_anonymous ? null : $donation->donor_email, $donation->is_anonymous ? null : $donation->donor_phone, $donation->is_anonymous ? null : $donation->donor_address,
                    $donation->donated_on->toDateString(), Donation::CATEGORIES[$donation->category], number_format($donation->amount_cents / 100, 2, '.', ''),
                    Donation::METHODS[$donation->method], $donation->reference, $donation->status, $donation->version, $donation->recordedBy?->name, $donation->notes,
                    $donation->kind, $donation->is_anonymous ? 'Yes' : 'No', $donation->massCollection?->receipt_number,
                    number_format($donation->status === 'recorded' && ! $donation->mass_collection_id ? $donation->amount_cents / 100 : 0, 2, '.', ''),
                    $donation->church ?? $donation->massCollection?->church, $donation->mass_time ?? $donation->massCollection?->mass_time,
                    $donation->collection_type ?? $donation->massCollection?->collection_type, $donation->collection_purpose ?? $donation->massCollection?->collection_purpose,
                    $donation->counted_by, $donation->verified_by,
                ]);
            } elseif ($type === 'masses') {
                $columns = ['dataset', 'receipt', 'date', 'mass_time', 'church', 'collection_type', 'purpose', 'method', 'received_php', 'counted_by', 'verified_by'];
                $rows = (clone $masses)->orderBy('donated_on')->orderBy('mass_time')->orderBy('id')->lazy()->map(fn (Donation $donation): array => [
                    $dataset, $donation->receipt_number, $donation->donated_on->toDateString(), $donation->mass_time, $donation->church,
                    Donation::COLLECTION_TYPES[$donation->collection_type], $donation->collection_purpose, Donation::METHODS[$donation->method],
                    number_format($donation->amount_cents / 100, 2, '.', ''), $donation->counted_by, $donation->verified_by,
                ]);
            } else {
                $group = $type === 'monthly' ? 'month' : $type;
                $columns = ['dataset', $group, 'donations', 'received_php'];
                $rows = (match ($type) {
                    'category' => $byCategory, 'method' => $byMethod, 'monthly' => $monthly, 'church' => $byChurch,
                })->map(fn ($row): array => [$dataset, match ($type) {
                    'category' => Donation::CATEGORIES[$row->category], 'method' => Donation::METHODS[$row->method], 'monthly' => $row->month, 'church' => $row->church,
                }, $row->entries, number_format($row->total_cents / 100, 2, '.', '')]);
            }
            OfficeAudit::record('donations.exported', null, [], ['source' => OfficeSettings::source(), ...$filters]);

            return CsvExport::download('donations-'.$type.'.csv', $columns, $rows);
        }

        return view('admin.donations.index', [
            'filters' => $filters, 'totals' => $totals, 'byCategory' => $byCategory, 'byMethod' => $byMethod, 'monthly' => $monthly, 'byChurch' => $byChurch,
            'masses' => (clone $masses)->orderByDesc('donated_on')->orderByDesc('mass_time')->orderByDesc('id')->paginate(10, ['*'], 'masses_page')->withQueryString(),
            'donations' => $query->with(['recordedBy', 'massCollection'])->withCount(['adjustments as pending_count' => fn (Builder $query): Builder => $query->where('status', 'pending')])
                ->orderByDesc('donated_on')->orderByDesc('id')->paginate(25)->withQueryString(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('donations.view');
        Gate::authorize('donations.manage');

        $data = $request->validate(['kind' => ['nullable', Rule::in(['individual', 'mass_collection'])], 'mass_collection_id' => ['nullable', 'integer', 'min:1']]);
        $collection = isset($data['mass_collection_id']) ? Donation::query()->where('source', OfficeSettings::source())->where('kind', 'mass_collection')->where('status', 'recorded')->findOrFail($data['mass_collection_id']) : null;
        $donation = new Donation(['kind' => $collection ? 'individual' : ($data['kind'] ?? 'individual'), 'mass_collection_id' => $collection?->id]);
        if ($collection) {
            $donation->fill($collection->only(['donated_on', 'method', 'category']));
        }

        return view('admin.donations.form', compact('donation', 'collection'));
    }

    public function store(StoreDonationRequest $request, ManageDonation $manage): RedirectResponse
    {
        $donation = $manage->record($request->user(), $request->validated());

        return redirect()->route('admin.donations.show', $donation)->with('status', 'Donation recorded. Your receipt is ready to print.');
    }

    public function show(Donation $donation): View
    {
        $this->authorizeDonation($donation);

        return view('admin.donations.show', [
            'donation' => $donation->load(['recordedBy', 'massCollection']),
            'itemized' => $donation->itemizedDonations()->orderByDesc('id')->paginate(10, ['*'], 'receipts_page')->withQueryString(),
            'allocatedCents' => (int) $donation->itemizedDonations()->where('status', 'recorded')->sum('amount_cents'),
            'adjustments' => $donation->adjustments()->with(['requestedBy', 'reviewedBy'])->latest('id')->paginate(10)->withQueryString(),
            'hasPending' => $donation->adjustments()->where('status', 'pending')->exists(),
        ]);
    }

    public function edit(Donation $donation): View
    {
        $this->authorizeDonation($donation);
        Gate::authorize('donations.manage');
        abort_unless($donation->status === 'recorded', 409, 'Voided donations cannot be changed.');
        abort_if($donation->adjustments()->where('status', 'pending')->exists(), 409, 'A request is already awaiting review.');

        return view('admin.donations.form', ['donation' => $donation, 'collection' => $donation->massCollection]);
    }

    public function update(StoreDonationRequest $request, Donation $donation, ManageDonation $manage): RedirectResponse
    {
        $this->authorizeDonation($donation);
        $manage->requestAdjustment($donation, $request->user(), 'correction', $request->validated());

        return redirect()->route('admin.donations.show', $donation)->with('status', 'Correction submitted for independent Super Admin review.');
    }

    public function receipt(Donation $donation): View
    {
        $this->authorizeDonation($donation);
        OfficeAudit::record('donation.receipt_viewed', $donation, [], ['version' => $donation->version]);

        return view('admin.donations.receipt', [
            'donation' => $donation->load(['recordedBy', 'massCollection']), 'parishName' => OfficeSettings::all()['parish_name'],
        ]);
    }

    private function authorizeDonation(Donation $donation): void
    {
        Gate::authorize('donations.view');
        abort_unless($donation->source === OfficeSettings::source(), 404);
    }
}
