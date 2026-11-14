@props(['sessions', 'selectClass', 'selectedService' => ''])

@if($sessions->isNotEmpty())
    <div data-group-baptism class="grid gap-4 sm:col-span-2" @if(! in_array($selectedService, ['baptism', 'wedding', 'confirmation'], true)) hidden @endif>
        <label class="grid gap-2 text-sm" for="baptism_mode"><span class="font-medium">Appointment arrangement</span>
            <select id="baptism_mode" name="baptism_mode" class="{{ $selectClass }}" @disabled(! in_array($selectedService, ['baptism', 'wedding', 'confirmation'], true))>
                <option value="private" @selected(old('baptism_mode', 'private') === 'private')>Individual appointment</option>
                <option value="shared" @selected(old('baptism_mode') === 'shared')>Group session</option>
            </select>
        </label>
        <label data-group-session-choice class="grid gap-2 text-sm" for="group_baptism_session_id" @if(old('baptism_mode') !== 'shared') hidden @endif>
            <span class="font-medium">Open group session <span class="text-red-700">*</span></span>
            <select id="group_baptism_session_id" name="group_baptism_session_id" class="{{ $selectClass }}" @disabled(! in_array($selectedService, ['baptism', 'wedding', 'confirmation'], true) || old('baptism_mode') !== 'shared') @required(in_array($selectedService, ['baptism', 'wedding', 'confirmation'], true) && old('baptism_mode') === 'shared')>
                <option value="">Choose an open session</option>
                @foreach($sessions as $session)
                    <option value="{{ $session->id }}" data-service="{{ mb_strtolower(substr($session->category, 6)) }}" data-date="{{ $session->starts_at->toDateString() }}" data-time="{{ $session->starts_at->format('H:i') }}" data-venue="{{ $session->location }}" data-fee-cents="{{ $session->group_fee !== null ? (int) round((float) $session->group_fee * 100) : '' }}" @selected((string) old('group_baptism_session_id') === (string) $session->id)>{{ $session->starts_at->format('l, F j, Y · g:i A') }} — {{ $session->location }}</option>
                @endforeach
            </select>
            <span class="text-xs text-muted">The session sets your date, time, and venue. Complete the same service details and physical document requirements below.</span>
        </label>
    </div>
@endif
