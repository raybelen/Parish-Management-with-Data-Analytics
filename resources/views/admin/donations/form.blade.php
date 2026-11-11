@extends('admin.layout')
@section('title', $donation->exists ? 'Request donation correction' : ($donation->isMassCollection() ? 'Record Mass collection' : 'Record donation'))
@section('actions')
    <a class="admin-secondary" href="{{ $donation->exists ? route('admin.donations.show', $donation) : route('admin.donations.index') }}">Cancel</a>
@endsection
@section('content')
    <p class="max-w-3xl text-sm leading-6 text-muted">{{ $donation->exists ? 'Another Super Admin must approve your changes. Earlier details stay in the history.' : 'Record funds already received by the parish. Amounts are in Philippine pesos (PHP).' }}</p>
    @if($collection)
        <p class="admin-alert border-sky-200 bg-sky-50 text-sm">This individual receipt is included in <a class="underline" href="{{ route('admin.donations.show', $collection) }}">{{ $collection->receipt_number }}</a> ({{ $collection->church }}, {{ $collection->donated_on->format('M j, Y') }} at {{ $collection->mass_time }}). It will not add to donation totals. Use this only for money already counted in that collection.</p>
    @endif
    <form method="POST" action="{{ $donation->exists ? route('admin.donations.update', $donation) : route('admin.donations.store') }}" class="grid gap-6">
        @csrf
        @if($donation->exists)@method('PUT')<input type="hidden" name="version" value="{{ old('version', $donation->version) }}">@endif
        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
        <input type="hidden" name="kind" value="{{ $donation->kind }}">
        @if($collection)<input type="hidden" name="mass_collection_id" value="{{ $collection->id }}">@endif
        @if($donation->isMassCollection())
            <section class="admin-card">
                <h2 class="text-2xl">Mass and collection</h2>
                <p class="mt-2 text-sm leading-6 text-muted">Enter the total collected at this Mass, including donations with individual receipts. Record cash and other payment methods separately. Individual donor names are not needed.</p>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <x-admin.field label="Church / chapel" name="church" required><select class="admin-input" name="church" required><option value="">Choose a church</option>@foreach(config('parish.churches') as $church)<option value="{{ $church }}" @selected(old('church', $donation->church) === $church)>{{ $church }}</option>@endforeach</select></x-admin.field>
                    <x-admin.field label="Mass time" name="mass_time" type="time" :value="$donation->mass_time" required />
                    <x-admin.field label="Collection type" name="collection_type" required><select class="admin-input" name="collection_type" required>@foreach(\App\Models\Donation::COLLECTION_TYPES as $key => $label)<option value="{{ $key }}" @selected(old('collection_type', $donation->collection_type ?? 'regular') === $key)>{{ $label }}</option>@endforeach</select></x-admin.field>
                    <x-admin.field label="Special collection purpose" name="collection_purpose" :value="$donation->collection_purpose" maxlength="255" help="Required for a special collection, such as disaster relief." />
                    <x-admin.field label="Counted by" name="counted_by" :value="$donation->counted_by" maxlength="255" required />
                    <x-admin.field label="Checked by" name="verified_by" :value="$donation->verified_by" maxlength="255" help="Name of a different person who checked the count." required />
                </div>
            </section>
        @else
            <section class="admin-card">
                <h2 class="text-2xl">Donor information</h2>
                <fieldset class="mt-5 grid gap-4 sm:grid-cols-2">
                    <legend class="sr-only">Named donor details</legend>
                    <x-admin.field label="Donor name / organization" name="donor_name" :value="$donation->is_anonymous ? '' : $donation->donor_name" maxlength="255" required />
                    <x-admin.field label="Email" name="donor_email" type="email" :value="$donation->donor_email" maxlength="255" />
                    <x-admin.field label="Phone" name="donor_phone" type="tel" :value="$donation->donor_phone" maxlength="40" />
                    <x-admin.field label="Address" name="donor_address" :value="$donation->donor_address" maxlength="1000" />
                </fieldset>
            </section>
        @endif
        <section class="admin-card">
            <h2 class="text-2xl">{{ $donation->isMassCollection() ? 'Collection amount' : 'Donation details' }}</h2>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <x-admin.field :label="$donation->isMassCollection() ? 'Total collected (PHP)' : 'Amount (PHP)'" name="amount" type="number" :value="$donation->exists ? number_format($donation->amount_cents / 100, 2, '.', '') : ''" min="0.01" max="9999999.99" step="0.01" required />
                <x-admin.field :label="$donation->isMassCollection() ? 'Mass date' : 'Date received'" name="donated_on" type="date" :value="$collection?->donated_on?->toDateString() ?? $donation->donated_on?->toDateString() ?? now(config('office.timezone'))->toDateString()" :max="now(config('office.timezone'))->toDateString()" :readonly="(bool) $collection" required />
                @if($donation->isMassCollection() || $collection)
                    <input type="hidden" name="category" value="mass_offering">
                @else
                    <x-admin.field label="Category" name="category" required><select name="category" class="admin-input" required>@foreach(\App\Models\Donation::CATEGORIES as $key => $label)<option value="{{ $key }}" @selected(old('category', $donation->category ?? 'general') === $key)>{{ $label }}</option>@endforeach</select></x-admin.field>
                @endif
                @if($collection)
                    <input type="hidden" name="method" value="{{ $collection->method }}"><p class="self-center text-sm">Payment method: {{ \App\Models\Donation::METHODS[$collection->method] }}</p>
                @else
                    <x-admin.field label="Payment method" name="method" required><select name="method" class="admin-input" required>@foreach(\App\Models\Donation::METHODS as $key => $label)<option value="{{ $key }}" @selected(old('method', $donation->method ?? 'cash') === $key)>{{ $label }}</option>@endforeach</select></x-admin.field>
                @endif
                <x-admin.field label="Payment reference / check number" name="reference" :value="$donation->reference" maxlength="255" help="Required for payment methods other than cash." />
                <x-admin.field label="Office notes" name="notes" help="These notes will not appear on the receipt."><textarea class="admin-input" name="notes" rows="3" maxlength="2000">{{ old('notes', $donation->notes) }}</textarea></x-admin.field>
                @if($donation->exists)<x-admin.field label="Reason for correction" name="reason" required><textarea class="admin-input" name="reason" rows="3" maxlength="1000" required>{{ old('reason') }}</textarea></x-admin.field>@endif
            </div>
        </section>
        <div><button class="admin-button">{{ $donation->exists ? 'Submit correction for approval' : ($donation->isMassCollection() ? 'Record Mass collection' : 'Record donation') }}</button></div>
    </form>
@endsection
