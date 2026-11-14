@extends('admin.layout')
@section('title', $facility->exists ? 'Edit facility' : 'Add facility')
@section('actions')<a class="admin-secondary" href="{{ $facility->exists ? route('admin.facilities.show', $facility) : route('admin.facilities.index') }}">Back to facilities</a>@endsection
@section('content')
    <form class="grid gap-6" method="POST" action="{{ $facility->exists ? route('admin.facilities.update', $facility) : route('admin.facilities.store') }}">
        @csrf @if($facility->exists) @method('PUT') @endif
        <section class="admin-card grid gap-5">
            <div><h2 class="text-2xl">Facility details</h2><p class="mt-2 text-sm text-muted">Inactive facilities cannot accept reservations. Existing upcoming reservations must be canceled before incompatible changes can be saved.</p></div>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.field label="Facility name" name="name" :value="$facility->name" maxlength="150" required />
                <x-admin.field label="Location" name="location" :value="$facility->location" maxlength="255" placeholder="For example, ground floor, parish building" />
                <x-admin.field label="Maximum capacity" name="capacity" type="number" :value="$facility->capacity" min="1" max="100000" required />
                <label class="grid gap-2 text-sm">Status<select name="active" class="admin-input"><option value="1" @selected(old('active', $facility->active) == 1)>Active</option><option value="0" @selected(old('active', $facility->active) == 0)>Inactive</option></select></label>
            </div>
            <label class="grid gap-2 text-sm">Description<textarea name="description" rows="3" class="admin-input" maxlength="3000">{{ old('description', $facility->description) }}</textarea></label>
            @php($activities = old('permitted_activities', $facility->permitted_activities))
            <label class="grid gap-2 text-sm">Permitted activities<textarea name="permitted_activities" class="admin-input" rows="4" required placeholder="Meeting&#10;Catechism class&#10;Parish gathering">{{ is_array($activities) ? implode(PHP_EOL, $activities) : $activities }}</textarea><span class="text-xs text-muted">One activity per line. Reservations must use one of these activities.</span></label>
        </section>
        <section class="admin-card grid gap-5">
            <div><h2 class="text-2xl">Weekly opening hours</h2><p class="mt-2 text-sm text-muted">All times use {{ config('office.timezone') }}. Select Open and enter a same-day time range. Reservations must fit inside these hours.</p></div>
            @foreach(\App\Models\Facility::DAYS as $day => $label)
                <fieldset class="grid items-end gap-3 rounded-xl border border-navy/10 p-4 sm:grid-cols-3">
                    <legend class="px-2 font-semibold">{{ $label }}</legend>
                    <label class="flex min-h-11 items-center gap-2 text-sm"><input type="hidden" name="opening_hours[{{ $day }}][open]" value="0"><input type="checkbox" name="opening_hours[{{ $day }}][open]" value="1" @checked(old('opening_hours.'.$day.'.open', $facility->opening_hours[$day]['open'] ?? false))>Open on {{ $label }}</label>
                    <label class="grid gap-2 text-sm">Opening time<input class="admin-input" type="time" name="opening_hours[{{ $day }}][opens_at]" value="{{ old('opening_hours.'.$day.'.opens_at', $facility->opening_hours[$day]['opens_at'] ?? '') }}"></label>
                    <label class="grid gap-2 text-sm">Closing time<input class="admin-input" type="time" name="opening_hours[{{ $day }}][closes_at]" value="{{ old('opening_hours.'.$day.'.closes_at', $facility->opening_hours[$day]['closes_at'] ?? '') }}"></label>
                </fieldset>
            @endforeach
        </section>
        <button class="admin-button justify-self-start">{{ $facility->exists ? 'Save facility changes' : 'Create facility' }}</button>
    </form>
@endsection
