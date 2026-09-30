<section aria-labelledby="{{ $serviceType }}-documents-heading" class="border-t border-navy/10 pt-8">
    <div class="max-w-2xl">
        <p class="section-label text-muted">Documents</p>
        <h3 id="{{ $serviceType }}-documents-heading" class="mt-4 text-2xl">Supporting documents</h3>
        <p class="mt-2 text-sm leading-7 text-muted">Upload PDF, JPG, or PNG files up to 5 MB each. Requirements marked optional may be provided later if requested by parish staff.</p>
    </div>

    <div class="mt-7 grid gap-5 sm:grid-cols-2">
        @foreach ($documents as $documentKey => $definition)
            <x-parish.form-field
                :id="$serviceType.'_'.$documentKey"
                :name="'documents.'.$documentKey"
                :label="$definition['label']"
                :required="$definition['required']"
                :optional="! $definition['required']"
                :help="$definition['multiple'] ? 'You may select up to 5 files.' : 'Select one file.'"
            >
                <input
                    id="{{ $serviceType }}_{{ $documentKey }}"
                    name="documents[{{ $documentKey }}][]"
                    type="file"
                    accept=".pdf,.jpg,.jpeg,.png"
                    @if ($definition['multiple']) multiple @endif
                    data-required="{{ $definition['required'] ? 'true' : 'false' }}"
                    @required($active && $definition['required'])
                    @disabled(! $active)
                    class="w-full border border-dashed border-navy/25 bg-white px-4 py-4 text-sm text-muted file:mr-4 file:border-0 file:bg-navy file:px-4 file:py-2 file:text-sm file:font-medium file:text-ivory hover:border-gold"
                >
            </x-parish.form-field>
        @endforeach
    </div>
</section>
