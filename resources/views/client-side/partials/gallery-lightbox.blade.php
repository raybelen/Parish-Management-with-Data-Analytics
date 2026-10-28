@push('dialogs')
    <dialog id="gallery-dialog" aria-labelledby="gallery-dialog-title" class="w-[min(1100px,94vw)] max-w-none border border-ivory/20 bg-navy p-4 text-ivory sm:p-6">
        <div class="mb-4 flex items-center justify-between"><p id="gallery-dialog-title" class="text-xs tracking-widest uppercase text-[#d4ba80]">{{ $websiteContent['gallery_title'] }}</p><button data-close-dialog aria-label="Close gallery" class="size-11 text-2xl">×</button></div>
        <img id="gallery-image" src="{{ $photos[0]['src'] }}" alt="{{ $photos[0]['alt'] }}" class="max-h-[65vh] w-full object-contain">
        <div class="mt-4 flex items-center justify-between gap-4"><button id="gallery-previous" class="min-h-11 border border-ivory/30 px-4" aria-label="Previous photo">←</button><p id="gallery-count" class="text-xs text-ivory/65" aria-live="polite">1 / {{ count($photos) }}</p><button id="gallery-next" class="min-h-11 border border-ivory/30 px-4" aria-label="Next photo">→</button></div>
    </dialog>
@endpush
