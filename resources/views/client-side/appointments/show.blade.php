@extends('client-side.layouts.parish')

@section('content')
    @php
        $detailLabels = match ($appointment->service_type) {
            'baptism' => [
                'child_full_name' => "Child's full name",
                'child_date_of_birth' => 'Date of birth',
                'child_place_of_birth' => 'Place of birth',
                'child_sex' => 'Sex',
                'father_name' => "Father's name",
                'mother_name' => "Mother's name",
                'godfather_name' => "Godfather's name",
                'godmother_name' => "Godmother's name",
                'additional_godparents' => 'Additional godparents',
                'special_requests' => 'Baptism special requests',
                'notes' => 'Notes for parish staff',
            ],
            'wedding' => [
                'bride_full_name' => "Bride's full name",
                'groom_full_name' => "Groom's full name",
                'bride_contact_number' => "Bride's contact number",
                'groom_contact_number' => "Groom's contact number",
                'current_address' => 'Current address',
                'expected_guest_count' => 'Expected number of guests',
                'civil_status' => 'Civil status',
                'previous_marriage' => 'Previous marriage details',
                'marriage_license_status' => 'Marriage license status',
                'preferred_priest' => 'Preferred priest / minister',
                'principal_sponsors' => 'Principal sponsors',
                'witnesses' => 'Witnesses',
            ],
            'funeral' => [
                'deceased_full_name' => "Deceased's full name",
                'date_of_birth' => 'Date of birth',
                'date_of_death' => 'Date of death',
                'place_of_death' => 'Place of death',
                'age' => 'Age',
                'residence_address' => 'Address / residence',
                'funeral_home' => 'Funeral home',
                'wake_location' => 'Location of wake',
                'cemetery_location' => 'Burial / cemetery location',
                'expected_attendee_count' => 'Expected number of attendees',
                'contact_full_name' => 'Family contact name',
                'contact_relationship' => 'Relationship to deceased',
                'contact_number' => 'Family contact number',
                'contact_email' => 'Family contact email',
                'contact_address' => 'Family contact address',
                'special_requests' => 'Funeral special requests',
                'priest_notes' => 'Notes for the priest / church staff',
            ],
        };
        $dateFields = ['child_date_of_birth', 'date_of_birth', 'date_of_death'];
        $inputClass = 'min-h-12 w-full border border-navy/20 bg-white px-4 py-3 text-sm text-navy outline-none transition-colors focus:border-gold focus:ring-1 focus:ring-gold user-invalid:border-red-700';
        $statusClasses = match ($appointment->status) {
            'confirmed' => 'bg-emerald-100 text-emerald-900',
            'cancelled' => 'bg-red-100 text-red-900',
            default => 'bg-amber-100 text-amber-900',
        };
        $statusDotClass = match ($appointment->status) {
            'confirmed' => 'bg-emerald-600',
            'cancelled' => 'bg-red-600',
            default => 'bg-amber-600',
        };
    @endphp

    <section class="border-b border-navy/10 bg-navy py-14 text-ivory sm:py-20">
        <div class="section-wrap max-w-6xl">
            <nav aria-label="Breadcrumb" class="flex flex-wrap items-center gap-3 text-sm text-ivory/60">
                <a href="{{ route('home') }}" class="inline-flex min-h-11 items-center hover:text-gold">Home</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('appointments.index') }}" class="inline-flex min-h-11 items-center hover:text-gold">Appointments</a>
                <span aria-hidden="true">/</span>
                <span aria-current="page">Confirmation</span>
            </nav>

            <div class="mt-8 grid gap-8 lg:grid-cols-[1fr_auto] lg:items-end">
                <div class="max-w-3xl">
                    <p class="section-label text-gold">Request Received</p>
                    <h1 class="mt-5 text-4xl leading-tight text-ivory sm:text-6xl">Your appointment request is on file.</h1>
                    <p class="mt-5 max-w-2xl text-base leading-8 text-ivory/70">Please keep your reference number. Parish staff will review the request and contact you through your preferred method.</p>
                </div>
                <div class="border border-gold/45 bg-ivory/5 p-6 lg:min-w-72">
                    <p class="text-xs font-medium tracking-[0.16em] uppercase text-ivory/60">Reference number</p>
                    <p class="mt-3 font-mono text-xl font-semibold tracking-wide text-gold sm:text-2xl">{{ $appointment->reference_number }}</p>
                    <p class="mt-4 text-xs leading-5 text-ivory/60">Use this with your saved email address or phone number to find the request later.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-[#eae5da] py-12 sm:py-16">
        <div class="section-wrap max-w-6xl">
            @if (session('appointment_message'))
                <div class="mb-7 border-l-4 border-emerald-700 bg-emerald-50 px-6 py-5 text-sm text-emerald-900" role="status">
                    {{ session('appointment_message') }}
                </div>
            @endif

            <div class="grid gap-7 lg:grid-cols-[0.7fr_1.3fr]">
                <aside class="h-fit border border-navy/15 bg-ivory p-6 sm:p-8">
                    <p class="section-label text-muted">Current Status</p>
                    <div class="mt-5 inline-flex items-center gap-3 px-4 py-2 text-sm font-medium {{ $statusClasses }}">
                        <span class="size-2 rounded-full {{ $statusDotClass }}" aria-hidden="true"></span>
                        {{ str($appointment->status)->replace('_', ' ')->title() }}
                    </div>
                    @if ($appointment->status === 'cancelled')
                        <p class="mt-5 text-sm leading-7 text-muted">This request has been cancelled. Its details remain on file, but it is no longer being considered for scheduling.</p>
                    @elseif ($appointment->status === 'confirmed')
                        <p class="mt-5 text-sm leading-7 text-muted">This appointment has been confirmed by the parish office. Contact the office directly if you need further assistance.</p>
                    @else
                        <p class="mt-5 text-sm leading-7 text-muted">This is a request, not yet a confirmed schedule. The parish office may contact you for additional documents or an alternate time.</p>
                    @endif
                    <a href="{{ route('appointments.lookup.create') }}" class="text-link mt-7">Find this request again <x-parish.icon name="arrow" class="size-4" /></a>
                </aside>

                <div class="grid gap-7">
                    <section aria-labelledby="summary-heading" class="border border-navy/15 bg-ivory p-6 sm:p-9">
                        <p class="section-label text-muted">Appointment Summary</p>
                        <h2 id="summary-heading" class="mt-4 text-3xl">{{ $service['label'] }} request</h2>

                        <dl class="mt-8 grid gap-x-8 gap-y-6 sm:grid-cols-2">
                            <div><dt class="text-xs font-medium tracking-[0.12em] uppercase text-muted">Preferred date</dt><dd class="mt-2 text-sm leading-6">{{ $appointment->preferred_date->format('F j, Y') }}</dd></div>
                            <div><dt class="text-xs font-medium tracking-[0.12em] uppercase text-muted">Preferred time</dt><dd class="mt-2 text-sm leading-6">{{ \Carbon\Carbon::parse($appointment->preferred_time)->format('g:i A') }}</dd></div>
                            <div class="sm:col-span-2"><dt class="text-xs font-medium tracking-[0.12em] uppercase text-muted">Preferred church / chapel</dt><dd class="mt-2 text-sm leading-6">{{ $appointment->preferred_church }}</dd></div>
                            @if ($appointment->additional_notes)
                                <div class="sm:col-span-2"><dt class="text-xs font-medium tracking-[0.12em] uppercase text-muted">General notes / special requests</dt><dd class="mt-2 whitespace-pre-line text-sm leading-6">{{ $appointment->additional_notes }}</dd></div>
                            @endif
                        </dl>
                    </section>

                    @if ($canManageRequest)
                        <section aria-labelledby="manage-request-heading" class="border border-navy/15 bg-ivory p-6 sm:p-9">
                            <p class="section-label text-muted">Manage Request</p>
                            <h2 id="manage-request-heading" class="mt-4 text-3xl">Change or cancel your request</h2>
                            <p class="mt-3 max-w-2xl text-sm leading-7 text-muted">You may update the preferred schedule while parish review is still pending. Changes remain subject to parish availability.</p>

                            @if ($errors->any())
                                <div class="mt-6 border-l-4 border-red-700 bg-red-50 px-5 py-4 text-sm text-red-900" role="alert">
                                    <p class="font-medium">Please review the preferred schedule.</p>
                                    <ul class="mt-2 list-disc space-y-1 pl-5">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <form method="POST" action="{{ $scheduleUpdateUrl }}" class="mt-7 grid gap-5 sm:grid-cols-2">
                                @csrf
                                @method('PATCH')

                                <x-parish.form-field id="updated_preferred_date" name="preferred_date" label="New preferred date" :required="true">
                                    <input id="updated_preferred_date" name="preferred_date" type="date" value="{{ old('preferred_date', $appointment->preferred_date->format('Y-m-d')) }}" min="{{ now()->toDateString() }}" required class="{{ $inputClass }}">
                                </x-parish.form-field>

                                <x-parish.form-field id="updated_preferred_time" name="preferred_time" label="New preferred time" :required="true">
                                    <input id="updated_preferred_time" name="preferred_time" type="time" value="{{ old('preferred_time', mb_substr($appointment->preferred_time, 0, 5)) }}" step="900" required class="{{ $inputClass }}">
                                </x-parish.form-field>

                                <button type="submit" class="inline-flex min-h-12 items-center justify-center gap-4 bg-gold px-6 py-3 text-sm font-medium text-navy transition-colors hover:bg-[#c9aa63] sm:col-span-2 sm:justify-self-start">
                                    Update preferred schedule <x-parish.icon name="arrow" class="size-4" />
                                </button>
                            </form>

                            <div class="mt-9 border-t border-navy/10 pt-7">
                                <h3 class="text-2xl">Cancel this request</h3>
                                <p class="mt-2 max-w-2xl text-sm leading-7 text-muted">Cancellation stops parish review of this request. The record will remain available for reference.</p>
                                <button type="button" data-dialog-target="cancel-appointment-dialog" class="mt-5 inline-flex min-h-12 items-center justify-center border border-red-700 px-6 py-3 text-sm font-medium text-red-800 transition-colors hover:bg-red-50">
                                    Cancel appointment request
                                </button>
                            </div>
                        </section>

                        <x-parish.dialog id="cancel-appointment-dialog" title="Cancel appointment request?" aria-describedby="cancel-appointment-description" class="border-red-700/40">
                            <p id="cancel-appointment-description">This will stop parish review of reference <span class="font-mono font-semibold text-navy">{{ $appointment->reference_number }}</span>.</p>
                            <div class="mt-5 border-l-2 border-red-700 bg-red-50 px-5 py-4 text-sm leading-6 text-red-900">
                                Your request will be marked as cancelled. Its submitted details and documents will remain on file, but you cannot restore it from the website.
                            </div>
                            <div class="mt-7 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                                <button type="button" data-close-dialog class="inline-flex min-h-12 items-center justify-center border border-navy/20 px-6 py-3 text-sm font-medium text-navy transition-colors hover:border-gold hover:bg-gold/10">
                                    Keep appointment
                                </button>
                                <form method="POST" action="{{ $cancellationUrl }}" data-cancel-confirmation-form>
                                    @csrf
                                    <button type="submit" class="inline-flex min-h-12 w-full items-center justify-center bg-red-800 px-6 py-3 text-sm font-medium text-white transition-colors hover:bg-red-900 sm:w-auto">
                                        Yes, cancel request
                                    </button>
                                </form>
                            </div>
                        </x-parish.dialog>
                    @elseif ($appointment->status === 'cancelled')
                        <section class="border border-red-200 bg-red-50 p-6 sm:p-9" aria-labelledby="cancelled-request-heading">
                            <p class="section-label text-red-700">Request Closed</p>
                            <h2 id="cancelled-request-heading" class="mt-4 text-3xl text-navy">This appointment request is cancelled.</h2>
                            <p class="mt-3 text-sm leading-7 text-muted">If you need another appointment, please submit a new request with your current preferred schedule.</p>
                            <a href="{{ route('appointments.create') }}" class="text-link mt-6">Make a new appointment <x-parish.icon name="arrow" class="size-4" /></a>
                        </section>
                    @endif

                    <section aria-labelledby="client-summary-heading" class="border border-navy/15 bg-ivory p-6 sm:p-9">
                        <p class="section-label text-muted">Client Information</p>
                        <h2 id="client-summary-heading" class="mt-4 text-3xl">Contact details</h2>
                        <dl class="mt-8 grid gap-x-8 gap-y-6 sm:grid-cols-2">
                            <div><dt class="text-xs font-medium tracking-[0.12em] uppercase text-muted">Full name</dt><dd class="mt-2 text-sm leading-6">{{ $appointment->client_full_name }}</dd></div>
                            <div><dt class="text-xs font-medium tracking-[0.12em] uppercase text-muted">Contact number</dt><dd class="mt-2 text-sm leading-6">{{ $appointment->client_contact_number }}</dd></div>
                            <div><dt class="text-xs font-medium tracking-[0.12em] uppercase text-muted">Email address</dt><dd class="mt-2 text-sm leading-6">{{ $appointment->client_email ?: 'Not provided' }}</dd></div>
                            <div><dt class="text-xs font-medium tracking-[0.12em] uppercase text-muted">Preferred contact method</dt><dd class="mt-2 text-sm leading-6">{{ str($appointment->preferred_contact_method)->replace('_', ' ')->title() }}</dd></div>
                            <div class="sm:col-span-2"><dt class="text-xs font-medium tracking-[0.12em] uppercase text-muted">Address</dt><dd class="mt-2 whitespace-pre-line text-sm leading-6">{{ $appointment->client_address }}</dd></div>
                        </dl>
                    </section>

                    <section aria-labelledby="service-details-heading" class="border border-navy/15 bg-ivory p-6 sm:p-9">
                        <p class="section-label text-muted">Service Details</p>
                        <h2 id="service-details-heading" class="mt-4 text-3xl">Submitted information</h2>
                        <dl class="mt-8 grid gap-x-8 gap-y-6 sm:grid-cols-2">
                            @foreach ($detailLabels as $key => $label)
                                @php($value = $appointment->service_details[$key] ?? null)
                                @if ($value !== null && $value !== '')
                                    <div @class(['sm:col-span-2' => in_array($key, ['current_address', 'previous_marriage', 'principal_sponsors', 'witnesses', 'residence_address', 'wake_location', 'cemetery_location', 'contact_address', 'special_requests', 'notes', 'priest_notes', 'additional_godparents'], true)])>
                                        <dt class="text-xs font-medium tracking-[0.12em] uppercase text-muted">{{ $label }}</dt>
                                        <dd class="mt-2 whitespace-pre-line text-sm leading-6">
                                            @if (in_array($key, $dateFields, true))
                                                {{ \Carbon\Carbon::parse($value)->format('F j, Y') }}
                                            @elseif (in_array($key, ['civil_status', 'marriage_license_status', 'child_sex'], true))
                                                {{ str($value)->replace('_', ' ')->title() }}
                                            @else
                                                {{ $value }}
                                            @endif
                                        </dd>
                                    </div>
                                @endif
                            @endforeach
                        </dl>
                    </section>

                    <section aria-labelledby="documents-summary-heading" class="border border-navy/15 bg-ivory p-6 sm:p-9">
                        <p class="section-label text-muted">Documents</p>
                        <h2 id="documents-summary-heading" class="mt-4 text-3xl">Uploaded files</h2>
                        @if (filled($appointment->documents))
                            <ul class="mt-7 grid gap-3">
                                @foreach ($appointment->documents as $files)
                                    @foreach ($files as $file)
                                        <li class="flex items-start justify-between gap-5 border border-navy/10 bg-white px-5 py-4">
                                            <span>
                                                <span class="block text-sm font-medium text-navy">{{ $file['label'] }}</span>
                                                <span class="mt-1 block break-all text-xs text-muted">{{ $file['original_name'] }}</span>
                                            </span>
                                            <span class="text-xs font-medium text-[#80662e]">Received</span>
                                        </li>
                                    @endforeach
                                @endforeach
                            </ul>
                        @else
                            <p class="mt-5 text-sm leading-7 text-muted">No documents were uploaded with this request.</p>
                        @endif
                    </section>
                </div>
            </div>
        </div>
    </section>
@endsection
