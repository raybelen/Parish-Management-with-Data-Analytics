<section aria-labelledby="{{ $serviceType }}-requirements-heading" class="border-t border-navy/10 pt-8">
    <div class="max-w-2xl">
        <p class="section-label text-muted">Requirements</p>
        <h3 id="{{ $serviceType }}-requirements-heading" class="mt-4 text-2xl">Documents to prepare</h3>
        <p class="mt-2 text-sm leading-7 text-muted">Please prepare these documents and bring the physical copies to the church office for verification.</p>
    </div>

    <ul class="mt-6 grid gap-3 text-sm leading-6 text-navy sm:grid-cols-2">
        @forelse ($documents as $definition)
            <li class="flex gap-3 border border-navy/10 bg-white px-4 py-3">
                <span aria-hidden="true" class="mt-2 size-1.5 shrink-0 rounded-full bg-gold"></span>
                <span>{{ $definition['label'] }}</span>
            </li>
        @empty
            <li class="text-muted">No document requirements are currently listed.</li>
        @endforelse
    </ul>
</section>
