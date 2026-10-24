@extends('admin.layout')
@section('title', 'Parish settings')
@section('content')
    <nav aria-label="Settings sections" class="flex flex-wrap gap-2">
        <a class="admin-secondary" href="#office-settings">Office & venues</a>
        <a class="admin-secondary" href="#service-settings">Services & fees</a>
        <a class="admin-secondary" href="#personnel-settings">Personnel</a>
        @can('manage-admin-roles')
            <a class="admin-secondary" href="#permission-settings">Permissions</a>
            <a class="admin-secondary" href="#backup-settings">Backup & recovery</a>
        @endcan
    </nav>
    <form id="office-settings" class="admin-card grid gap-6" method="POST" action="{{ route('admin.settings.update') }}">@csrf @method('PUT')
        <header>
            <h2 class="text-2xl">Office settings</h2>
            <p class="mt-1 text-sm text-muted">Manage the parish details used for appointment requests and office schedules.</p>
        </header>

        <section class="grid gap-4 border-t border-navy/10 pt-5">
            <div>
                <h3 class="font-semibold">Parish information</h3>
                <p class="mt-1 text-sm text-muted">This name appears throughout the administration workspace.</p>
            </div>
            <div class="max-w-2xl">
                <x-admin.field label="Parish name" name="parish_name" :value="$settings['parish_name']" required />
            </div>
        </section>

        <section class="grid gap-4 border-t border-navy/10 pt-5">
            <div>
                <h3 class="font-semibold">Operating schedule</h3>
                <p class="mt-1 text-sm text-muted">Set the regular hours and days available for parish appointments.</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.field label="Opening time" name="opens_at" type="time" :value="$settings['opens_at']" required />
                <x-admin.field label="Closing time" name="closes_at" type="time" :value="$settings['closes_at']" help="Include any preparation time needed after the final appointment." required />
            </div>
            <fieldset>
                <legend class="mb-3 text-sm font-semibold">Operating days</legend>
                <div class="flex flex-wrap gap-x-5 gap-y-3">
                    @foreach([1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday',7=>'Sunday'] as $number => $day)
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="operating_days[]" value="{{ $number }}" @checked(in_array($number, $settings['operating_days']))>{{ $day }}</label>
                    @endforeach
                </div>
            </fieldset>
        </section>

        <fieldset class="grid gap-3 border-t border-navy/10 pt-5">
            <legend class="font-semibold">Appointment venue availability</legend>
            <p class="text-sm text-muted">Checked venues can receive new appointment requests. Existing appointments are not affected.</p>
            <input type="hidden" name="active_venues_present" value="1">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach($venues as $venue)
                    <label class="flex min-h-12 items-center gap-3 rounded-lg border border-navy/10 px-4 py-3 text-sm transition hover:border-navy/20 hover:bg-navy/[0.02]">
                        <input type="checkbox" name="active_venues[]" value="{{ $venue }}" @checked(in_array($venue, old('active_venues_present') ? old('active_venues', []) : $activeVenues, true))>
                        <span>{{ $venue }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        @if(config('office.analytics_enabled'))
            <details class="group rounded-xl border border-navy/10 bg-navy/[0.02]" @if($errors->has('analytics_start') || $errors->has('missing_months_text')) open @endif>
                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 rounded-xl px-4 py-3 marker:hidden">
                    <span>
                        <span class="block text-sm font-semibold">Advanced reporting settings</span>
                        <span class="mt-0.5 block text-xs leading-5 text-muted">Only needed when correcting gaps in historical appointment data.</span>
                    </span>
                    <svg class="size-5 shrink-0 text-muted transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path d="m5 7.5 5 5 5-5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" />
                    </svg>
                </summary>
                <div class="grid gap-4 border-t border-navy/10 px-4 py-4 sm:grid-cols-2">
                    <x-admin.field label="Reporting history starts" name="analytics_start" type="date" :value="$settings['analytics_start']" help="Choose the first month with complete online appointment records." />
                    <x-admin.field label="Months with incomplete records" name="missing_months_text" :value="implode(', ', $settings['missing_months'])" placeholder="Example: 2025-02, 2025-03" help="Optional. Enter each month as YYYY-MM, separated by commas." />
                    <p class="text-xs leading-5 text-muted sm:col-span-2">These settings affect operational reports only. Forecasts use confirmed parish statistics.</p>
                </div>
            </details>
        @endif

        <button class="admin-button justify-self-start">Save office settings</button>
    </form>
    <section id="service-settings" class="admin-card"><h2 class="mb-2 text-2xl">Services, fees & preparation gaps</h2><p class="mb-5 text-sm text-muted">Changes apply to new requests. Current appointments retain their document requirements and fee. Preparation gaps may be extended when scheduling.</p>
        @foreach($services as $service)<details class="border-b border-navy/10 py-4"><summary class="cursor-pointer font-semibold">{{ $service->name }} · {{ $service->duration_minutes }} min + {{ $service->buffer_minutes }} min preparation · ₱{{ number_format($service->fee_cents / 100, 2) }}</summary>@include('admin.partials.service-form', ['service' => $service])</details>@endforeach
        <details class="mt-5"><summary class="cursor-pointer font-semibold">Add another service</summary>@include('admin.partials.service-form', ['service' => new \App\Models\ServiceType(['duration_minutes' => 60, 'buffer_minutes' => 30, 'fee_cents' => 0, 'active' => true, 'requirements' => []])])</details>
    </section>
    <section id="personnel-settings" class="admin-card"><h2 class="mb-5 text-2xl">Priests & authorized personnel</h2>
        @foreach($personnel->push(new \App\Models\Personnel(['active' => true])) as $person)
            <form class="grid items-end gap-3 border-b border-navy/10 py-4 sm:grid-cols-4" method="POST" action="{{ $person->exists ? route('admin.personnel.update', $person) : route('admin.personnel.store') }}">@csrf @if($person->exists)@method('PUT')@endif
                <x-admin.field :label="$person->exists ? 'Name' : 'New personnel name'" name="name" :value="$person->name" required /><x-admin.field label="Linked priest account" name="user_id"><select class="admin-input" name="user_id"><option value="">No login account</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected($person->user_id === $user->id)>{{ $user->name }}</option>@endforeach</select></x-admin.field>
                <x-admin.field label="Availability" name="active"><select class="admin-input" name="active"><option value="1" @selected($person->active)>Active</option><option value="0" @selected(!$person->active)>Inactive</option></select></x-admin.field><button class="admin-secondary">{{ $person->exists ? 'Save personnel' : 'Add personnel' }}</button>
            </form>
        @endforeach
    </section>
    @can('manage-admin-roles')<details id="permission-settings" class="admin-card"><summary class="cursor-pointer text-lg font-semibold">Role permissions</summary><p class="my-4 text-sm text-muted">Super administrators retain full access and exclusive role administration. Priests can access only their assigned appointments.</p><form class="grid gap-5" method="POST" action="{{ route('admin.permissions.update') }}">@csrf @method('PUT')
        <div class="grid gap-5 sm:grid-cols-3">@foreach(['admin','staff','priest'] as $role)<fieldset class="rounded-lg border border-navy/10 p-4"><legend class="px-2 font-semibold">{{ ucfirst($role) }}</legend><div class="grid gap-3">@foreach($abilities as $ability)@if($role !== 'priest' || in_array($ability, ['appointments.view','appointments.approve']))<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="permissions[{{ $role }}][]" value="{{ $ability }}" @checked(in_array($ability, $settings['permissions'][$role] ?? config('office.permissions.'.$role)))>{{ \Illuminate\Support\Str::headline(str_replace('.', ' ', $ability)) }}</label>@endif
@endforeach</div></fieldset>@endforeach</div>
        <x-admin.field label="Your current password" name="password" type="password" autocomplete="current-password" required /><button class="admin-button justify-self-start">Save permissions</button>
    </form></details>@endcan
    @can('manage-admin-roles')
        <details id="backup-settings" class="admin-card"><summary class="cursor-pointer text-lg font-semibold">Encrypted backup & recovery</summary><p class="my-4 text-sm text-muted">Download an encrypted backup of office records, accounts, audit history and uploaded documents. Keep a copy off this server. Recovery requires the original application encryption key stored separately and an empty recovery database; ask the system administrator to perform recovery.</p><form class="flex flex-wrap items-end gap-4" method="POST" action="{{ route('admin.backups.store') }}">@csrf<x-admin.field label="Your current password" name="password" type="password" autocomplete="current-password" required /><button class="admin-secondary">Create & download encrypted backup</button></form></details>
    @endcan
@endsection
