@extends('admin.layout')
@section('title', $appointment->reference_number)
@section('actions')<div class="flex items-center gap-3"><x-admin.status :value="$appointment->status" /><a class="admin-secondary" href="{{ route('admin.appointments.index') }}">All appointments</a></div>@endsection
@section('content')
    @can('appointments.manage')@if(in_array($appointment->status, ['pending', 'confirmed']))<a class="admin-secondary justify-self-start" href="{{ route('admin.appointments.edit', $appointment) }}">Correct contact & participant details</a>@endif
    @endcan
    <div class="grid gap-6 xl:grid-cols-2">
        <section class="admin-card"><h2 class="mb-4 text-2xl">Booking contact</h2><dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-3 text-sm"><dt class="text-muted">Name</dt><dd>{{ $appointment->client_full_name }}</dd><dt class="text-muted">Contact</dt><dd>{{ $appointment->client_contact_number }}<br>{{ $appointment->client_email }}</dd><dt class="text-muted">Address</dt><dd>{{ $appointment->client_address }}</dd><dt class="text-muted">Requested</dt><dd>{{ $appointment->preferred_date->format('M j, Y') }} · {{ substr($appointment->preferred_time, 0, 5) }}<br>{{ $appointment->preferred_church }}</dd><dt class="text-muted">Notes</dt><dd class="whitespace-pre-line">{{ $appointment->additional_notes ?: 'None' }}</dd></dl></section>
        <section class="admin-card"><h2 class="mb-4 text-2xl">{{ $service->name }} participants</h2><div class="grid gap-3">@forelse($appointment->participants as $participant)<p class="text-sm"><span class="text-muted">{{ ucfirst($participant->role) }}:</span> {{ $participant->full_name }} @if($participant->date_of_birth)<span class="text-muted">· {{ $participant->date_of_birth->format('M j, Y') }}</span>@endif</p>@empty<p class="text-sm text-muted">Participant information is preserved in the full appointment details.</p>@endforelse</div><button type="button" class="admin-secondary mt-5" data-dialog-target="appointment-service-details">View full appointment details</button></section>
    </div>
    <x-parish.dialog id="appointment-service-details" :title="$service->name.' appointment details'" class="backdrop:bg-navy/60">
        <dl class="grid gap-5 text-sm sm:grid-cols-2">
            @if ($appointment->service_type === 'funeral')
                <div>
                    <dt class="text-xs font-semibold tracking-[0.08em] text-muted uppercase">Year of death</dt>
                    <dd class="mt-1 text-navy">{{ substr($appointment->service_details['date_of_death'] ?? '', 0, 4) ?: 'Not provided' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold tracking-[0.08em] text-muted uppercase">Date of burial / funeral Mass</dt>
                    <dd class="mt-1 text-navy">{{ ($appointment->starts_at ?? $appointment->preferred_date)->format('F j, Y') }}</dd>
                </div>
            @endif
            @foreach($appointment->service_details as $key => $value)
                @if(is_scalar($value) && $value !== '')
                    <div class="border-b border-navy/10 pb-4">
                        <dt class="text-xs font-semibold tracking-[0.08em] text-muted uppercase">{{ config("appointments.details.{$appointment->service_type}.{$key}.label", \Illuminate\Support\Str::headline($key)) }}</dt>
                        <dd class="mt-1 whitespace-pre-line text-navy">{{ config("appointments.details.{$appointment->service_type}.{$key}.options.{$value}", $value) }}</dd>
                    </div>
                @endif
            @endforeach
        </dl>
        <div class="mt-7 flex justify-end"><button type="button" class="admin-secondary" data-close-dialog>Close</button></div>
    </x-parish.dialog>
    <section class="admin-card"><h2 class="mb-4 text-2xl">Schedule & preparation</h2>
        @if($appointment->starts_at)<p class="mb-5 text-sm">{{ $appointment->starts_at->format('M j, Y · g:i A') }} – {{ $appointment->ends_at->format('g:i A') }} · {{ $appointment->confirmed_church }} · {{ $appointment->personnel?->name }}<br><strong>Preparation time reserved until {{ $appointment->reserved_until->format('g:i A') }} ({{ $appointment->buffer_minutes }} minutes).</strong></p>@endif
        @can('appointments.manage')@if(in_array($appointment->status, ['pending', 'confirmed']))
            <form method="POST" action="{{ route('admin.appointments.update', $appointment) }}" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">@csrf @method('PUT')<input type="hidden" name="version" value="{{ $appointment->version }}">
                <x-admin.field label="Reserved start" name="starts_at" type="datetime-local" :value="$appointment->starts_at?->format('Y-m-d\TH:i') ?? $appointment->preferred_date->format('Y-m-d').'T'.substr($appointment->preferred_time, 0, 5)" help="Pre-filled from the requested date and time." required />
                <x-admin.field label="Service duration (minutes)" name="duration_minutes" type="number" min="60" max="60" :value="60" help="All parish services use a one-hour schedule." readonly required />
                <x-admin.field label="Venue turnaround gap after service (minutes)" name="buffer_minutes" type="number" :min="$service->buffer_minutes" max="240" :value="max($appointment->buffer_minutes, $service->buffer_minutes)" help="Keeps the venue unavailable after the service while attendees leave and preparations or decorations are cleared." required />
                @php($selectedChurch = old('confirmed_church', $appointment->confirmed_church ?? $appointment->preferred_church))
                <x-admin.field label="Venue" name="confirmed_church"><select name="confirmed_church" class="admin-input" required><option value="">Choose an available venue</option>@if($selectedChurch && !in_array($selectedChurch, $churches, true))<option value="" selected disabled>{{ $selectedChurch }} (currently unavailable)</option>@endif @foreach($churches as $church)<option value="{{ $church }}" @selected($selectedChurch === $church)>{{ $church }}</option>@endforeach</select></x-admin.field>
                <x-admin.field label="Assigned priest / personnel" name="personnel_id"><select name="personnel_id" class="admin-input" required><option value="">Choose personnel</option>@foreach($personnel as $person)<option value="{{ $person->id }}" @selected((string)old('personnel_id', $appointment->personnel_id) === (string)$person->id)>{{ $person->name }}</option>@endforeach</select></x-admin.field>
                <x-admin.field label="Reason for schedule / change" name="reason" required />
                <x-admin.field label="Office notes" name="office_notes" :value="$appointment->office_notes" />
                <button class="admin-button self-end">Save schedule</button>
            </form>
        @endif
@endcan
    </section>
    @can('documents.manage')<section class="admin-card"><h2 class="mb-2 text-2xl">Document verification</h2><p class="mb-5 text-sm text-muted">Online copies are optional. Verify the required physical copies received at the church office before confirmation.</p><div class="grid gap-5">
        @foreach($appointment->documentRequirements() as $key => $requirement)<div class="rounded-lg border border-navy/10 p-4"><div class="flex flex-wrap justify-between gap-3"><strong class="text-sm">{{ $requirement['label'] }} <span class="font-normal text-muted">{{ $requirement['required'] ? '· physical copy required' : '· optional' }}</span></strong><span class="text-sm">{{ ($appointment->physical_documents[$key]['verified'] ?? false) ? 'Physical copy verified' : 'Physical copy not verified' }}</span></div>
            <div class="my-3 flex flex-wrap gap-3">@forelse($appointment->documents[$key] ?? [] as $index => $file)<a class="text-link break-all" href="{{ route('admin.documents.show', [$appointment, $key, $index]) }}">{{ $file['original_name'] }}</a>@empty<p class="text-xs text-muted">No online upload</p>@endforelse</div>
            @if(in_array($appointment->status, ['pending', 'confirmed']))<form class="grid items-end gap-3 sm:grid-cols-4" method="POST" action="{{ route('admin.documents.update', $appointment) }}">@csrf @method('PATCH')<input type="hidden" name="version" value="{{ $appointment->version }}"><input type="hidden" name="type" value="{{ $key }}">
                <x-admin.field label="Copy to verify" name="channel"><select class="admin-input" name="channel"><option value="physical">Physical copy</option>@if(!empty($appointment->documents[$key]))<option value="online">Online upload</option>@endif</select></x-admin.field>
                <x-admin.field label="Verification" name="verified"><select class="admin-input" name="verified"><option value="1">Verified</option><option value="0">Needs correction</option></select></x-admin.field>
                <x-admin.field label="Verification note" name="reason" required /><button class="admin-secondary">Save verification</button>
            </form>@endif
        </div>@endforeach
    </div>
        @if(in_array($appointment->status, ['pending', 'confirmed']) && count($appointment->documentRequirements()))
            <details class="mt-6 rounded-lg border border-navy/10 p-4"><summary class="cursor-pointer text-sm font-semibold">Attach an optional document copy</summary>
                <form method="POST" action="{{ route('admin.documents.store', $appointment) }}" enctype="multipart/form-data" class="mt-4 grid items-end gap-4 sm:grid-cols-2">@csrf
                    <input type="hidden" name="version" value="{{ $appointment->version }}">
                    <x-admin.field label="Requirement" name="type"><select class="admin-input" name="type">@foreach($appointment->documentRequirements() as $key => $requirement)<option value="{{ $key }}">{{ $requirement['label'] }}</option>@endforeach</select></x-admin.field>
                    <x-admin.field label="Copies (PDF, JPG or PNG; up to 5 MB each)" name="files[]" type="file" accept=".pdf,.jpg,.jpeg,.png" multiple required />
                    <x-admin.field label="Upload note" name="reason" required /><button class="admin-secondary">Upload private copies</button>
                </form>
            </details>
        @endif
    </section>@endcan
    @canany(['appointments.manage', 'appointments.approve'])@if(in_array($appointment->status, ['pending', 'confirmed']))<section class="admin-card"><h2 class="mb-4 text-2xl">Process appointment</h2><form class="grid items-end gap-4 sm:grid-cols-2" method="POST" action="{{ route('admin.appointments.status', $appointment) }}" data-confirm="Save this status change? Completed records and final outcomes will be preserved.">@csrf<input type="hidden" name="version" value="{{ $appointment->version }}">
        <x-admin.field label="Next status" name="status"><select class="admin-input" name="status">@foreach(($appointment->status === 'pending' ? ['confirmed', 'declined', 'cancelled'] : ['completed', 'cancelled', 'no_show']) as $status)@if(auth()->user()->can('appointments.manage') || in_array($status, ['confirmed', 'declined']))<option value="{{ $status }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</option>@endif
@endforeach</select></x-admin.field>
        <x-admin.field label="Actual completion time (when completing)" name="completed_at" type="datetime-local" :value="now()->format('Y-m-d\TH:i')" />
        <x-admin.field label="Outcome / reason" name="reason" required /><button class="admin-button">Save status</button>
    </form></section>@endif
@endcanany
    @if($appointment->serviceRecord)@can('records.view')<p class="admin-card text-sm">Permanent record created: <a class="underline" href="{{ route('admin.records.show', $appointment->serviceRecord) }}">{{ $appointment->serviceRecord->record_number }}</a> · {{ $appointment->completed_at?->format('M j, Y g:i A') }}</p>@endcan
@endif
    @can('finance.view')@include('admin.appointments.payments')@endcan
    <details class="admin-card"><summary class="cursor-pointer text-lg font-semibold">Appointment history</summary><div class="mt-4 grid gap-4">@forelse($history as $entry)<div class="border-b border-navy/10 pb-3 text-sm"><p><strong>{{ \Illuminate\Support\Str::headline($entry->action) }}</strong> · {{ $entry->created_at->format('M j, Y g:i A') }}</p><p class="text-muted">{{ $entry->actor?->name ?? 'Client / system' }} · {{ $entry->reason }}</p></div>@empty<p class="text-sm text-muted">No recorded changes.</p>@endforelse</div></details>
@endsection
