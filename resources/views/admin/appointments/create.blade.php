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
            <x-admin.field label="Requested venue" name="preferred_church"><select name="preferred_church" class="admin-input">@foreach(config('parish.churches') as $church)<option @selected(old('preferred_church', $appointment->preferred_church ?? '') === $church)>{{ $church }}</option>@endforeach</select></x-admin.field>
            <x-admin.field label="Requested date" name="preferred_date" type="date" :value="isset($appointment) ? $appointment->preferred_date->toDateString() : ''" required /><x-admin.field label="Requested time" name="preferred_time" type="time" :value="isset($appointment) ? substr($appointment->preferred_time, 0, 5) : ''" required />
            <x-admin.field label="Additional notes" name="additional_notes" :value="$appointment->additional_notes ?? ''" />
        </section>
        @php
            $fields = [
                'baptism' => ['child_full_name' => 'text', 'child_date_of_birth' => 'date', 'child_place_of_birth' => 'text', 'child_sex' => ['female', 'male'], 'father_name' => 'text', 'mother_name' => 'text', 'godfather_name' => 'text', 'godmother_name' => 'text'],
                'wedding' => ['bride_full_name' => 'text', 'groom_full_name' => 'text', 'bride_contact_number' => 'tel', 'groom_contact_number' => 'tel', 'current_address' => 'text', 'expected_guest_count' => 'number', 'civil_status' => ['single', 'widowed', 'annulled'], 'marriage_license_status' => ['not_started', 'in_process', 'secured']],
                'funeral' => ['deceased_full_name' => 'text', 'date_of_death' => 'date', 'place_of_death' => 'text', 'age' => 'number', 'residence_address' => 'text', 'funeral_home' => 'text', 'wake_location' => 'text', 'cemetery_location' => 'text', 'contact_full_name' => 'text', 'contact_relationship' => 'text', 'contact_number' => 'tel', 'contact_address' => 'text'],
            ];
        @endphp
        @foreach($services as $service)<section class="admin-card" data-walkin-fields="{{ $service->slug }}" hidden><h2 class="mb-5 text-2xl">{{ $service->name }} participants & details</h2><div class="grid gap-4 sm:grid-cols-2">
            @foreach($fields[$service->slug] ?? ['recipient_full_name' => 'text'] as $field => $type)
                <label class="grid gap-2 text-sm"><span>{{ \Illuminate\Support\Str::headline($field) }} *</span>@if(is_array($type))<select name="service[{{ $field }}]" class="admin-input" required disabled>@foreach($type as $option)<option value="{{ $option }}" @selected(old('service.'.$field, $appointment->service_details[$field] ?? '') === $option)>{{ \Illuminate\Support\Str::headline($option) }}</option>@endforeach</select>@else<input type="{{ $type }}" name="service[{{ $field }}]" class="admin-input" value="{{ old('service.'.$field, $appointment->service_details[$field] ?? '') }}" required disabled @if($type === 'number') min="0" @endif>@endif</label>
            @endforeach
        </div></section>@endforeach
        <p class="rounded-lg border border-gold/30 bg-amber-50 p-4 text-sm">Online uploads are optional. Required physical copies must be submitted and verified at the church office.</p>
        @isset($appointment)<x-admin.field label="Reason for correction" name="change_reason" required />@endisset
        <button class="admin-button justify-self-start">{{ isset($appointment) ? 'Save corrected details' : 'Create pending appointment' }}</button>
    </form>
@endsection
