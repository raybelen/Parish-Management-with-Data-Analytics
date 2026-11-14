@extends('client-side.layouts.parish')

@section('content')
    @php
        $requestedService = request()->query('service', '');
        $selectedService = old('service_type', is_string($requestedService) && isset($services[$requestedService]) && ($services[$requestedService]['active'] ?? false) ? $requestedService : '');

        $inputClass = 'min-h-12 w-full rounded-lg border border-navy/20 bg-white px-4 py-3 text-sm text-navy outline-none transition-colors placeholder:text-muted/55 focus:border-gold focus:ring-1 focus:ring-gold disabled:bg-navy/5 disabled:text-muted';
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
                    <p class="mt-5 text-base leading-8 text-muted">Add your contact details, choose a service and time, then review your request before sending it.</p>
                </div>
                <div class="border-l-2 border-gold pl-5 text-sm leading-7 text-muted">
                    <p><span class="font-semibold text-red-700">*</span> indicates a required field.</p>
                    <p>Optional details can be provided if available.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-[#eae5da] py-12 sm:py-16">
        <form method="POST" action="{{ route('appointments.store') }}" class="section-wrap max-w-6xl" data-appointment-form data-availability-url="{{ route('appointments.availability') }}">
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

            <nav aria-label="Booking form sections" class="mb-6 flex flex-wrap gap-2">
                <a href="#client-information-heading" class="inline-flex min-h-11 items-center rounded-full border border-navy/15 bg-white px-4 text-sm font-medium text-navy hover:border-gold focus-visible:outline-2 focus-visible:outline-navy">1. Contact details</a>
                <a href="#service-selection-heading" class="inline-flex min-h-11 items-center rounded-full border border-navy/15 bg-white px-4 text-sm font-medium text-navy hover:border-gold focus-visible:outline-2 focus-visible:outline-navy">2. Service & schedule</a>
                <a href="#payment-option-heading" class="inline-flex min-h-11 items-center rounded-full border border-navy/15 bg-white px-4 text-sm font-medium text-navy hover:border-gold focus-visible:outline-2 focus-visible:outline-navy">3. Payment option</a>
                <a href="{{ route('appointments.lookup.create') }}" class="inline-flex min-h-11 items-center px-4 text-sm text-navy underline decoration-gold underline-offset-4">Already have an appointment?</a>
            </nav>

            <div class="grid gap-8">
                <section aria-labelledby="client-information-heading" class="rounded-2xl border border-navy/10 bg-white p-6 shadow-sm sm:p-9">
                    <div class="max-w-2xl">
                        <p class="section-label text-muted">01 · Client Information</p>
                        <h2 id="client-information-heading" class="mt-4 scroll-mt-32 text-3xl">How may we contact you?</h2>
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

                        <x-parish.form-field id="client_address" name="client_address" label="Address" :required="true" help="Choose your location, then add your street or house number." class="sm:col-span-2">
                            <x-parish.address-field id="client_address" name="client_address" label="Address" :value="old('client_address')" :required="true" :input-class="$inputClass" :select-class="$selectClass" />
                        </x-parish.form-field>
                    </div>
                </section>

                <section aria-labelledby="service-selection-heading" class="rounded-2xl border border-navy/10 bg-white p-6 shadow-sm sm:p-9">
                    <div class="max-w-2xl">
                        <p class="section-label text-muted">02 · Service Selection</p>
                        <h2 id="service-selection-heading" class="mt-4 scroll-mt-32 text-3xl">Choose a service and preferred schedule</h2>
                    </div>

                    <div class="mt-8 grid gap-6 sm:grid-cols-2">
                        <x-parish.form-field id="service_type" name="service_type" label="Service type" :required="true" class="sm:col-span-2">
                            <select id="service_type" name="service_type" required class="{{ $selectClass }}" data-service-select>
                                <option value="">Select Baptism, Wedding, or Funeral</option>
                                @foreach ($services as $serviceKey => $service)
                                    @if($service['active'] ?? true)
                                    <option value="{{ $serviceKey }}" data-fee-cents="{{ $service['fee_cents'] ?? 0 }}" @selected($selectedService === $serviceKey)>{{ $service['label'] }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </x-parish.form-field>


                        <x-parish.group-baptism-fields :sessions="$groupSessions" :select-class="$selectClass" :selected-service="$selectedService" />

                           <div class="grid items-start gap-6 border-y border-navy/10 py-6 sm:col-span-2 sm:grid-cols-2" data-schedule-fields>
                            <x-parish.form-field id="preferred_date" name="preferred_date" label="Preferred date" :required="true" help="Choose a date from the calendar." class="self-start">
                                <input id="preferred_date" name="preferred_date" type="date" value="{{ old('preferred_date') }}" min="{{ now()->toDateString() }}" required data-preferred-date class="{{ $inputClass }}">
                            </x-parish.form-field>

                            <x-parish.form-field id="preferred_church" name="preferred_church" label="Preferred church / chapel" :required="true" class="self-start">
                                <select id="preferred_church" name="preferred_church" data-service-venue @disabled($selectedService === 'others') @required($selectedService !== 'others') class="{{ $selectClass }}">
                                    <option value="{{ \App\Models\Appointment::OTHER_SERVICE_LOCATION }}" data-other-service-venue @selected($selectedService === 'others') @if($selectedService !== 'others') hidden disabled @endif>Use the service address below</option>
                                    <option value="">{{ $churches === [] ? 'No venues currently available' : 'Select a location' }}</option>
                                    @foreach ($churches as $church)
                                        <option value="{{ $church }}" @selected(old('preferred_church') === $church)>{{ $church }}</option>
                                    @endforeach
                                </select>
                                @if ($churches === [])
                                    <p class="text-xs leading-5 text-red-700">No church or chapel is currently accepting appointment requests. Please contact the parish office.</p>
                                @endif
                            </x-parish.form-field>

                            <x-parish.form-field id="preferred_time" name="preferred_time" label="Preferred time" :required="true" help="Choose a suggested time or enter a different time in 15-minute intervals." class="border-t border-navy/10 pt-6 sm:col-span-2">
                                <div class="border border-navy/10 bg-white/45 p-4 sm:p-5" data-time-controls>
                                    <div class="grid gap-5">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <p class="text-[11px] font-semibold tracking-[0.14em] uppercase text-muted">Suggested times</p>
                                            <p class="text-[11px] text-muted" data-time-availability-hint role="status">Choose a service, date, and venue first.</p>
                                        </div>

                                        <div class="flex flex-wrap gap-2" aria-label="Suggested time options">
                                            @foreach (['08:00' => '8:00 AM', '09:00' => '9:00 AM', '10:00' => '10:00 AM', '13:00' => '1:00 PM', '14:00' => '2:00 PM', '15:00' => '3:00 PM'] as $timeValue => $timeLabel)
                                                <button type="button" data-time-value="{{ $timeValue }}" disabled aria-pressed="false" class="min-h-12 min-w-[calc(50%_-_0.25rem)] flex-1 basis-[calc(50%_-_0.25rem)] border border-navy/15 bg-white px-3 text-sm font-medium text-navy shadow-[0_1px_0_rgb(19_35_63/0.04)] transition-[border-color,background-color,color,transform,box-shadow] enabled:hover:-translate-y-0.5 enabled:hover:border-gold enabled:hover:bg-gold/10 enabled:hover:shadow-sm disabled:cursor-not-allowed disabled:opacity-40 disabled:shadow-none sm:min-w-[calc(25%_-_0.375rem)] sm:basis-[calc(25%_-_0.375rem)] lg:min-w-0 lg:basis-0 aria-pressed:border-navy aria-pressed:bg-navy aria-pressed:text-ivory aria-pressed:shadow-sm">{{ $timeLabel }}</button>
                                            @endforeach
                                        </div>

                                        <div class="flex flex-col gap-4 border-t border-navy/10 pt-5 sm:flex-row sm:items-center sm:justify-between">
                                            <div>
                                                <label for="preferred_time" class="text-sm font-medium text-navy">Need another time?</label>
                                                <p class="mt-1 text-xs leading-5 text-muted">Enter a time in 15-minute intervals.</p>
                                            </div>
                                            <input id="preferred_time" name="preferred_time" type="time" value="{{ old('preferred_time') }}" step="900" required data-preferred-time class="{{ $inputClass }} border-navy/25 bg-white text-base font-medium focus:bg-white sm:w-64">
                                        </div>
                                    </div>
                                </div>
                            </x-parish.form-field>
                        </div>

                        <p id="schedule-availability" data-availability-status role="status" aria-live="polite" hidden class="sm:col-span-2 border-l-4 border-red-700 bg-red-50 px-5 py-4 text-sm text-red-900"></p>

                        <div class="sm:col-span-2 border-l-2 border-gold bg-gold/10 px-4 py-3 text-sm text-navy" aria-live="polite" data-schedule-summary hidden></div>

                        <x-parish.form-field id="additional_notes" name="additional_notes" label="Notes or special requests" :optional="true" class="sm:col-span-2">
                            <textarea id="additional_notes" name="additional_notes" rows="4" class="{{ $inputClass }}">{{ old('additional_notes') }}</textarea>
                        </x-parish.form-field>
                    </div>
                </section>

                @foreach (['baptism' => 'Baptism details', 'wedding' => 'Marriage details', 'funeral' => 'Death and funeral details', 'confirmation' => 'Confirmation details'] as $serviceKey => $heading)
                    @php($active = $selectedService === $serviceKey)
                    <section data-service-fields="{{ $serviceKey }}" @if (! $active) hidden @endif aria-labelledby="{{ $serviceKey }}-heading" class="rounded-2xl border border-navy/10 bg-white p-6 shadow-sm sm:p-9">
                        <p class="section-label text-muted">03 · Service Details</p>
                        <h2 id="{{ $serviceKey }}-heading" class="mt-4 scroll-mt-32 text-3xl">{{ $heading }}</h2>
                        <p class="mt-3 text-sm leading-7 text-muted">Your chosen date, time, and church also apply to these details.</p>
                        <x-appointment-service-fields :service-type="$serviceKey" :active="$active" :input-class="$inputClass" :select-class="$selectClass" />
                        <div class="mt-9">
                            @include('client-side.appointments.partials.documents', ['documents' => $services[$serviceKey]['documents'], 'serviceType' => $serviceKey, 'active' => $active])
                        </div>
                    </section>
                @endforeach

                @foreach($services as $serviceKey => $service)
                    @if(!in_array($serviceKey, ['baptism', 'wedding', 'funeral', 'confirmation']) && ($service['active'] ?? true))
                        <section data-service-fields="{{ $serviceKey }}" @if($selectedService !== $serviceKey) hidden @endif class="rounded-2xl border border-navy/10 bg-white p-6 shadow-sm sm:p-9">
                            <h2 class="mb-5 text-2xl">{{ $service['label'] }} details</h2>
                            @if(config('appointments.details.'.$serviceKey))
                                <p class="text-sm leading-7 text-muted">The parish office will review your request and assign a priest before approval. Enter the actual service location below.</p>
                                <x-appointment-service-fields :service-type="$serviceKey" :active="$selectedService === $serviceKey" :input-class="$inputClass" :select-class="$selectClass" />
                            @else
                                <label class="grid gap-2 text-sm"><span>Recipient full name <span class="text-red-700" aria-hidden="true">*</span></span><input name="service[recipient_full_name]" value="{{ old('service.recipient_full_name') }}" data-required="true" @required($selectedService === $serviceKey) @disabled($selectedService !== $serviceKey) class="{{ $inputClass }}"></label>
                            @endif
                            @include('client-side.appointments.partials.documents', ['documents' => $service['documents'], 'serviceType' => $serviceKey, 'active' => $selectedService === $serviceKey])
                        </section>
                    @endif
                @endforeach

                <section aria-labelledby="payment-option-heading" class="rounded-2xl border border-navy/10 bg-white p-6 shadow-sm sm:p-9">
                    <p class="section-label text-muted">04 · Payment Option</p>
                    <h2 id="payment-option-heading" class="mt-4 scroll-mt-32 text-3xl">How would you like to pay?</h2>
                    <p class="mt-3 max-w-3xl text-sm leading-7 text-muted">The service fee is payable separately. Payment is not required to confirm your appointment and does not affect document verification or your reserved schedule.</p>
                    <div class="mt-5" data-offering-fields @if($selectedService !== 'others') hidden @endif>
                        <label for="offering_amount" class="block text-sm font-semibold">Voluntary offering (₱)</label>
                        <p id="offering-help" class="mt-2 text-sm text-muted">Give any amount you can. Enter 0 if you cannot give an offering. No payment is needed for ₱0. Online payments require a whole-peso amount.</p>
                        <input id="offering_amount" name="offering_amount" type="number" min="0" max="999999.99" step="0.01" value="{{ old('offering_amount', '0') }}" aria-describedby="offering-help" class="{{ $inputClass }}" @disabled($selectedService !== 'others') @required($selectedService === 'others')>
                        @error('offering_amount')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                    <p class="mt-5 rounded-lg bg-ivory p-4 text-sm" aria-live="polite"><span data-service-fee-label>{{ $selectedService === 'others' ? 'Voluntary offering' : 'Service fee' }}</span>: <strong data-service-fee>{{ $selectedService === 'others' ? '₱'.number_format((float) old('offering_amount', 0), 2) : (isset($services[$selectedService]) ? '₱'.number_format(($services[$selectedService]['fee_cents'] ?? 0) / 100, 2) : 'Select a service to see the fee.') }}</strong></p>

                    <fieldset class="mt-7 grid gap-4 sm:grid-cols-2">
                        <legend class="sr-only">Payment option</legend>
                        <label class="flex min-h-36 cursor-pointer gap-4 rounded-xl border border-navy/15 bg-ivory p-5 transition-colors has-checked:border-gold has-checked:bg-gold/10">
                            <input type="radio" name="payment_option" value="online" required data-payment-label="Online payment" @checked(old('payment_option') === 'online') @disabled(! $xenditSandboxAvailable) class="mt-1 size-5 shrink-0 accent-[#9b7b34]">
                            <span>
                                <span class="block font-semibold text-navy">Online payment</span>
                                <span class="mt-2 block text-sm leading-6 text-muted">Choose from the available payment methods at checkout. Test transaction.</span>
                                @unless($xenditSandboxAvailable)
                                    <span class="mt-2 block text-xs font-medium text-amber-800">Online payment is temporarily unavailable. Please pay at the parish office.</span>
                                @endunless
                            </span>
                        </label>

                        <label class="flex min-h-36 cursor-pointer gap-4 rounded-xl border border-navy/15 bg-ivory p-5 transition-colors has-checked:border-gold has-checked:bg-gold/10">
                            <input type="radio" name="payment_option" value="walk_in" required data-payment-label="Walk-in at the parish office" @checked(old('payment_option', 'walk_in') === 'walk_in') class="mt-1 size-5 shrink-0 accent-[#9b7b34]">
                            <span>
                                <span class="block font-semibold text-navy">Walk-in at the parish office</span>
                                <span class="mt-2 block text-sm leading-6 text-muted">Pay in person at the parish office. Staff will record your payment and provide a receipt.</span>
                            </span>
                        </label>
                    </fieldset>

                    @error('payment_option')
                        <p class="mt-4 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                    <p class="mt-5 border-l-4 border-sky-700 bg-sky-50 px-4 py-3 text-sm leading-6 text-sky-950"><strong>Payment processing:</strong> Your balance updates after payment confirmation.</p>
                </section>

                <div class="flex flex-col-reverse gap-4 border border-navy/15 bg-navy p-6 text-ivory sm:flex-row sm:items-center sm:justify-between sm:p-8">
                    <p class="max-w-xl text-sm leading-7 text-ivory/65">Submitting this form creates a request with a unique reference number. Parish staff will review it before the appointment is confirmed.</p>
                    <button type="submit" data-review-appointment class="inline-flex min-h-12 shrink-0 items-center justify-center gap-4 bg-gold px-7 py-3 text-sm font-medium text-navy transition-colors hover:bg-[#c9aa63]">
                        Review appointment request <x-parish.icon name="arrow" class="size-4" />
                    </button>
                </div>
            </div>
        </form>

        <x-parish.dialog id="confirm-appointment-dialog" title="Check your information" aria-describedby="confirm-appointment-description">
            <p id="confirm-appointment-description">Please check these appointment details before submitting your request.</p>

            <dl class="mt-6 divide-y divide-navy/10 border-y border-navy/10 text-sm">
                <div class="grid gap-1 py-4 sm:grid-cols-[9rem_1fr] sm:gap-5">
                    <dt class="font-medium text-navy">Client</dt>
                    <dd data-review-client></dd>
                </div>
                <div class="grid gap-1 py-4 sm:grid-cols-[9rem_1fr] sm:gap-5">
                    <dt class="font-medium text-navy">Contact</dt>
                    <dd data-review-contact></dd>
                </div>
                <div class="grid gap-1 py-4 sm:grid-cols-[9rem_1fr] sm:gap-5">
                    <dt class="font-medium text-navy">Address</dt>
                    <dd data-review-address></dd>
                </div>
                <div class="grid gap-1 py-4 sm:grid-cols-[9rem_1fr] sm:gap-5">
                    <dt class="font-medium text-navy">Service</dt>
                    <dd data-review-service></dd>
                </div>
                <div class="grid gap-1 py-4 sm:grid-cols-[9rem_1fr] sm:gap-5">
                    <dt class="font-medium text-navy">Schedule</dt>
                    <dd data-review-schedule></dd>
                </div>
                <div class="grid gap-1 py-4 sm:grid-cols-[9rem_1fr] sm:gap-5">
                    <dt class="font-medium text-navy">Service location</dt>
                    <dd data-review-church></dd>
                </div>
                <div class="grid gap-1 py-4 sm:grid-cols-[9rem_1fr] sm:gap-5">
                    <dt class="font-medium text-navy">Payment option</dt>
                    <dd data-review-payment></dd>
                </div>
                <div class="grid gap-1 py-4 sm:grid-cols-[9rem_1fr] sm:gap-5">
                    <dt class="font-medium text-navy" data-service-fee-label>Service fee</dt>
                    <dd data-review-fee></dd>
                </div>
            </dl>

            <p class="mt-5 border-l-2 border-gold bg-gold/10 px-5 py-4 text-sm leading-6 text-navy">This creates a pending request for parish review. It does not confirm the appointment yet.</p>

            <div class="mt-7 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button type="button" data-close-dialog class="inline-flex min-h-12 items-center justify-center border border-navy/20 px-6 py-3 text-sm font-medium text-navy transition-colors hover:border-gold hover:bg-gold/10">
                    Go back and edit
                </button>
                <button type="button" data-confirm-appointment class="inline-flex min-h-12 items-center justify-center bg-gold px-6 py-3 text-sm font-medium text-navy transition-colors hover:bg-[#c9aa63]">
                    Confirm and submit
                </button>
            </div>
        </x-parish.dialog>
    </section>
@endsection
