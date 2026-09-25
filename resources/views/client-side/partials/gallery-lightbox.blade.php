@push('dialogs')
    <dialog id="gallery-dialog" aria-labelledby="gallery-caption" class="w-[min(1100px,94vw)] max-w-none border border-ivory/20 bg-navy p-4 text-ivory sm:p-6">
        <div class="mb-4 flex items-center justify-between"><p class="text-xs tracking-widest uppercase text-[#d4ba80]">Moments of Faith</p><button data-close-dialog aria-label="Close gallery" class="size-11 text-2xl">×</button></div>
        <img id="gallery-image" src="{{ $churchImage }}" alt="{{ $photos[0]['alt'] }}" class="max-h-[65vh] w-full object-contain">
        <div class="mt-4 flex items-center justify-between gap-4"><button id="gallery-previous" class="min-h-11 border border-ivory/30 px-4" aria-label="Previous photo">←</button><div class="text-center"><h2 id="gallery-caption" class="text-2xl">{{ $photos[0]['title'] }}</h2><p id="gallery-count" class="mt-1 text-xs text-ivory/65" aria-live="polite">1 / 3</p></div><button id="gallery-next" class="min-h-11 border border-ivory/30 px-4" aria-label="Next photo">→</button></div>
    </dialog>
@endpush
