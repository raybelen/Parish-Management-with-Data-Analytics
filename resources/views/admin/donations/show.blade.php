@extends('admin.layout')
@section('title', $donation->isMassCollection() ? 'Mass collection details' : 'Donation details')
@section('actions')
    <a class="admin-secondary" href="{{ route('admin.donations.index') }}">All donations</a>
    <a class="admin-button" href="{{ route('admin.donations.receipt', $donation) }}" target="_blank" rel="noopener">Print receipt<span class="sr-only"> (new tab)</span></a>
@endsection
@section('content')
    <section class="admin-card">
        <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="break-all text-2xl">{{ $donation->receipt_number }}</h2><span class="rounded-full bg-slate-100 px-4 py-2 text-sm font-semibold">{{ $donation->status === 'voided' ? 'Canceled' : ucfirst($donation->status) }} · Version {{ $donation->version }}</span></div>
        @if($donation->status === 'voided')<p role="status" class="mt-4 font-semibold text-red-800">CANCELED — This record is excluded from donation totals.</p>@endif
        <dl class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach(['Donor' => $donation->donorLabel(), 'Email' => $donation->is_anonymous ? null : $donation->donor_email, 'Phone' => $donation->is_anonymous ? null : $donation->donor_phone, 'Address' => $donation->is_anonymous ? null : $donation->donor_address, 'Amount (PHP)' => '₱'.number_format($donation->amount_cents / 100, 2), 'Date received' => $donation->donated_on->format('M j, Y'), 'Category' => \App\Models\Donation::CATEGORIES[$donation->category], 'Payment method' => \App\Models\Donation::METHODS[$donation->method], 'Reference' => $donation->reference, 'Recorded by' => $donation->recordedBy?->name, 'Office notes' => $donation->notes] as $label => $value)
                <div class="min-w-0"><dt class="text-sm text-muted">{{ $label }}</dt><dd class="mt-1 whitespace-pre-line break-words">{{ $value ?: '—' }}</dd></div>
            @endforeach
        </dl>
        @if($donation->isMassCollection())@include('admin.donations.mass-details', ['mass' => $donation])@endif
        @if($donation->massCollection)
            <p class="mt-5 text-sm">Included in Mass collection <a class="underline" href="{{ route('admin.donations.show', $donation->massCollection) }}">{{ $donation->massCollection->receipt_number }}</a>. This individual receipt is not added to donation totals again.</p>
        @endif
    </section>
    @if($donation->isMassCollection())
        <section class="admin-card">
            <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-2xl">Donor receipts for this collection</h2>@can('donations.manage')@if($donation->status === 'recorded')<a class="admin-secondary" href="{{ route('admin.donations.create', ['mass_collection_id' => $donation->id]) }}">Add donor receipt</a>@endif @endcan</div>
            <p class="mt-3 text-sm leading-6 text-muted">Full collection: ₱{{ number_format($donation->amount_cents / 100, 2) }} · With donor receipts: ₱{{ number_format($allocatedCents / 100, 2) }} · Without donor receipts: ₱{{ number_format(($donation->amount_cents - $allocatedCents) / 100, 2) }}. These donations are already counted in the collection total.</p>
            <div class="mt-4 overflow-x-auto"><table class="admin-table"><thead><tr><th>Receipt</th><th>Donor</th><th>Amount (PHP)</th><th>Status</th></tr></thead><tbody>@forelse($itemized as $item)<tr><td><a class="underline" href="{{ route('admin.donations.show', $item) }}">{{ $item->receipt_number }}</a></td><td>{{ $item->donorLabel() }}</td><td>₱{{ number_format($item->amount_cents / 100, 2) }}</td><td>{{ $item->status === 'voided' ? 'Canceled' : ucfirst($item->status) }}</td></tr>@empty<tr><td colspan="4">No individual receipts. The full collection is already included in totals.</td></tr>@endforelse</tbody></table></div>
            <div class="mt-4">{{ $itemized->links() }}</div>
        </section>
    @endif
    @if($hasPending)<p class="admin-alert border-amber-200 bg-amber-50 text-sm">A change is waiting for another Super Admin to approve it. Details and totals stay the same until then.</p>@endif
    @can('donations.manage')
        @if($donation->status === 'recorded' && ! $hasPending)
            <section class="admin-card"><h2 class="text-2xl">Manage this donation</h2><p class="mt-2 text-sm leading-6 text-muted">Request a correction or cancel a duplicate or incorrect record. Another Super Admin must approve it. Canceling a record does not refund money.</p>
                <a class="admin-secondary mt-4" href="{{ route('admin.donations.edit', $donation) }}">Request correction</a>
                <details class="mt-5"><summary class="cursor-pointer font-semibold text-red-800">Request cancellation</summary>
                    <form method="POST" action="{{ route('admin.donations.void', $donation) }}" class="mt-4 grid max-w-xl gap-4">
                        @csrf<input type="hidden" name="version" value="{{ $donation->version }}"><input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
                        <x-admin.field label="Reason for cancellation" name="reason" required><textarea name="reason" class="admin-input" rows="3" maxlength="1000" required>{{ old('reason') }}</textarea></x-admin.field>
                        <div><button class="admin-secondary">Submit cancellation for approval</button></div>
                    </form>
                </details>
            </section>
        @endif
    @endcan
    <section class="admin-card"><h2 class="text-2xl">Change history</h2>
        <div class="mt-5 grid gap-5">
            @forelse($adjustments as $adjustment)
                <article class="rounded-xl border border-navy/15 p-4">
                    <h3 class="text-lg">{{ $adjustment->kind === 'void' ? 'Cancellation' : 'Correction' }} · {{ ucfirst($adjustment->status) }}</h3>
                    <p class="mt-2 text-sm">Requested by {{ $adjustment->requestedBy?->name ?? 'Former office user' }} on {{ $adjustment->created_at->format('M j, Y g:i A') }} · Version {{ $adjustment->donation_version }}</p>
                    <p class="mt-2 whitespace-pre-line text-sm">{{ $adjustment->reason }}</p>
                    @if($adjustment->kind === 'correction')
                        <div class="mt-4 overflow-x-auto"><table class="admin-table"><thead><tr><th>Detail</th><th>Before</th><th>Requested change</th></tr></thead><tbody>
                            @foreach($adjustment->payload as $key => $value)
                                @if(($adjustment->before[$key] ?? null) !== $value)
                                    <tr><td>{{ $key === 'amount_cents' ? 'Amount (PHP)' : str($key)->headline() }}</td><td class="whitespace-pre-line">{{ $key === 'amount_cents' ? number_format(($adjustment->before[$key] ?? 0) / 100, 2) : ($adjustment->before[$key] ?? '—') }}</td><td class="whitespace-pre-line">{{ $key === 'amount_cents' ? number_format($value / 100, 2) : ($value ?? '—') }}</td></tr>
                                @endif
                            @endforeach
                        </tbody></table></div>
                    @endif
                    @if($adjustment->status === 'pending')
                        @can('payments.approve')
                            @if($adjustment->requested_by === auth()->id())<p class="mt-4 text-sm text-amber-800">Another Super Admin must review your request.</p>
                            @else
                                <form method="POST" action="{{ route('admin.donation-adjustments.update', $adjustment) }}" class="mt-4 grid gap-3">
                                    @csrf @method('PATCH')
                                    <x-admin.field label="Reason for decision" name="review_reason" required><textarea class="admin-input" name="review_reason" rows="2" maxlength="1000" required>{{ old('review_reason') }}</textarea></x-admin.field>
                                    <div class="flex flex-wrap gap-3"><button class="admin-button" name="decision" value="approved">Approve {{ $adjustment->kind === 'void' ? 'cancellation' : 'correction' }}</button><button class="admin-secondary" name="decision" value="rejected">Reject request</button></div>
                                </form>
                            @endif
                        @endcan
                    @else
                        <p class="mt-4 text-sm">Reviewed by {{ $adjustment->reviewedBy?->name ?? 'Former office user' }} on {{ $adjustment->reviewed_at?->format('M j, Y g:i A') }}</p><p class="mt-1 whitespace-pre-line text-sm">{{ $adjustment->review_reason }}</p>
                    @endif
                </article>
            @empty<p class="text-sm text-muted">No changes have been requested.</p>@endforelse
        </div><div class="mt-4">{{ $adjustments->links() }}</div>
    </section>
@endsection
