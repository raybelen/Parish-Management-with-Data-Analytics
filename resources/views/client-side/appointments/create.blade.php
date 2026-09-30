@extends('client-side.layouts.parish')

@section('content')
    @php
        $selectedService = old('service_type', '');
        $baptismActive = $selectedService === 'baptism';
        $weddingActive = $selectedService === 'wedding';
        $funeralActive = $selectedService === 'funeral';
        $inputClass = 'min-h-12 w-full border border-navy/20 bg-white px-4 py-3 text-sm text-navy outline-none transition-colors placeholder:text-muted/55 focus:border-gold focus:ring-1 focus:ring-gold disabled:bg-navy/5 disabled:text-muted';
        $selectClass = $inputClass.' parish-select appearance-none pr-10 forced-colors:appearance-auto';
    @endphp

    <section class="border-b border-navy/10 bg-ivory py-12 sm:py-16">
        <div class="section-wrap">
            <nav aria-label="Breadcrumb" class="flex flex-wrap items-center gap-3 text-sm text-muted">
                <a href="{{ route('home') }}" class="inline-flex min-h-11 items-center hover:text-navy">Home</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('appointments.index') }}" class="inline-flex min-h-11 items-center hover:text-navy">Appointments</a>
                <span aria-hidden="true">/</span>
                <span aria-current="page">New request</span>
            </nav>

            <div class="mt-8 grid gap-7 lg:grid-cols-[1fr_auto] lg:items-end">
                <div class="max-w-3xl">
                    <p class="section-label text-muted">New Request</p>
                    <h1 class="mt-5 text-4xl leading-tight sm:text-6xl">Request a parish appointment</h1>
                    <p class="mt-5 text-base leading-8 text-muted">Complete the common information first. The form will then show only the details and documents needed for your selected service.</p>
                </div>
                <div class="border-l-2 border-gold pl-5 text-sm leading-7 text-muted">
                    <p><span class="font-medium text-navy">Required</span> fields must be completed.</p>
                    <p>Optional details can be provided if available.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-[#eae5da] py-12 sm:py-16">
        <form method="POST" action="{{ route('appointments.store') }}" enctype="multipart/form-data" class="section-wrap max-w-6xl" data-appointment-form>
            @csrf

            @if ($errors->any())
                <div class="mb-8 border-l-4 border-red-700 bg-red-50 px-6 py-5 text-red-900" role="alert" tabindex="-1" data-validation-summary>
                    <p class="font-medium">Please review the highlighted information.</p>
                    <ul class="mt-3 list-disc space-y-1 pl-5 text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div hidden class="mb-8 border-l-4 border-red-700 bg-red-50 px-6 py-5 text-red-900" role="alert" tabindex="-1" data-required-alert>
                <p class="font-medium">Please complete the required fields.</p>
                <p class="mt-1 text-sm">The following information is still missing:</p>
                <ul class="mt-3 list-disc space-y-1 pl-5 text-sm" data-required-alert-list></ul>
            </div>

            <div class="grid gap-8">
                <section aria-labelledby="client-information-heading" class="border border-navy/15 bg-ivory p-6 sm:p-9">
                    <div class="max-w-2xl">
                        <p class="section-label text-muted">01 · Client Information</p>
                        <h2 id="client-information-heading" class="mt-4 text-3xl">How may we contact you?</h2>
                    </div>

                    <div class="mt-8 grid gap-6 sm:grid-cols-2">
                        <x-parish.form-field id="client_full_name" name="client_full_name" label="Full name" :required="true">
                            <input id="client_full_name" name="client_full_name" type="text" value="{{ old('client_full_name') }}" required autocomplete="name" autocapitalize="words" data-title-case class="{{ $inputClass }}">
                        </x-parish.form-field>

                        <x-parish.form-field id="client_contact_number" name="client_contact_number" label="Contact number" :required="true">
                            <input id="client_contact_number" name="client_contact_number" type="tel" value="{{ old('client_contact_number') }}" required autocomplete="tel" inputmode="tel" placeholder="0912 345 6789" data-phone-input class="{{ $inputClass }}">
                        </x-parish.form-field>

                        <x-parish.form-field id="client_email" name="client_email" label="Email address" :optional="true" help="Required when email is your preferred contact method.">
                            <input id="client_email" name="client_email" type="email" value="{{ old('client_email') }}" autocomplete="email" autocapitalize="none" spellcheck="false" class="{{ $inputClass }}" data-contact-email data-email-input>
                        </x-parish.form-field>

                        <x-parish.form-field id="preferred_contact_method" name="preferred_contact_method" label="Preferred contact method" :required="true">
                            <select id="preferred_contact_method" name="preferred_contact_method" required class="{{ $selectClass }}" data-contact-method>
                                <option value="">Select a contact method</option>
                                <option value="phone" @selected(old('preferred_contact_method') === 'phone')>Phone call</option>
                                <option value="sms" @selected(old('preferred_contact_method') === 'sms')>Text message</option>
                                <option value="email" @selected(old('preferred_contact_method') === 'email')>Email</option>
                            </select>
                        </x-parish.form-field>

                        <x-parish.form-field id="client_address" name="client_address" label="Address" :required="true" class="sm:col-span-2">
                            <textarea id="client_address" name="client_address" rows="3" required autocomplete="street-address" autocapitalize="words" data-title-case class="{{ $inputClass }}">{{ old('client_address') }}</textarea>
                        </x-parish.form-field>
                    </div>
                </section>

                <section aria-labelledby="service-selection-heading" class="border border-navy/15 bg-ivory p-6 sm:p-9">
                    <div class="max-w-2xl">
                        <p class="section-label text-muted">02 · Service Selection</p>
                        <h2 id="service-selection-heading" class="mt-4 text-3xl">Choose a service and preferred schedule</h2>
                    </div>

                    <div class="mt-8 grid gap-6 sm:grid-cols-2">
                        <x-parish.form-field id="service_type" name="service_type" label="Service type" :required="true" class="sm:col-span-2">
                            <select id="service_type" name="service_type" required class="{{ $selectClass }}" data-service-select>
                                <option value="">Select Baptism, Wedding, or Funeral</option>
                                @foreach ($services as $serviceKey => $service)
                                    <option value="{{ $serviceKey }}" @selected($selectedService === $serviceKey)>{{ $service['label'] }}</option>
                                @endforeach
                            </select>
                        </x-parish.form-field>

                        <div class="grid items-start gap-6 border-y border-navy/10 py-6 sm:col-span-2 sm:grid-cols-2" data-schedule-fields>
                            <x-parish.form-field id="preferred_date" name="preferred_date" label="Preferred date" :required="true" help="Choose a date from the calendar." class="self-start">
                                <input id="preferred_date" name="preferred_date" type="date" value="{{ old('preferred_date') }}" min="{{ now()->toDateString() }}" required data-preferred-date class="{{ $inputClass }}">
                            </x-parish.form-field>

                            <x-parish.form-field id="preferred_church" name="preferred_church" label="Preferred church / chapel" :required="true" class="self-start">
                                <select id="preferred_church" name="preferred_church" required class="{{ $selectClass }}">
                                    <option value="">Select a location</option>
                                    @foreach ($churches as $church)
                                        <option value="{{ $church }}" @selected(old('preferred_church') === $church)>{{ $church }}</option>
                                    @endforeach
                                </select>
                            </x-parish.form-field>

                            <x-parish.form-field id="preferred_time" name="preferred_time" label="Preferred time" :required="true" help="Select a suggested time or enter another time." class="border-t border-navy/10 pt-6 sm:col-span-2">
                                <div class="grid items-start gap-3 lg:grid-cols-[minmax(0,0.7fr)_minmax(0,1.3fr)]" data-time-controls>
                                    <input id="preferred_time" name="preferred_time" type="time" value="{{ old('preferred_time') }}" step="900" required data-preferred-time class="{{ $inputClass }}">
                                    <div class="grid grid-cols-2 gap-2 min-[460px]:grid-cols-3" aria-label="Suggested time options">
                                        @foreach (['08:00' => '8:00 AM', '09:00' => '9:00 AM', '10:00' => '10:00 AM', '14:00' => '2:00 PM', '15:00' => '3:00 PM', '16:00' => '4:00 PM'] as $timeValue => $timeLabel)
                                            <button type="button" data-time-value="{{ $timeValue }}" class="min-h-12 border border-navy/20 bg-white px-2 text-xs font-medium text-navy transition-colors hover:border-gold hover:bg-gold/10">{{ $timeLabel }}</button>
                                        @endforeach
                                    </div>
                                </div>
                            </x-parish.form-field>
                        </div>

                        <div class="sm:col-span-2 border-l-2 border-gold bg-gold/10 px-4 py-3 text-sm text-navy" aria-live="polite" data-schedule-summary>
                            Choose a preferred date and time.
                        </div>

                        <x-parish.form-field id="additional_notes" name="additional_notes" label="General additional notes / special requests" :optional="true" class="sm:col-span-2">
                            <textarea id="additional_notes" name="additional_notes" rows="4" class="{{ $inputClass }}">{{ old('additional_notes') }}</textarea>
                        </x-parish.form-field>
                    </div>
                </section>

                <section data-service-fields="baptism" @if (! $baptismActive) hidden @endif aria-labelledby="baptism-heading" class="border border-navy/15 bg-ivory p-6 sm:p-9">
                    <div class="max-w-2xl">
                        <p class="section-label text-muted">03 · Baptism Details</p>
                        <h2 id="baptism-heading" class="mt-4 text-3xl">Child information and godparents</h2>
                        <p class="mt-3 text-sm leading-7 text-muted">The preferred baptism date and time are taken from the service schedule above.</p>
                    </div>

                    <div class="mt-8 grid gap-6 sm:grid-cols-2">
                        <x-parish.form-field id="child_full_name" name="service.child_full_name" label="Child's full name" :required="true" class="sm:col-span-2">
                            <input id="child_full_name" name="service[child_full_name]" type="text" value="{{ old('service.child_full_name') }}" autocapitalize="words" data-title-case data-required="true" @required($baptismActive) @disabled(! $baptismActive) class="{{ $inputClass }}">
                        </x-parish.form-field>
                        <x-parish.form-field id="child_date_of_birth" name="service.child_date_of_birth" label="Date of birth" :required="true">
                            <input id="child_date_of_birth" name="service[child_date_of_birth]" type="date" value="{{ old('service.child_date_of_birth') }}" max="{{ now()->toDateString() }}" data-required="true" @required($baptismActive) @disabled(! $baptismActive) class="{{ $inputClass }}">
                        </x-parish.form-field>
                        <x-parish.form-field id="child_place_of_birth" name="service.child_place_of_birth" label="Place of birth" :required="true">
                            <input id="child_place_of_birth" name="service[child_place_of_birth]" type="text" value="{{ old('service.child_place_of_birth') }}" autocapitalize="words" data-title-case data-required="true" @required($baptismActive) @disabled(! $baptismActive) class="{{ $inputClass }}">
                        </x-parish.form-field>
                        <x-parish.form-field id="child_sex" name="service.child_sex" label="Sex" :required="true">
                            <select id="child_sex" name="service[child_sex]" data-required="true" @required($baptismActive) @disabled(! $baptismActive) class="{{ $selectClass }}">
                                <option value="">Select</option>
                                <option value="female" @selected(old('service.child_sex') === 'female')>Female</option>
                                <option value="male" @selected(old('service.child_sex') === 'male')>Male</option>
                            </select>
                        </x-parish.form-field>
                        <x-parish.form-field id="father_name" name="service.father_name" label="Father's name" :required="true">
                            <input id="father_name" name="service[father_name]" type="text" value="{{ old('service.father_name') }}" autocapitalize="words" data-title-case data-required="true" @required($baptismActive) @disabled(! $baptismActive) class="{{ $inputClass }}">
                        </x-parish.form-field>
                        <x-parish.form-field id="mother_name" name="service.mother_name" label="Mother's name" :required="true">
                            <input id="mother_name" name="service[mother_name]" type="text" value="{{ old('service.mother_name') }}" autocapitalize="words" data-title-case data-required="true" @required($baptismActive) @disabled(! $baptismActive) class="{{ $inputClass }}">
                        </x-parish.form-field>
                        <x-parish.form-field id="godfather_name" name="service.godfather_name" label="Godfather's name" :required="true">
                            <input id="godfather_name" name="service[godfather_name]" type="text" value="{{ old('service.godfather_name') }}" autocapitalize="words" data-title-case data-required="true" @required($baptismActive) @disabled(! $baptismActive) class="{{ $inputClass }}">
                        </x-parish.form-field>
                        <x-parish.form-field id="godmother_name" name="service.godmother_name" label="Godmother's name" :required="true">
                            <input id="godmother_name" name="service[godmother_name]" type="text" value="{{ old('service.godmother_name') }}" autocapitalize="words" data-title-case data-required="true" @required($baptismActive) @disabled(! $baptismActive) class="{{ $inputClass }}">
                        </x-parish.form-field>
                        <x-parish.form-field id="additional_godparents" name="service.additional_godparents" label="Additional godparents" :optional="true" class="sm:col-span-2">
                            <textarea id="additional_godparents" name="service[additional_godparents]" rows="3" autocapitalize="words" data-title-case @disabled(! $baptismActive) class="{{ $inputClass }}">{{ old('service.additional_godparents') }}</textarea>
                        </x-parish.form-field>
                        <x-parish.form-field id="baptism_special_requests" name="service.special_requests" label="Baptism special requests" :optional="true">
                            <textarea id="baptism_special_requests" name="service[special_requests]" rows="3" @disabled(! $baptismActive) class="{{ $inputClass }}">{{ old('service.special_requests') }}</textarea>
                        </x-parish.form-field>
                        <x-parish.form-field id="baptism_notes" name="service.notes" label="Notes for parish staff" :optional="true">
                            <textarea id="baptism_notes" name="service[notes]" rows="3" @disabled(! $baptismActive) class="{{ $inputClass }}">{{ old('service.notes') }}</textarea>
                        </x-parish.form-field>
                    </div>

                    <div class="mt-9">
                        @include('client-side.appointments.partials.documents', ['documents' => $services['baptism']['documents'], 'serviceType' => 'baptism', 'active' => $baptismActive])
                    </div>
                </section>

                <section data-service-fields="wedding" @if (! $weddingActive) hidden @endif aria-labelledby="wedding-heading" class="border border-navy/15 bg-ivory p-6 sm:p-9">
                    <div class="max-w-2xl">
                        <p class="section-label text-muted">03 · Wedding Details</p>
                        <h2 id="wedding-heading" class="mt-4 text-3xl">Couple and marriage information</h2>
                        <p class="mt-3 text-sm leading-7 text-muted">The preferred wedding date, ceremony time, and church are taken from the service schedule above.</p>
                    </div>

                    <div class="mt-8 grid gap-6 sm:grid-cols-2">
                        <x-parish.form-field id="bride_full_name" name="service.bride_full_name" label="Bride's full name" :required="true">
                            <input id="bride_full_name" name="service[bride_full_name]" type="text" value="{{ old('service.bride_full_name') }}" autocapitalize="words" data-title-case data-required="true" @required($weddingActive) @disabled(! $weddingActive) class="{{ $inputClass }}">
                        </x-parish.form-field>
                        <x-parish.form-field id="groom_full_name" name="service.groom_full_name" label="Groom's full name" :required="true">
                            <input id="groom_full_name" name="service[groom_full_name]" type="text" value="{{ old('service.groom_full_name') }}" autocapitalize="words" data-title-case data-required="true" @required($weddingActive) @disabled(! $weddingActive) class="{{ $inputClass }}">
                        </x-parish.form-field>
                        <x-parish.form-field id="bride_contact_number" name="service.bride_contact_number" label="Bride's contact number" :required="true">
                            <input id="bride_contact_number" name="service[bride_contact_number]" type="tel" value="{{ old('service.bride_contact_number') }}" inputmode="tel" placeholder="0912 345 6789" data-phone-input data-required="true" @required($weddingActive) @disabled(! $weddingActive) class="{{ $inputClass }}">
                        </x-parish.form-field>
                        <x-parish.form-field id="groom_contact_number" name="service.groom_contact_number" label="Groom's contact number" :required="true">
                            <input id="groom_contact_number" name="service[groom_contact_number]" type="tel" value="{{ old('service.groom_contact_number') }}" inputmode="tel" placeholder="0912 345 6789" data-phone-input data-required="true" @required($weddingActive) @disabled(! $weddingActive) class="{{ $inputClass }}">
                        </x-parish.form-field>
                        <x-parish.form-field id="current_address" name="service.current_address" label="Current address" :required="true" class="sm:col-span-2">
                            <textarea id="current_address" name="service[current_address]" rows="3" autocapitalize="words" data-title-case data-required="true" @required($weddingActive) @disabled(! $weddingActive) class="{{ $inputClass }}">{{ old('service.current_address') }}</textarea>
                        </x-parish.form-field>
                        <x-parish.form-field id="expected_guest_count" name="service.expected_guest_count" label="Expected number of guests" :required="true">
                            <input id="expected_guest_count" name="service[expected_guest_count]" type="number" min="1" max="5000" value="{{ old('service.expected_guest_count') }}" data-required="true" @required($weddingActive) @disabled(! $weddingActive) class="{{ $inputClass }}">
                        </x-parish.form-field>
                        <x-parish.form-field id="civil_status" name="service.civil_status" label="Civil status" :required="true">
                            <select id="civil_status" name="service[civil_status]" data-required="true" @required($weddingActive) @disabled(! $weddingActive) class="{{ $selectClass }}">
                                <option value="">Select</option>
                                <option value="single" @selected(old('service.civil_status') === 'single')>Single</option>
                                <option value="widowed" @selected(old('service.civil_status') === 'widowed')>Widowed</option>
                                <option value="annulled" @selected(old('service.civil_status') === 'annulled')>Previously married, with annulment</option>
                            </select>
                        </x-parish.form-field>
                        <x-parish.form-field id="previous_marriage" name="service.previous_marriage" label="Previous marriage details" :optional="true" class="sm:col-span-2" help="Complete only if applicable.">
                            <textarea id="previous_marriage" name="service[previous_marriage]" rows="3" @disabled(! $weddingActive) class="{{ $inputClass }}">{{ old('service.previous_marriage') }}</textarea>
                        </x-parish.form-field>
                        <x-parish.form-field id="marriage_license_status" name="service.marriage_license_status" label="Marriage license status" :required="true">
                            <select id="marriage_license_status" name="service[marriage_license_status]" data-required="true" @required($weddingActive) @disabled(! $weddingActive) class="{{ $selectClass }}">
                                <option value="">Select</option>
                                <option value="not_started" @selected(old('service.marriage_license_status') === 'not_started')>Not started</option>
                                <option value="in_process" @selected(old('service.marriage_license_status') === 'in_process')>In process</option>
                                <option value="secured" @selected(old('service.marriage_license_status') === 'secured')>Secured</option>
                            </select>
                        </x-parish.form-field>
                        <x-parish.form-field id="preferred_priest" name="service.preferred_priest" label="Preferred priest / minister" :optional="true">
                            <input id="preferred_priest" name="service[preferred_priest]" type="text" value="{{ old('service.preferred_priest') }}" autocapitalize="words" data-title-case @disabled(! $weddingActive) class="{{ $inputClass }}">
                        </x-parish.form-field>
                        <x-parish.form-field id="principal_sponsors" name="service.principal_sponsors" label="Principal sponsors" :optional="true">
                            <textarea id="principal_sponsors" name="service[principal_sponsors]" rows="3" autocapitalize="words" data-title-case @disabled(! $weddingActive) class="{{ $inputClass }}">{{ old('service.principal_sponsors') }}</textarea>
                        </x-parish.form-field>
                        <x-parish.form-field id="witnesses" name="service.witnesses" label="Witnesses" :optional="true">
                            <textarea id="witnesses" name="service[witnesses]" rows="3" autocapitalize="words" data-title-case @disabled(! $weddingActive) class="{{ $inputClass }}">{{ old('service.witnesses') }}</textarea>
                        </x-parish.form-field>
                    </div>

                    <div class="mt-9">
                        @include('client-side.appointments.partials.documents', ['documents' => $services['wedding']['documents'], 'serviceType' => 'wedding', 'active' => $weddingActive])
                    </div>
                </section>

                <section data-service-fields="funeral" @if (! $funeralActive) hidden @endif aria-labelledby="funeral-heading" class="border border-navy/15 bg-ivory p-6 sm:p-9">
                    <div class="max-w-2xl">
                        <p class="section-label text-muted">03 · Funeral Details</p>
                        <h2 id="funeral-heading" class="mt-4 text-3xl">Deceased and family information</h2>
                        <p class="mt-3 text-sm leading-7 text-muted">The preferred funeral or Mass date and time are taken from the service schedule above.</p>
                    </div>

                    <div class="mt-8 grid gap-6 sm:grid-cols-2">
                        <x-parish.form-field id="deceased_full_name" name="service.deceased_full_name" label="Deceased's full name" :required="true" class="sm:col-span-2">
                            <input id="deceased_full_name" name="service[deceased_full_name]" type="text" value="{{ old('service.deceased_full_name') }}" autocapitalize="words" data-title-case data-required="true" @required($funeralActive) @disabled(! $funeralActive) class="{{ $inputClass }}">
                        </x-parish.form-field>
                        <x-parish.form-field id="deceased_date_of_birth" name="service.date_of_birth" label="Date of birth" :optional="true">
                            <input id="deceased_date_of_birth" name="service[date_of_birth]" type="date" value="{{ old('service.date_of_birth') }}" max="{{ now()->toDateString() }}" @disabled(! $funeralActive) class="{{ $inputClass }}">
                        </x-parish.form-field>
                        <x-parish.form-field id="date_of_death" name="service.date_of_death" label="Date of death" :required="true">
                            <input id="date_of_death" name="service[date_of_death]" type="date" value="{{ old('service.date_of_death') }}" max="{{ now()->toDateString() }}" data-required="true" @required($funeralActive) @disabled(! $funeralActive) class="{{ $inputClass }}">
                        </x-parish.form-field>
                        <x-parish.form-field id="place_of_death" name="service.place_of_death" label="Place of death" :required="true">
                            <input id="place_of_death" name="service[place_of_death]" type="text" value="{{ old('service.place_of_death') }}" autocapitalize="words" data-title-case data-required="true" @required($funeralActive) @disabled(! $funeralActive) class="{{ $inputClass }}">
                        </x-parish.form-field>
                        <x-parish.form-field id="deceased_age" name="service.age" label="Age" :required="true">
                            <input id="deceased_age" name="service[age]" type="number" min="0" max="150" value="{{ old('service.age') }}" data-required="true" @required($funeralActive) @disabled(! $funeralActive) class="{{ $inputClass }}">
                        </x-parish.form-field>
                        <x-parish.form-field id="residence_address" name="service.residence_address" label="Address / residence" :required="true" class="sm:col-span-2">
                            <textarea id="residence_address" name="service[residence_address]" rows="3" autocapitalize="words" data-title-case data-required="true" @required($funeralActive) @disabled(! $funeralActive) class="{{ $inputClass }}">{{ old('service.residence_address') }}</textarea>
                        </x-parish.form-field>
                        <x-parish.form-field id="funeral_home" name="service.funeral_home" label="Funeral home" :required="true">
                            <input id="funeral_home" name="service[funeral_home]" type="text" value="{{ old('service.funeral_home') }}" autocapitalize="words" data-title-case data-required="true" @required($funeralActive) @disabled(! $funeralActive) class="{{ $inputClass }}">
                        </x-parish.form-field>
                        <x-parish.form-field id="wake_location" name="service.wake_location" label="Location of wake" :required="true">
                            <input id="wake_location" name="service[wake_location]" type="text" value="{{ old('service.wake_location') }}" autocapitalize="words" data-title-case data-required="true" @required($funeralActive) @disabled(! $funeralActive) class="{{ $inputClass }}">
                        </x-parish.form-field>
                        <x-parish.form-field id="cemetery_location" name="service.cemetery_location" label="Burial / cemetery location" :required="true">
                            <input id="cemetery_location" name="service[cemetery_location]" type="text" value="{{ old('service.cemetery_location') }}" autocapitalize="words" data-title-case data-required="true" @required($funeralActive) @disabled(! $funeralActive) class="{{ $inputClass }}">
                        </x-parish.form-field>
                        <x-parish.form-field id="expected_attendee_count" name="service.expected_attendee_count" label="Expected number of attendees" :optional="true">
                            <input id="expected_attendee_count" name="service[expected_attendee_count]" type="number" min="1" max="10000" value="{{ old('service.expected_attendee_count') }}" @disabled(! $funeralActive) class="{{ $inputClass }}">
                        </x-parish.form-field>
                    </div>

                    <div class="mt-9 border-t border-navy/10 pt-8">
                        <h3 class="text-2xl">Family / contact person</h3>
                        <div class="mt-6 grid gap-6 sm:grid-cols-2">
                            <x-parish.form-field id="funeral_contact_full_name" name="service.contact_full_name" label="Full name" :required="true">
                                <input id="funeral_contact_full_name" name="service[contact_full_name]" type="text" value="{{ old('service.contact_full_name') }}" autocapitalize="words" data-title-case data-required="true" @required($funeralActive) @disabled(! $funeralActive) class="{{ $inputClass }}">
                            </x-parish.form-field>
                            <x-parish.form-field id="contact_relationship" name="service.contact_relationship" label="Relationship to deceased" :required="true">
                                <input id="contact_relationship" name="service[contact_relationship]" type="text" value="{{ old('service.contact_relationship') }}" autocapitalize="words" data-title-case data-required="true" @required($funeralActive) @disabled(! $funeralActive) class="{{ $inputClass }}">
                            </x-parish.form-field>
                            <x-parish.form-field id="funeral_contact_number" name="service.contact_number" label="Contact number" :required="true">
                                <input id="funeral_contact_number" name="service[contact_number]" type="tel" value="{{ old('service.contact_number') }}" inputmode="tel" placeholder="0912 345 6789" data-phone-input data-required="true" @required($funeralActive) @disabled(! $funeralActive) class="{{ $inputClass }}">
                            </x-parish.form-field>
                            <x-parish.form-field id="funeral_contact_email" name="service.contact_email" label="Email" :optional="true">
                                <input id="funeral_contact_email" name="service[contact_email]" type="email" value="{{ old('service.contact_email') }}" autocapitalize="none" spellcheck="false" data-email-input @disabled(! $funeralActive) class="{{ $inputClass }}">
                            </x-parish.form-field>
                            <x-parish.form-field id="funeral_contact_address" name="service.contact_address" label="Address" :required="true" class="sm:col-span-2">
                                <textarea id="funeral_contact_address" name="service[contact_address]" rows="3" autocapitalize="words" data-title-case data-required="true" @required($funeralActive) @disabled(! $funeralActive) class="{{ $inputClass }}">{{ old('service.contact_address') }}</textarea>
                            </x-parish.form-field>
                            <x-parish.form-field id="funeral_special_requests" name="service.special_requests" label="Funeral special requests" :optional="true">
                                <textarea id="funeral_special_requests" name="service[special_requests]" rows="3" @disabled(! $funeralActive) class="{{ $inputClass }}">{{ old('service.special_requests') }}</textarea>
                            </x-parish.form-field>
                            <x-parish.form-field id="priest_notes" name="service.priest_notes" label="Notes for the priest / church staff" :optional="true">
                                <textarea id="priest_notes" name="service[priest_notes]" rows="3" @disabled(! $funeralActive) class="{{ $inputClass }}">{{ old('service.priest_notes') }}</textarea>
                            </x-parish.form-field>
                        </div>
                    </div>

                    <div class="mt-9">
                        @include('client-side.appointments.partials.documents', ['documents' => $services['funeral']['documents'], 'serviceType' => 'funeral', 'active' => $funeralActive])
                    </div>
                </section>

                <div class="flex flex-col-reverse gap-4 border border-navy/15 bg-navy p-6 text-ivory sm:flex-row sm:items-center sm:justify-between sm:p-8">
                    <p class="max-w-xl text-sm leading-7 text-ivory/65">Submitting this form creates a request with a unique reference number. Parish staff will review it before the appointment is confirmed.</p>
                    <button type="submit" class="inline-flex min-h-12 shrink-0 items-center justify-center gap-4 bg-gold px-7 py-3 text-sm font-medium text-navy transition-colors hover:bg-[#c9aa63]">
                        Submit appointment request <x-parish.icon name="arrow" class="size-4" />
                    </button>
                </div>
            </div>
        </form>
    </section>
@endsection
