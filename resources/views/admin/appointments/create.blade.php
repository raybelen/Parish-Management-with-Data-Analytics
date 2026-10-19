@extends('admin.layout')
@section('title', isset($appointment) ? 'Correct appointment details' : 'New walk-in appointment')
@section('content')
    <p class="text-sm text-muted">Record a current office request without a client account. Older register entries belong in Service records.</p>
    <form class="grid gap-6" method="POST" action="{{ isset($appointment) ? route('admin.appointments.details', $appointment) : route('admin.appointments.store') }}">@csrf
        @isset($appointment)@method('PATCH')<input type="hidden" name="version" value="{{ $appointment->version }}">@endisset
        <section class="admin-card grid gap-4 sm:grid-cols-2"><h2 class="text-2xl sm:col-span-2">Booking contact</h2>
            <x-admin.field label="Full name" name="client_full_name" :value="$appointment->client_full_name ?? ''" required /><x-admin.field label="Phone number" name="client_contact_number" type="tel" :value="$appointment->client_contact_number ?? ''" required />
            <x-admin.field label="Email (optional)" name="client_email" type="email" :value="$appointment->client_email ?? ''" /><x-admin.field label="Address" name="client_address" :value="$appointment->client_address ?? ''" required />
            <input type="hidden" name="preferred_contact_method" value="phone">
            <x-admin.field label="Service" name="service_type"><select name="service_type" class="admin-input" data-walkin-service required><option value="">Choose a service</option>@foreach($services as $service)<option value="{{ $service->slug }}" @selected(old('service_type', $appointment->service_type ?? '') === $service->slug)>{{ $service->name }}</option>@endforeach</select></x-admin.field>
            @php($selectedChurch = old('preferred_church', $appointment->preferred_church ?? ''))
            <x-admin.field label="Requested venue" name="preferred_church"><select name="preferred_church" class="admin-input" required><option value="">Choose an available venue</option>@if($selectedChurch && !in_array($selectedChurch, $churches, true))<option value="{{ $selectedChurch }}" selected>{{ $selectedChurch }} (currently unavailable)</option>@endif @foreach($churches as $church)<option value="{{ $church }}" @selected($selectedChurch === $church)>{{ $church }}</option>@endforeach</select></x-admin.field>
            <x-admin.field label="Requested date" name="preferred_date" type="date" :value="isset($appointment) ? $appointment->preferred_date->toDateString() : ''" required /><x-admin.field label="Requested time" name="preferred_time" type="time" :value="isset($appointment) ? substr($appointment->preferred_time, 0, 5) : ''" required />
            <x-admin.field label="Additional notes" name="additional_notes" :value="$appointment->additional_notes ?? ''" />
        </section>
        @foreach($services as $service)
            <section class="admin-card" data-walkin-fields="{{ $service->slug }}" hidden>
                <h2 class="mb-5 text-2xl">{{ $service->name }} participants & details</h2>
                @if(config('appointments.details.'.$service->slug))
                    <x-appointment-service-fields :service-type="$service->slug" input-class="admin-input" select-class="admin-input" :values="$appointment->service_details ?? []" :preferred-date="isset($appointment) ? $appointment->preferred_date->toDateString() : ''" />
                @else
                    <label class="grid gap-2 text-sm"><span>Recipient full name *</span><input type="text" name="service[recipient_full_name]" class="admin-input" value="{{ old('service.recipient_full_name', $appointment->service_details['recipient_full_name'] ?? '') }}" required disabled></label>
                @endif
            </section>
        @endforeach
        <p class="rounded-lg border border-gold/30 bg-amber-50 p-4 text-sm">Online uploads are optional. Required physical copies must be submitted and verified at the church office.</p>
        @isset($appointment)<x-admin.field label="Reason for correction" name="change_reason" required />@endisset
        <button class="admin-button justify-self-start">{{ isset($appointment) ? 'Save corrected details' : 'Create pending appointment' }}</button>
    </form>
@endsection
