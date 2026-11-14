@extends('client-side.layouts.parish')

@section('content')
<section class="section-wrap max-w-3xl py-12 sm:py-20">
    <h1 class="text-4xl">Verify an invoice or payment receipt</h1>
    <p class="mt-4 leading-7 text-muted">Enter the details printed on the document. Compare the verified number, date, and amount with your copy. A matching code alone is not enough. Verification confirms a parish record; it cannot independently confirm that cash changed hands.</p>
    @if($verificationError)
        <p role="alert" class="mt-6 rounded-lg border border-red-300 bg-red-50 p-4">We could not verify these details. Check the document or contact the parish office. Do not rely on this copy as verified.</p>
    @endif
    @if($result)
        <section aria-label="Verification result" class="mt-6 rounded-lg border border-navy/20 bg-white p-6">
            <h2 class="text-2xl">Record verified</h2>
            @if($result['demo'])<p class="mt-3 font-bold">Test transaction</p>@endif
            <dl class="mt-4 grid gap-3">
                <div><dt class="text-sm text-muted">Parish / service</dt><dd>{{ $result['parish'] }} · {{ $result['service'] }}</dd></div>
                @if($result['method'])<div><dt class="text-sm text-muted">Payment method</dt><dd>{{ $result['method'] }}</dd></div>@endif
                <div><dt class="text-sm text-muted">Document</dt><dd class="break-all font-semibold">{{ $result['type'] }} {{ $result['number'] }}</dd></div>
                <div><dt class="text-sm text-muted">Issued / payment date</dt><dd>{{ $result['date']->timezone(config('office.timezone'))->format('M j, Y g:i A') }} (Philippine time)</dd></div>
                <div><dt class="text-sm text-muted">Amount (PHP)</dt><dd class="text-xl font-semibold">{{ number_format($result['amount_cents'] / 100, 2) }}</dd></div>
                <div><dt class="text-sm text-muted">Current status</dt><dd>{{ $result['status'] }}</dd></div>
                @if($result['refunded_cents'] !== null)<div><dt class="text-sm text-muted">Recorded refunds (PHP)</dt><dd>{{ number_format($result['refunded_cents'] / 100, 2) }}</dd></div>@endif
            </dl>
            <p class="mt-4 text-sm text-muted">The supplied client name matches the saved document. Contact details and appointment access are not disclosed by this check.</p>
        </section>
    @endif
    <form method="POST" action="{{ route('documents.verify.store') }}" class="mt-8 grid gap-5">
        @csrf
        <label class="grid gap-2">Invoice / receipt number<input class="admin-input" name="number" maxlength="100" required autocomplete="off"></label>
        <label class="grid gap-2">Billed to / client name on the document<input class="admin-input" name="holder" maxlength="255" required autocomplete="off"></label>
        <label class="grid gap-2">Verification code<input class="admin-input font-mono" name="code" minlength="64" maxlength="64" pattern="[a-f0-9]{64}" required autocomplete="off" spellcheck="false" autocapitalize="none"></label>
        <button class="admin-button justify-self-start">Verify document</button>
    </form>
    <p class="mt-5 text-sm text-muted">Older documents without a verification code must be checked with the parish office.</p>
</section>
@endsection
