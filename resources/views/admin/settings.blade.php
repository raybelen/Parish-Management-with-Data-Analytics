@extends('admin.layout')
@section('title', 'Parish settings')
@section('content')
    @can('manage-admin-roles')
        <details class="admin-card"><summary class="cursor-pointer text-lg font-semibold">Encrypted backup & recovery</summary><p class="my-4 text-sm text-muted">Download an encrypted backup of office records, accounts, audit history and uploaded documents. Keep a copy off this server. Recovery requires the original application encryption key stored separately and an empty recovery database; ask the system administrator to perform recovery.</p><form class="flex flex-wrap items-end gap-4" method="POST" action="{{ route('admin.backups.store') }}">@csrf<x-admin.field label="Your current password" name="password" type="password" autocomplete="current-password" required /><button class="admin-secondary">Create & download encrypted backup</button></form></details>
    @endcan
    <form class="admin-card grid gap-4 sm:grid-cols-2 xl:grid-cols-3" method="POST" action="{{ route('admin.settings.update') }}">@csrf @method('PUT')
        <h2 class="text-2xl sm:col-span-2 xl:col-span-3">Office settings</h2>
        <x-admin.field label="Parish name" name="parish_name" :value="$settings['parish_name']" required />
        <x-admin.field label="Opening time" name="opens_at" type="time" :value="$settings['opens_at']" required /><x-admin.field label="Closing time (including preparation)" name="closes_at" type="time" :value="$settings['closes_at']" required />
        <fieldset class="sm:col-span-2 xl:col-span-3"><legend class="mb-3 text-sm font-semibold">Operating days</legend><div class="flex flex-wrap gap-4">@foreach([1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday',7=>'Sunday'] as $number => $day)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="operating_days[]" value="{{ $number }}" @checked(in_array($number, $settings['operating_days']))>{{ $day }}</label>@endforeach</div></fieldset>
        @if(config('office.analytics_enabled'))
        <x-admin.field label="First complete operational month (first day)" name="analytics_start" type="date" :value="$settings['analytics_start']" />
        <x-admin.field label="Unknown months (YYYY-MM, comma-separated)" name="missing_months_text" :value="implode(', ', $settings['missing_months'])" />
        <x-admin.field label="Forecast horizon (months)" name="forecast_horizon" type="number" min="1" max="12" :value="$settings['forecast_horizon']" required />
        <x-admin.field label="Training window (months)" name="training_months" type="number" min="36" max="120" :value="$settings['training_months']" required />
        <x-admin.field label="Minimum history (months)" name="minimum_months" type="number" min="24" max="120" :value="$settings['minimum_months']" required />
        <p class="text-sm text-muted">Mark a month complete only when all its operational records are available. Unknown periods are not treated as zero. Archived register imports remain excluded.</p>
        <fieldset class="sm:col-span-2 xl:col-span-3"><legend class="mb-3 text-sm font-semibold">Advanced SARIMA parameters</legend><div class="grid gap-3 sm:grid-cols-7">@foreach(['p','d','q'] as $index => $label)<x-admin.field :label="$label" :name="'order['.$index.']'" type="number" min="0" max="2" :value="$settings['order'][$index]" required />@endforeach @foreach(['P','D','Q','Season length'] as $index => $label)<x-admin.field :label="$label" :name="'seasonal_order['.$index.']'" type="number" min="0" max="12" :value="$settings['seasonal_order'][$index]" required />@endforeach</div></fieldset>
        @endif
        <button class="admin-button justify-self-start">Save settings</button>
    </form>
    <section class="admin-card"><h2 class="mb-2 text-2xl">Services, fees & preparation gaps</h2><p class="mb-5 text-sm text-muted">Changes apply to new requests. Current appointments retain their document requirements and fee. Preparation gaps may be extended when scheduling.</p>
        @foreach($services as $service)<details class="border-b border-navy/10 py-4"><summary class="cursor-pointer font-semibold">{{ $service->name }} · {{ $service->duration_minutes }} min + {{ $service->buffer_minutes }} min preparation · ₱{{ number_format($service->fee_cents / 100, 2) }}</summary>@include('admin.partials.service-form', ['service' => $service])</details>@endforeach
        <details class="mt-5"><summary class="cursor-pointer font-semibold">Add another service</summary>@include('admin.partials.service-form', ['service' => new \App\Models\ServiceType(['duration_minutes' => 60, 'buffer_minutes' => 30, 'fee_cents' => 0, 'active' => true, 'requirements' => []])])</details>
    </section>
    <section class="admin-card"><h2 class="mb-5 text-2xl">Priests & authorized personnel</h2>
        @foreach($personnel->push(new \App\Models\Personnel(['active' => true])) as $person)
            <form class="grid items-end gap-3 border-b border-navy/10 py-4 sm:grid-cols-4" method="POST" action="{{ $person->exists ? route('admin.personnel.update', $person) : route('admin.personnel.store') }}">@csrf @if($person->exists)@method('PUT')@endif
                <x-admin.field :label="$person->exists ? 'Name' : 'New personnel name'" name="name" :value="$person->name" required /><x-admin.field label="Linked priest account" name="user_id"><select class="admin-input" name="user_id"><option value="">No login account</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected($person->user_id === $user->id)>{{ $user->name }}</option>@endforeach</select></x-admin.field>
                <x-admin.field label="Availability" name="active"><select class="admin-input" name="active"><option value="1" @selected($person->active)>Active</option><option value="0" @selected(!$person->active)>Inactive</option></select></x-admin.field><button class="admin-secondary">{{ $person->exists ? 'Save personnel' : 'Add personnel' }}</button>
            </form>
        @endforeach
    </section>
    @can('manage-admin-roles')<details class="admin-card"><summary class="cursor-pointer text-lg font-semibold">Role permissions</summary><p class="my-4 text-sm text-muted">Super administrators retain full access and exclusive role administration. Priests can access only their assigned appointments.</p><form class="grid gap-5" method="POST" action="{{ route('admin.permissions.update') }}">@csrf @method('PUT')
        <div class="grid gap-5 sm:grid-cols-3">@foreach(['admin','staff','priest'] as $role)<fieldset class="rounded-lg border border-navy/10 p-4"><legend class="px-2 font-semibold">{{ ucfirst($role) }}</legend><div class="grid gap-3">@foreach($abilities as $ability)@if($role !== 'priest' || in_array($ability, ['appointments.view','appointments.approve']))<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="permissions[{{ $role }}][]" value="{{ $ability }}" @checked(in_array($ability, $settings['permissions'][$role] ?? config('office.permissions.'.$role)))>{{ \Illuminate\Support\Str::headline(str_replace('.', ' ', $ability)) }}</label>@endif
@endforeach</div></fieldset>@endforeach</div>
        <x-admin.field label="Your current password" name="password" type="password" autocomplete="current-password" required /><button class="admin-button justify-self-start">Save permissions</button>
    </form></details>@endcan
@endsection
