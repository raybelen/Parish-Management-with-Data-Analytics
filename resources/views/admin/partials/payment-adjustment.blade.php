<article class="rounded-xl border border-navy/10 p-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-base font-semibold">{{ $adjustment->kind === 'refund' ? 'Refund request' : 'Fee adjustment' }} #{{ $adjustment->id }}</h3>
        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold">{{ ucfirst($adjustment->status) }}</span>
    </div>
    <dl class="mt-4 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-[auto_minmax(0,1fr)]">
        <dt class="text-muted">Requested by</dt><dd>{{ $adjustment->requestedBy->name }} · {{ $adjustment->created_at->format('M j, Y g:i A') }}</dd>
        @if($adjustment->kind === 'fee')
            <dt class="text-muted">Fee change</dt><dd>PHP {{ number_format($adjustment->payload['previous_fee_cents'] / 100, 2) }} → PHP {{ number_format((float) $adjustment->payload['fee'], 2) }}</dd>
        @else
            <dt class="text-muted">Refund</dt><dd>PHP {{ number_format((float) $adjustment->payload['amount'], 2) }} · Original payment #{{ $adjustment->payload['original_payment_id'] }}</dd>
            <dt class="text-muted">Method / date</dt><dd>{{ \Illuminate\Support\Str::headline($adjustment->payload['method']) }} · {{ str_replace('T', ' ', $adjustment->payload['paid_at']) }}</dd>
            <dt class="text-muted">Reference</dt><dd class="break-words">{{ $adjustment->payload['reference'] ?? 'Not provided' }}</dd>
        @endif
        <dt class="text-muted">Request reason</dt><dd class="whitespace-pre-line break-words">{{ $adjustment->reason }}</dd>
        @if($adjustment->reviewed_at)
            <dt class="text-muted">Reviewed by</dt><dd>{{ $adjustment->reviewedBy->name }} · {{ $adjustment->reviewed_at->format('M j, Y g:i A') }}</dd>
            <dt class="text-muted">Review reason</dt><dd class="whitespace-pre-line break-words">{{ $adjustment->review_reason }}</dd>
            @if($adjustment->payment_id)<dt class="text-muted">Payment record</dt><dd>#{{ $adjustment->payment_id }}</dd>@endif
        @endif
    </dl>
    @if($adjustment->status === 'pending')
        @can('payments.approve')
            @if($adjustment->requested_by === auth()->id())
                <p class="mt-4 text-sm text-muted">A different super administrator must review your request.</p>
            @else
                <form method="POST" action="{{ route('admin.payment-adjustments.update', $adjustment) }}" class="mt-4 grid gap-3" data-confirm="Submit this review? Approved adjustments take effect immediately and completed reviews cannot be edited.">
                    @csrf @method('PATCH')
                    <x-admin.field :name="'reason'" :id="'review-reason-'.$adjustment->id" label="Review reason" required />
                    <div class="flex flex-wrap gap-2"><button class="admin-button" name="decision" value="approved">Approve request</button><button class="admin-secondary" name="decision" value="rejected">Reject request</button></div>
                </form>
            @endif
        @else
            <p class="mt-4 text-sm text-muted">Awaiting review by a different super administrator.</p>
        @endcan
    @endif
</article>
