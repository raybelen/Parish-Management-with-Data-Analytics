@props(['sessions', 'selected' => null, 'sharedOnly' => false])
<div data-baptism-options data-shared-only="{{ $sharedOnly ? '1' : '0' }}" class="sm:col-span-2 border border-navy/15 p-5" @if(!$sharedOnly) hidden @endif>
    @if(!$sharedOnly)
        <label for="baptism_mode" class="block text-sm font-medium">Baptism arrangement</label>
        <select id="baptism_mode" name="baptism_mode" class="mt-2 w-full border border-navy/25 bg-white px-4 py-3" data-baptism-mode>
            <option value="private" @selected(old('baptism_mode', 'private') === 'private')>Private baptism</option>
            <option value="shared" @selected(old('baptism_mode') === 'shared')>Shared Sunday baptism</option>
        </select>
        <p class="mt-2 text-sm text-muted">Shared sessions are generally more economical. Private baptisms are subject to availability and applicable parish fees.</p>
    @endif
    <div data-group-session-field class="mt-4">
        <label for="group_baptism_session_id" class="block text-sm font-medium">Authorized Sunday group session</label>
        <select id="group_baptism_session_id" name="group_baptism_session_id" class="mt-2 w-full border border-navy/25 bg-white px-4 py-3" data-group-session>
            <option value="">Choose an authorized session</option>
            @foreach($sessions as $session)
                <option value="{{ $session->id }}" data-date="{{ $session->starts_at->toDateString() }}" data-time="{{ $session->starts_at->format('H:i') }}" data-venue="{{ $session->location }}" @selected((string) old('group_baptism_session_id', $selected) === (string) $session->id)>{{ $session->starts_at->format('M j, Y - g:i A') }} - {{ $session->location }}{{ $session->group_fee !== null ? ' - PHP '.number_format((float) $session->group_fee, 2).' per family' : ' - Fee confirmed by parish office' }}</option>
            @endforeach
        </select>
        <p class="mt-2 text-sm text-muted">Each session lasts one hour for all families. Availability and any family limit are checked before booking.</p>
        @if($sessions->isEmpty())<p class="mt-2 text-sm text-red-700">No authorized sessions are currently available. Choose a private baptism or contact the parish office.</p>@endif
    </div>
</div>
