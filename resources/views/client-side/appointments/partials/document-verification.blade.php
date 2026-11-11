<div class="mt-6 border-t border-navy/15 pt-5 text-xs leading-5">
    @if($document->integrity_seal)
        <p>Verify this document at <a class="break-all underline" href="{{ route('documents.verify') }}">{{ route('documents.verify') }}</a> using its number, client name, and code below. Compare the returned amount and status with this copy.</p>
        <p class="mt-2">Verification code: <span class="break-all font-mono">{{ $document->integrity_seal }}</span></p>
        <p class="mt-2 text-muted">A copied code does not verify altered details. Keep this code private with your document.</p>
    @else
        <p class="font-semibold">UNVERIFIED LEGACY COPY — No integrity seal was recorded at issuance. Ask the parish office to check the original records.</p>
    @endif
</div>
