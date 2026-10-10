<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServiceRecordRequest;
use App\Models\AuditLog;
use App\Models\ServiceRecord;
use App\Models\ServiceType;
use App\Support\OfficeAudit;
use App\Support\OfficeSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AdminRecordController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('records.view');

        $services = ServiceType::query()->orderBy('name')->get();
        $groupedRecords = $this->visibleRecords()->whereNull('archived_at')
            ->selectRaw('service_type, register_book, COUNT(*) as record_count')
            ->groupBy('service_type', 'register_book')
            ->get()
            ->groupBy('service_type');
        $serviceSummaries = $services->mapWithKeys(function (ServiceType $service) use ($groupedRecords): array {
            $groups = $groupedRecords->get($service->slug, collect());
            $bookNames = $groups->map(fn (ServiceRecord $record): string => trim((string) $record->register_book))
                ->map(fn (string $book): string => $book === '' ? '__unassigned' : $book)
                ->unique();

            return [$service->slug => [
                'record_count' => (int) $groups->sum('record_count'),
                'book_count' => $bookNames->count(),
            ]];
        });

        return view('admin.records.index', compact('services', 'serviceSummaries'));
    }

    public function all(Request $request): View
    {
        Gate::authorize('records.view');
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:255'], 'service' => ['nullable', 'string', 'max:30'], 'archived' => ['nullable', 'boolean']]);
        $records = $this->visibleRecords()
            ->when(! $request->boolean('archived'), fn ($q) => $q->whereNull('archived_at'))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($inner) => $inner->where('record_number', 'like', '%'.$term.'%')->orWhere('participant_names', 'like', '%'.$term.'%')))
            ->when($filters['service'] ?? null, fn ($q, $service) => $q->where('service_type', $service))
            ->orderByDesc('service_date')->orderByDesc('id')->paginate(25)->withQueryString();

        return view('admin.records.all', ['records' => $records, 'services' => ServiceType::query()->orderBy('name')->get()]);
    }

    public function service(Request $request, ServiceType $serviceType): View
    {
        Gate::authorize('records.view');
        $request->validate(['archived' => ['nullable', 'boolean']]);

        $groups = $this->visibleRecords()->where('service_type', $serviceType->slug)
            ->when(! $request->boolean('archived'), fn ($query) => $query->whereNull('archived_at'))
            ->selectRaw('register_book, COUNT(*) as record_count, MAX(service_date) as latest_service_date')
            ->groupBy('register_book')
            ->get()
            ->groupBy(fn (ServiceRecord $record): string => trim((string) $record->register_book) ?: '__unassigned')
            ->map(function ($records, string $key): array {
                return [
                    'name' => $key === '__unassigned' ? null : $key,
                    'record_count' => (int) $records->sum('record_count'),
                    'latest_service_date' => $records->max('latest_service_date'),
                ];
            })
            ->sortBy(fn (array $book): string => $book['name'] === null ? 'zzzz' : mb_strtolower($book['name']))
            ->values();

        return view('admin.records.service', compact('serviceType', 'groups'));
    }

    public function book(Request $request, ServiceType $serviceType): View
    {
        Gate::authorize('records.view');
        $filters = $request->validate([
            'book' => ['nullable', 'string', 'max:100'],
            'unassigned' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:255'],
            'archived' => ['nullable', 'boolean'],
        ]);
        $isUnassigned = $request->boolean('unassigned');
        $book = isset($filters['book']) ? trim($filters['book']) : null;
        abort_if(($isUnassigned && filled($book)) || (! $isUnassigned && blank($book)), 404);

        $records = $this->visibleRecords()->where('service_type', $serviceType->slug)
            ->when($isUnassigned, fn ($query) => $query->where(fn ($inner) => $inner->whereNull('register_book')->orWhere('register_book', '')))
            ->when(! $isUnassigned, fn ($query) => $query->where('register_book', $book))
            ->when(! $request->boolean('archived'), fn ($query) => $query->whereNull('archived_at'))
            ->when($filters['q'] ?? null, fn ($query, $term) => $query->where(fn ($inner) => $inner->where('record_number', 'like', '%'.$term.'%')->orWhere('participant_names', 'like', '%'.$term.'%')))
            ->orderByDesc('service_date')->orderByDesc('id')->paginate(25)->withQueryString();

        return view('admin.records.book', ['serviceType' => $serviceType, 'book' => $book, 'isUnassigned' => $isUnassigned, 'records' => $records]);
    }

    public function create(): View
    {
        Gate::authorize('records.manage');

        return view('admin.records.form', ['record' => new ServiceRecord, 'services' => ServiceType::query()->orderBy('name')->get(), 'history' => collect()]);
    }

    public function show(ServiceRecord $record): View
    {
        Gate::authorize('records.view');
        abort_unless(in_array($record->source, ['archival', OfficeSettings::source()], true), 404);

        return view('admin.records.form', [
            'record' => $record, 'services' => ServiceType::query()->orderBy('name')->get(),
            'history' => AuditLog::query()->with('actor')->where('entity_type', ServiceRecord::class)->where('entity_id', $record->id)->latest('id')->limit(50)->get(),
        ]);
    }

    public function store(StoreServiceRecordRequest $request): RedirectResponse
    {
        $record = DB::transaction(function () use ($request): ServiceRecord {
            $data = $request->safe()->except(['reason', 'version', 'details_text']);
            $record = ServiceRecord::create([...$data, 'source' => 'archival', 'recorded_by' => auth()->id(), 'details' => ['transcription' => $request->validated('details_text')]]);
            OfficeAudit::record('record.created', $record, [], $record->toArray(), $request->validated('reason'));

            return $record;
        });

        return redirect()->route('admin.records.show', $record)->with('status', 'Historical record saved separately from current appointment operations.');
    }

    public function update(StoreServiceRecordRequest $request, ServiceRecord $record): RedirectResponse
    {
        abort_unless(in_array($record->source, ['archival', OfficeSettings::source()], true), 404);
        DB::transaction(function () use ($request, $record): void {
            $locked = ServiceRecord::query()->lockForUpdate()->findOrFail($record->id);
            abort_unless($locked->version === (int) $request->validated('version'), 409, 'This record has changed. Refresh before saving.');
            $before = $locked->toArray();
            $locked->fill($request->safe()->except(['reason', 'version', 'details_text']));
            $locked->details = [...($locked->details ?? []), 'transcription' => $request->validated('details_text')];
            $locked->version++;
            $locked->save();
            OfficeAudit::record('record.corrected', $locked, $before, $locked->toArray(), $request->validated('reason'));
        });

        return back()->with('status', 'Correction saved with its history. The linked appointment remains unchanged.');
    }

    public function destroy(Request $request, ServiceRecord $record): RedirectResponse
    {
        Gate::authorize('records.manage');
        abort_unless(in_array($record->source, ['archival', OfficeSettings::source()], true), 404);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000'], 'archive' => ['required', 'boolean'], 'version' => ['required', 'integer']]);
        DB::transaction(function () use ($record, $data): void {
            $locked = ServiceRecord::query()->lockForUpdate()->findOrFail($record->id);
            abort_unless($locked->version === (int) $data['version'], 409);
            $before = $locked->only('archived_at');
            $locked->archived_at = $data['archive'] ? now() : null;
            $locked->version++;
            $locked->save();
            OfficeAudit::record('record.archive_changed', $locked, $before, $locked->only('archived_at'), $data['reason']);
        });

        return back()->with('status', 'Archive status updated. The record remains preserved.');
    }

    private function visibleRecords(): Builder
    {
        return ServiceRecord::query()->whereIn('source', ['archival', OfficeSettings::source()]);
    }
}
