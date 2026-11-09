@extends('admin.layout')
@section('title', $appointment->reference_number)
@section('actions')<div class="flex items-center gap-3"><span data-appointment-status-badge>@include('admin.appointments.partials.status-badge', ['status' => $appointment->status])</span><a class="admin-secondary" href="{{ route('admin.appointments.index') }}">All appointments</a></div>@endsection
@section('content')
    <section class="rounded-2xl border border-navy/10 bg-white p-5 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div><p class="text-sm text-muted">{{ $appointment->group_baptism_session_id ? 'Group Baptism' : $service->name }}</p><h2 class="mt-1 text-2xl">{{ $appointment->client_full_name }}</h2></div>
            <p class="text-sm text-muted">{{ ($appointment->starts_at ?? $appointment->preferred_date)->format('F j, Y') }}<br>{{ $appointment->confirmed_church ?? $appointment->preferred_church }}</p>
        </div>
        <p class="mt-4 text-sm leading-6 text-muted" data-appointment-status-summary>{{ match ($appointment->status) {
            'pending' => 'The slot is reserved for three days and visible to staff while the physical documents are verified. It is not yet on the public calendar.',
            'confirmed' => 'All required physical documents are verified. The appointment is confirmed and visible on both parish calendars.',
            'completed' => 'This service is complete. You can review its details and history below.',
            default => 'This appointment is closed. Its details and history are kept for your records.',
        } }}</p>
        <nav aria-label="Appointment sections" class="mt-5 flex flex-wrap gap-2">
            <a class="admin-secondary" href="#appointment-contact">Contact & details</a>
            <a class="admin-secondary" href="#appointment-schedule">Schedule</a>
            @can('documents.manage')<a class="admin-secondary" href="#appointment-documents">Documents</a>@endcan
            @can('finance.view')<a class="admin-secondary" href="#appointment-payments">Payments</a>@endcan
            @can('appointments.manage')@if(in_array($appointment->status, ['pending', 'confirmed']))<a class="admin-secondary" href="#appointment-status" data-appointment-status-link>Update status</a>@endif @endcan
            <a class="admin-secondary" href="#appointment-history">History</a>
        </nav>
    </section>
    <div class="grid gap-6 xl:grid-cols-2">
        <section id="appointment-contact" class="admin-card scroll-mt-28"><h2 class="mb-4 text-2xl">Contact details</h2><dl class="grid sm:grid-cols-[auto_minmax(0,1fr)] gap-x-6 gap-y-3 text-sm"><dt class="text-muted">Name</dt><dd class="break-words">{{ $appointment->client_full_name }}</dd><dt class="text-muted">Contact</dt><dd class="break-words">{{ $appointment->client_contact_number }}<br>{{ $appointment->client_email }}</dd><dt class="text-muted">Address</dt><dd class="break-words">{{ $appointment->client_address }}</dd><dt class="text-muted">Appointment created</dt><dd>{{ $appointment->created_at->timezone(config('office.timezone'))->format('F j, Y · g:i A') }}</dd><dt class="text-muted">Requested</dt><dd class="break-words">{{ $appointment->preferred_date->format('M j, Y') }} · {{ substr($appointment->preferred_time, 0, 5) }}<br>{{ $appointment->preferred_church }}</dd><dt class="text-muted">Notes</dt><dd class="whitespace-pre-line">{{ $appointment->additional_notes ?: 'None' }}</dd></dl></section>
        <section class="admin-card"><h2 class="mb-4 text-2xl">{{ $service->name }} participants</h2><div class="grid gap-3">@forelse($appointment->participants as $participant)<p class="text-sm"><span class="text-muted">{{ ucfirst($participant->role) }}:</span> {{ $participant->full_name }} @if($participant->date_of_birth)<span class="text-muted">· {{ $participant->date_of_birth->format('M j, Y') }}</span>@endif</p>@empty<p class="text-sm text-muted">Participant information is preserved in the full appointment details.</p>@endforelse</div><button type="button" class="admin-secondary mt-5" data-dialog-target="appointment-service-details">View full appointment details</button></section>
    </div>
    <x-parish.dialog id="appointment-service-details" :title="$service->name.' appointment details'" class="backdrop:bg-navy/60" wide>
        @php
            $detailDefinitions = config("appointments.details.{$appointment->service_type}", []);
            $detailGroups = collect($appointment->service_details)
                ->filter(fn ($value) => is_scalar($value) && $value !== '')
                ->groupBy(fn ($value, $key) => $detailDefinitions[$key]['group'] ?? 'Other details', true);
        @endphp

        <div class="grid gap-5">
            @if ($appointment->service_type === 'funeral')
                <section aria-labelledby="funeral-service-dates" class="rounded-xl border border-navy/10 bg-white px-4 py-4 sm:px-5">
                    <h3 id="funeral-service-dates" class="text-xl leading-tight text-navy">Service dates</h3>
                    <dl class="mt-3 grid gap-x-8 sm:grid-cols-2">
                        <div class="border-t border-navy/10 py-3">
                            <dt class="text-xs font-medium text-muted">Year of death</dt>
                            <dd class="mt-1 text-sm font-medium text-navy">{{ substr($appointment->service_details['date_of_death'] ?? '', 0, 4) ?: 'Not provided' }}</dd>
                        </div>
                        <div class="border-t border-navy/10 py-3">
                            <dt class="text-xs font-medium text-muted">Date of burial / funeral Mass</dt>
                            <dd class="mt-1 text-sm font-medium text-navy">{{ ($appointment->starts_at ?? $appointment->preferred_date)->format('F j, Y') }}</dd>
                        </div>
                    </dl>
                </section>
            @endif

            @foreach($detailGroups as $group => $details)
                <section aria-labelledby="service-detail-group-{{ $loop->index }}" class="rounded-xl border border-navy/10 bg-white px-4 py-4 sm:px-5">
                    <h3 id="service-detail-group-{{ $loop->index }}" class="text-xl leading-tight text-navy">{{ $group }}</h3>
                    <dl class="mt-3 grid gap-x-8 sm:grid-cols-2">
                        @foreach($details as $key => $value)
                            <div class="border-t border-navy/10 py-3">
                                <dt class="text-xs font-medium leading-5 text-muted">{{ $detailDefinitions[$key]['label'] ?? \Illuminate\Support\Str::headline($key) }}</dt>
                                <dd class="mt-1 whitespace-pre-line break-words text-sm font-medium leading-6 text-navy">{{ $detailDefinitions[$key]['options'][$value] ?? $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endforeach
        </div>
    </x-parish.dialog>
    <section id="appointment-schedule" class="admin-card scroll-mt-28"><h2 class="mb-4 text-2xl" data-appointment-schedule-heading>{{ $appointment->status === 'pending' ? 'Reserved schedule' : 'Confirmed schedule' }}</h2>
        @if($appointment->starts_at)
            <dl class="grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-4">
                <div><dt class="text-muted">Service</dt><dd class="mt-1 font-medium">{{ $service->name }}</dd></div>
                <div><dt class="text-muted">Date and time</dt><dd class="mt-1 font-medium">{{ $appointment->starts_at->format('M j, Y · g:i A') }} – {{ $appointment->ends_at->format('g:i A') }}</dd></div>
                <div><dt class="text-muted">Venue</dt><dd class="mt-1 font-medium">{{ $appointment->confirmed_church }}</dd></div>
                <div><dt class="text-muted">Assigned priest / staff</dt><dd class="mt-1 font-medium">{{ $appointment->personnel?->name ?? 'Not assigned yet' }}</dd></div>
            </dl>
            <p class="mt-5 text-sm text-muted">The client-selected service, date, time, and venue were reserved automatically after the availability check. Staff cannot change them here.</p>
            @if($appointment->status === 'pending' && $appointment->documents_due_at)
                <p class="mt-3 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900" data-pending-document-notice>Awaiting documents until {{ $appointment->documents_due_at->timezone(config('office.timezone'))->format('F j, Y \a\t g:i A') }}. The slot is shown only on staff calendars until verification is complete.</p>
            @endif
        @else
            <p class="text-sm text-muted">This legacy request does not have a confirmed schedule.</p>
        @endif
        @can('appointments.manage')@if(in_array($appointment->status, ['pending', 'confirmed']) && $appointment->starts_at)
            <form method="POST" action="{{ route('admin.appointments.update', $appointment) }}" class="mt-6 grid items-end gap-4 sm:grid-cols-[minmax(0,1fr)_auto]">@csrf @method('PUT')<input type="hidden" name="version" value="{{ $appointment->version }}">
                <x-admin.field label="Assign priest / staff" name="personnel_id"><select name="personnel_id" class="admin-input" required><option value="">Choose a priest or staff member</option>@foreach($personnel as $person)<option value="{{ $person->id }}" @selected((string)old('personnel_id', $appointment->personnel_id) === (string)$person->id)>{{ $person->name }}</option>@endforeach</select></x-admin.field>
                <button class="admin-button">Save assignment</button>
            </form>
        @endif @endcan
    </section>
    @can('documents.manage')<section id="appointment-documents" class="admin-card scroll-mt-28" data-document-verification><h2 class="mb-2 text-2xl">Check documents</h2><p class="mb-4 max-w-3xl text-sm leading-6 text-muted">Check each paper copy, then choose Verified or Decline. The appointment is confirmed only when every required document is verified.</p><p role="status" aria-live="polite" data-document-feedback hidden></p><div class="grid gap-3">
        @foreach($appointment->documentRequirements() as $key => $requirement)
            @php($documentVerified = $appointment->physical_documents[$key]['verified'] ?? false)
            <article class="grid gap-4 rounded-xl border border-navy/10 p-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-center" data-document-card data-document-key="{{ $key }}" data-document-state="{{ $documentVerified ? 'verified' : 'pending' }}"><div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><p class="text-sm font-semibold leading-6 text-navy">{{ $requirement['label'] }}</p><span @class(['inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold', 'border-emerald-200 bg-emerald-50 text-emerald-800' => $documentVerified, 'border-amber-200 bg-amber-50 text-amber-900' => ! $documentVerified]) data-document-status><span aria-hidden="true" class="size-1.5 rounded-full bg-current"></span><span data-document-status-label>{{ $documentVerified ? 'Verified' : 'Needs checking' }}</span></span></div><p class="mt-1 text-xs text-muted">{{ $requirement['required'] ? 'Required physical copy' : 'Optional physical copy' }}</p>
                    @if(! empty($appointment->documents[$key]))
                        <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs"><span class="text-muted">Attached copy:</span>@foreach($appointment->documents[$key] as $index => $file)<a class="text-link break-all" href="{{ route('admin.documents.show', [$appointment, $key, $index]) }}">{{ $file['original_name'] }}</a>@endforeach</div>
                    @endif
                </div>
                @if(in_array($appointment->status, ['pending', 'confirmed']))<form class="flex flex-wrap gap-2 md:justify-end" method="POST" action="{{ route('admin.documents.update', $appointment) }}" data-document-decision-form data-document-key="{{ $key }}">@csrf @method('PATCH')<input type="hidden" name="version" value="{{ $appointment->version }}"><input type="hidden" name="type" value="{{ $key }}">
                    <button class="admin-button min-h-10 px-4 py-2" name="decision" value="verified">Verified</button>
                    <button class="admin-secondary min-h-10 border-red-200 px-4 py-2 text-red-800 hover:border-red-300 hover:bg-red-50" name="decision" value="declined">Decline</button>
                </form>@endif
            </article>
        @endforeach
    </div>
        @if(in_array($appointment->status, ['pending', 'confirmed']) && count($appointment->documentRequirements()))
            <details class="mt-5 rounded-xl border border-navy/10 p-4" data-document-upload><summary class="cursor-pointer text-sm font-semibold">Attach an optional document copy</summary>
                <form method="POST" action="{{ route('admin.documents.store', $appointment) }}" enctype="multipart/form-data" class="mt-4 grid items-end gap-4 sm:grid-cols-2">@csrf
                    <input type="hidden" name="version" value="{{ $appointment->version }}">
                    <x-admin.field label="Requirement" name="type"><select class="admin-input" name="type">@foreach($appointment->documentRequirements() as $key => $requirement)<option value="{{ $key }}">{{ $requirement['label'] }}</option>@endforeach</select></x-admin.field>
                    <x-admin.field label="Copies (PDF, JPG or PNG; up to 5 MB each)" name="files[]" type="file" accept=".pdf,.jpg,.jpeg,.png" multiple required />
                    <x-admin.field label="Upload note" name="reason" required /><button class="admin-secondary">Upload copies</button>
                </form>
            </details>
        @endif
    </section>@endcan
    @can('finance.view')@include('admin.appointments.payments')@endcan
    <div data-appointment-status-slot>@include('admin.appointments.partials.status-section', ['appointment' => $appointment])</div>
    @if($appointment->serviceRecord)@can('records.view')<p class="admin-card text-sm">Permanent record created: <a class="underline" href="{{ route('admin.records.show', $appointment->serviceRecord) }}">{{ $appointment->serviceRecord->record_number }}</a> · {{ $appointment->completed_at?->format('M j, Y g:i A') }}</p>@endcan
@endif
    <details id="appointment-history" class="admin-card scroll-mt-28"><summary class="cursor-pointer text-lg font-semibold">Appointment history</summary><div class="mt-4 grid gap-4">@forelse($history as $entry)<div class="border-b border-navy/10 pb-3 text-sm"><p><strong>{{ \Illuminate\Support\Str::headline($entry->action) }}</strong> · {{ $entry->created_at->format('M j, Y g:i A') }}</p><p class="text-muted">{{ $entry->actor?->name ?? 'Client / system' }} · {{ $entry->reason }}</p></div>@empty<p class="text-sm text-muted">No recorded changes.</p>@endforelse</div></details>
@endsection
