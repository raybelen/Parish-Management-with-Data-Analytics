<dialog data-schedule-dialog class="calendar-dialog calendar-schedule-dialog" aria-labelledby="schedule-dialog-title" aria-describedby="schedule-dialog-help">
    <header class="sticky top-0 z-20 flex items-start justify-between gap-4 border-b border-navy/10 bg-white px-5 py-4 sm:px-7">
        <div>
            <h2 id="schedule-dialog-title" class="text-2xl">Add schedule</h2>
            <p id="schedule-dialog-help" class="mt-1 text-sm text-muted">Choose a category, then set when and where it happens.</p>
        </div>
        <button type="button" class="admin-secondary shrink-0 px-3" data-dialog-close aria-label="Close schedule details">✕</button>
    </header>
    <div data-schedule-details class="grid gap-4 p-5 sm:p-7"></div>
    <form data-schedule-form>
        @csrf
        <div class="grid gap-6 p-5 sm:p-7">
            <p data-schedule-errors role="alert" tabindex="-1" class="calendar-errors" hidden></p>
            <section class="grid gap-4" aria-labelledby="schedule-basics-heading">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 id="schedule-basics-heading" class="font-sans text-sm font-semibold">Event details</h3>
                    <p class="text-xs text-muted"><span class="text-red-700">*</span> Required</p>
                </div>
                <label class="grid gap-2 text-sm" for="schedule-category">
                    <span class="font-medium">Category <span class="text-red-700">*</span></span>
                    <select id="schedule-category" class="admin-input" name="category" required aria-describedby="schedule-category-help" autofocus>
                        <option value="">Select category</option>
                        <option value="Group Wedding">Group Wedding</option>
                        <option value="Group Confirmation">Group Confirmation</option>
                        <option value="Group Baptism">Group Baptism</option>
                        @foreach(collect(['Meeting', 'Liturgy', 'Formation', 'Community', 'Other'])->merge($categories)->reject(fn ($category) => in_array($category, ['Group Baptism', 'Group Wedding', 'Group Confirmation', '__custom__'], true))->unique() as $category)
                            <option value="{{ $category }}">{{ $category }}</option>
                        @endforeach
                        <option value="__custom__">Add a custom category…</option>
                    </select>
                    <span id="schedule-category-help" class="text-xs text-muted">Choose Group Baptism, Group Wedding, or Group Confirmation to open session bookings.</span>
                </label>
                <label data-custom-category-field class="grid gap-2 text-sm" hidden>
                    <span class="font-medium">Custom category <span class="text-red-700">*</span></span>
                    <input data-custom-category class="admin-input" maxlength="80" placeholder="e.g. Pastoral visit" disabled>
                </label>
                <label class="grid gap-2 text-sm"><span class="font-medium">Event title <span class="text-red-700">*</span></span><input class="admin-input" name="title" required maxlength="255" placeholder="e.g. Parish council meeting"></label>
            </section>

            <section class="grid gap-4 border-t border-navy/10 pt-5" aria-labelledby="schedule-time-heading">
                <div class="flex flex-wrap items-center justify-between gap-2"><h3 id="schedule-time-heading" class="font-sans text-sm font-semibold">Date & location</h3><span class="text-xs text-muted">{{ config('office.timezone') }}</span></div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="grid gap-2 text-sm"><span class="font-medium">Starts <span class="text-red-700">*</span></span><input class="admin-input min-w-0" name="starts_at" type="datetime-local" required></label>
                    <label class="grid gap-2 text-sm"><span class="font-medium">Ends <span class="text-red-700">*</span></span><input class="admin-input min-w-0" name="ends_at" type="datetime-local" required aria-describedby="schedule-duration-help"></label>
                </div>
                <p id="schedule-duration-help" data-schedule-duration class="text-xs text-muted">Choose the start and end of the activity.</p>
                <div class="grid gap-2 text-sm" data-location-combobox>
                    <label for="schedule-location" class="font-medium">Location <span data-group-location-required class="text-red-700" hidden>*</span></label>
                    <div class="relative">
                        <input id="schedule-location" class="admin-input w-full pr-12" name="location" maxlength="255" placeholder="Select or type a location" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="schedule-location-options" aria-describedby="schedule-location-help" autocomplete="off">
                        <button type="button" data-location-toggle class="absolute right-1 top-1 flex size-10 items-center justify-center rounded-lg hover:bg-slate-100" aria-label="Show locations" aria-controls="schedule-location-options" aria-expanded="false" tabindex="-1">
                            <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m5 7.5 5 5 5-5" /></svg>
                        </button>
                        <div data-location-popup class="absolute inset-x-0 top-full z-30 mt-1 max-h-60 overflow-y-auto rounded-xl border border-navy/15 bg-white p-1 shadow-lg" hidden>
                            <ul id="schedule-location-options" role="listbox" aria-label="Parish locations">
                                @foreach($churches as $church)
                                    <li id="schedule-location-option-{{ $loop->index }}" role="option" aria-selected="false" data-location-option data-value="{{ $church }}" class="cursor-pointer rounded-lg px-3 py-3 text-sm break-words hover:bg-slate-100">{{ $church }}</li>
                                @endforeach
                            </ul>
                            <p data-location-empty class="px-3 py-3 text-xs leading-5 text-muted" role="status" hidden></p>
                        </div>
                    </div>
                    <span id="schedule-location-help" class="text-xs text-muted" data-schedule-location-help>Optional. Choose a parish venue or type another location in this field.</span>
                </div>
                <input type="hidden" name="priest_required" value="0">
                <label data-priest-required-control class="flex items-start gap-3 rounded-xl border border-navy/10 bg-slate-50 p-4 text-sm">
                    <input class="mt-1 size-4 shrink-0 accent-navy" type="checkbox" name="priest_required" value="1">
                    <span><span class="block font-semibold">Requires a priest</span><span class="mt-1 block text-xs leading-5 text-muted">Assign an available priest automatically and use a one-hour schedule.</span></span>
                </label>
            </section>

            <fieldset data-group-session-settings class="grid gap-4 rounded-xl border border-gold/40 bg-ivory p-4 sm:p-5" hidden disabled>
                <legend class="px-2 font-sans text-sm font-semibold">Group session registration</legend>
                <p class="text-sm leading-6 text-muted">Use the date above for the ceremony itself. Choose an available ceremony date, with no participant limit.</p>
                <input type="hidden" name="group_booking_open" value="0">
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-navy/15 bg-white p-4 text-sm">
                    <input type="checkbox" name="group_booking_open" value="1" class="mt-1 size-5 shrink-0 accent-navy" aria-describedby="schedule-registration-help">
                    <span><span class="block font-semibold">Open for bookings</span><span id="schedule-registration-help" class="mt-1 block text-xs leading-5 text-muted" data-registration-help>Leave off to keep registration closed. Turn on to let parishioners book as soon as you save.</span></span>
                </label>
                <p class="text-xs leading-5 text-muted">Closing registration keeps existing bookings. Families use the same appointment form and document requirements.</p>
                <label class="grid gap-2 text-sm"><span class="font-medium">Fee per family or couple <span class="font-normal text-muted">(optional)</span></span><input class="admin-input" type="number" name="group_fee" min="0" step="0.01" max="99999999.99" placeholder="Use the standard service fee"><span class="text-xs text-muted">Amount in PHP. Leave blank for the standard fee; enter 0 for no fee.</span></label>
            </fieldset>

            <fieldset data-recurrence-fields class="min-w-0">
                <details class="rounded-xl border border-navy/10 p-4">
                    <summary class="cursor-pointer text-sm font-semibold">Repeat schedule <span class="ml-2 font-normal text-muted" data-recurrence-summary>Does not repeat</span></summary>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <label class="grid gap-2 text-sm">Frequency<select class="admin-input" name="recurrence">@foreach(['none' => 'Does not repeat', 'daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'yearly' => 'Yearly'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                        <label data-recurrence-until class="grid gap-2 text-sm" hidden><span>Repeat through <span class="text-red-700">*</span></span><input class="admin-input" type="date" name="recurrence_until"></label>
                        <p class="text-xs leading-5 text-muted sm:col-span-2">Up to 366 occurrences. Each date can be edited separately. Monthly and yearly schedules use the last valid day when needed.</p>
                    </div>
                </details>
            </fieldset>
            <p data-occurrence-notice class="rounded-xl bg-slate-50 p-4 text-sm text-muted" hidden>Changes apply only to this occurrence, including rescheduling or cancellation.</p>
            <details data-schedule-extra class="rounded-xl border border-navy/10 p-4">
                <summary class="cursor-pointer text-sm font-semibold">Additional details <span class="ml-2 font-normal text-muted">Optional</span></summary>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <label class="grid gap-2 text-sm" data-schedule-personnel-field>Assigned personnel<select class="admin-input" name="personnel_id"><option value="">Unassigned</option>@foreach($personnel as $person)<option value="{{ $person->id }}" @disabled(! $person->active)>{{ $person->name }}{{ $person->active ? '' : ' (inactive)' }}</option>@endforeach</select><span class="text-xs text-muted" data-schedule-personnel-help>Optional for activities that do not require a priest.</span></label>
                    <label class="grid gap-2 text-sm">Department<input class="admin-input" name="department" maxlength="255" placeholder="e.g. Parish office"></label>
                    <label class="grid gap-2 text-sm sm:col-span-2">Description<textarea class="admin-input" name="description" rows="2" maxlength="10000" placeholder="What is this activity about?"></textarea></label>
                    <label class="grid gap-2 text-sm sm:col-span-2">Internal notes<textarea class="admin-input" name="notes" rows="2" maxlength="10000" placeholder="Notes for the parish office"></textarea></label>
                    <label class="grid gap-2 text-sm sm:col-span-2">Status<select class="admin-input" name="status"><option value="scheduled">Scheduled</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select><span class="text-xs text-muted">Keep Scheduled for an upcoming activity. Registration is available only for scheduled group sessions.</span></label>
                </div>
            </details>
        </div>
        <footer class="sticky bottom-0 z-10 grid gap-3 border-t border-navy/10 bg-white px-5 py-4 shadow-[0_-4px_16px_rgb(15_23_42/0.04)] sm:grid-cols-[1fr_auto] sm:items-center sm:px-7">
            <p data-schedule-preview class="min-w-0 text-xs leading-5 text-muted" aria-live="polite">Choose an activity to get started.</p>
            <div class="flex gap-2"><button type="button" class="admin-secondary flex-1 sm:flex-none" data-dialog-close>Cancel</button><button type="submit" class="admin-button flex-1 sm:flex-none" data-schedule-save>Create schedule</button></div>
        </footer>
    </form>
</dialog>
