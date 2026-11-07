<?php

namespace App\Http\Controllers;

use App\Actions\CreateAppointment;
use App\Actions\ManageAppointment;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateOfficeAppointmentRequest;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Personnel;
use App\Models\ScheduleBlock;
use App\Models\ServiceType;
use App\Support\OfficeAccess;
use App\Support\OfficeSettings;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AdminAppointmentController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('appointments.view');
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', Rule::in(Appointment::STATUSES)],
            'service' => ['nullable', 'string', 'max:30'], 'date' => ['nullable', 'date_format:Y-m-d'],
            'view' => ['nullable', Rule::in(['list', 'calendar'])],
            'month' => ['nullable', 'date_format:Y-m'],
        ]);
        $query = OfficeAccess::appointments($request->user())->with('personnel')
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($inner) => $inner->where('reference_number', 'like', '%'.$term.'%')->orWhere('client_full_name', 'like', '%'.$term.'%')->orWhereHas('participants', fn ($p) => $p->where('full_name', 'like', '%'.$term.'%'))))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['service'] ?? null, fn ($q, $service) => $q->where('service_type', $service))
            ->when($filters['date'] ?? null, fn ($q, $date) => $q->where(fn ($inner) => $inner->whereDate('starts_at', $date)->orWhere(fn ($pending) => $pending->whereNull('starts_at')->whereDate('preferred_date', $date))));

        $month = CarbonImmutable::parse(($filters['month'] ?? now()->format('Y-m')).'-01');
        if ($request->input('view') === 'calendar') {
            $query->whereIn('status', ['pending', 'confirmed'])->whereBetween('starts_at', [$month, $month->endOfMonth()]);
        }
        $calendarAppointments = $request->input('view') === 'calendar'
            ? (clone $query)->orderBy('starts_at')->get()->groupBy(fn ($appointment) => $appointment->starts_at->toDateString()) : collect();

        return view('admin.appointments.index', [
            'month' => $month, 'calendarAppointments' => $calendarAppointments,
            'appointments' => ($request->input('view') === 'calendar' ? $query->orderBy('starts_at') : $query->latest())->orderBy('id')->paginate(25)->withQueryString(),
            'services' => ServiceType::query()->orderBy('name')->get(),
            'personnel' => $request->user()->can('settings.manage') ? Personnel::query()->where('active', true)->orderBy('name')->get() : collect(),
            'blocks' => ScheduleBlock::query()->with('personnel')->where('ends_at', '>=', now())->orderBy('starts_at')->limit(30)->get(),
            'churches' => OfficeSettings::activeVenues(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('appointments.manage');

        return view('admin.appointments.create', [
            'services' => ServiceType::query()->where('active', true)->orderBy('name')->get(),
            'churches' => OfficeSettings::activeVenues(),
        ]);
    }

    public function store(StoreAppointmentRequest $request, CreateAppointment $create): RedirectResponse
    {
        Gate::authorize('appointments.manage');
        $appointment = $create->handle($request->validated());

        return redirect()->route('admin.appointments.show', $appointment)->with('status', 'Appointment reserved for three days while its physical documents are verified. The service fee can be paid separately and does not block confirmation.');
    }

    public function show(Request $request, Appointment $appointment): View
    {
        Gate::authorize('appointments.view');
        OfficeAccess::appointment($request->user(), $appointment);
        $appointment->load(['participants', 'personnel', 'serviceRecord']);
        if ($request->user()->can('finance.view')) {
            $appointment->load(['payments.originalPayment', 'paymentTransactions' => fn ($query) => $query->latest('id')]);
        }

        return view('admin.appointments.show', [
            'appointment' => $appointment,
            'service' => ServiceType::query()->where('slug', $appointment->service_type)->firstOrFail(),
            'personnel' => Personnel::query()->where('active', true)->whereNotNull('rotation_position')->orderBy('rotation_position')->get(),
            'churches' => OfficeSettings::activeVenues(),
            'history' => AuditLog::query()->with('actor')->where('entity_type', Appointment::class)->where('entity_id', $appointment->id)
                ->when(! $request->user()->can('finance.view'), fn ($query) => $query->where('action', '!=', 'appointment.fee_changed'))->latest('id')->limit(50)->get(),
        ]);
    }

    public function update(UpdateOfficeAppointmentRequest $request, Appointment $appointment, ManageAppointment $manage): RedirectResponse
    {
        OfficeAccess::appointment($request->user(), $appointment);
        $manage->assignPersonnel($appointment, $request->validated());

        return back()->with('status', 'Assigned priest or staff member saved.');
    }
}
