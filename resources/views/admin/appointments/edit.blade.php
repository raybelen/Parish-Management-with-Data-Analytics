@extends('admin.layout')
@section('title', 'Edit appointment details')
@section('actions')<a class="admin-secondary" href="{{ route('admin.appointments.show', $appointment) }}">Back to appointment</a>@endsection
@section('content')
    <p class="text-sm leading-6 text-muted">Check the parishioner’s information against their documents and correct any mistakes before completing the appointment.</p>
    <section class="admin-card grid gap-2 text-sm">
        <h2 class="text-xl">{{ $service->name }} · {{ $appointment->reference_number }}</h2>
        <p>{{ ($appointment->starts_at ?? $appointment->preferred_date)->format('F j, Y') }} · {{ substr($appointment->preferred_time, 0, 5) }} · {{ $appointment->confirmed_church ?? $appointment->preferred_church }}</p>
        @if($appointment->service_type === 'others')
            <p>{{ config('appointments.details.others.service_kind.options.'.$appointment->service_details['service_kind'], $appointment->service_details['service_kind']) }}</p>
            <p class="whitespace-pre-line">{{ $appointment->service_details['service_location'] ?? '' }}</p>
        @endif
    </section>
    <form class="grid gap-6" method="POST" action="{{ route('admin.appointments.details', $appointment) }}">
        @csrf
        @method('PATCH')
        <input type="hidden" name="version" value="{{ old('version', $appointment->version) }}">
        <section class="admin-card grid gap-4 sm:grid-cols-2">
            <h2 class="text-2xl sm:col-span-2">Booking contact</h2>
            <x-admin.field label="Full name" name="client_full_name" :value="$appointment->client_full_name" maxlength="255" required />
            <x-admin.field label="Phone number" name="client_contact_number" type="tel" :value="$appointment->client_contact_number" required />
            <x-admin.field label="Email" name="client_email" type="email" :value="$appointment->client_email" maxlength="255" />
            <x-admin.field label="Address" name="client_address" :value="$appointment->client_address" maxlength="1000" required />
            <x-admin.field label="Preferred contact method" name="preferred_contact_method">
                <select name="preferred_contact_method" class="admin-input" required>
                    @foreach(['phone' => 'Phone', 'sms' => 'SMS', 'email' => 'Email'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('preferred_contact_method', $appointment->preferred_contact_method) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-admin.field>
            <x-admin.field label="Additional notes" name="additional_notes">
                <textarea name="additional_notes" class="admin-input" rows="3" maxlength="2000">{{ old('additional_notes', $appointment->additional_notes) }}</textarea>
            </x-admin.field>
        </section>
        <section class="admin-card">
            <h2 class="text-2xl">{{ $service->name }} details</h2>
            @if(config('appointments.details.'.$appointment->service_type))
                <x-appointment-service-fields :service-type="$appointment->service_type" :active="true" :address-picker="false" input-class="admin-input" select-class="admin-input" :values="$appointment->service_details" :preferred-date="$appointment->preferred_date->toDateString()" :exclude="$appointment->service_type === 'others' ? ['service_kind', 'service_location'] : []" />
            @else
                <x-admin.field label="Recipient full name" name="service[recipient_full_name]" :value="old('service.recipient_full_name', $appointment->service_details['recipient_full_name'] ?? '')" maxlength="255" required />
            @endif
        </section>
        <section class="admin-card grid gap-4">
            <h2 class="text-2xl">Confirm correction</h2>
            <x-admin.field label="Reason for correction" name="change_reason" maxlength="1000" help="For example: Corrected spelling based on the birth certificate." required />
            <label class="flex items-start gap-3 text-sm leading-6">
                <input class="mt-1 size-4" type="checkbox" name="documents_checked" value="1" required>
                <span>I checked the corrected details against the documents and confirm they match.</span>
            </label>
            @error('documents_checked')<p class="text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
            <p class="text-sm text-muted">Your name, the correction reason, and the previous and corrected values will be saved in the appointment history.</p>
        </section>
        <div class="flex flex-wrap gap-3">
            <button class="admin-button">Save correction</button>
            <a class="admin-secondary" href="{{ route('admin.appointments.show', $appointment) }}">Cancel</a>
        </div>
    </form>
@endsection
