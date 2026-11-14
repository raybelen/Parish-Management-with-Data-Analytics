@php
    $isAdminRequest = request()->is('admin/*');
    $primaryRoute = $isAdminRequest ? route('admin.dashboard') : route('home');
    $primaryLabel = $isAdminRequest ? 'Return to administration' : 'Go to parish home';
@endphp
<!DOCTYPE html>
<html lang="en" @class(['admin-document' => $isAdminRequest])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#13233F">
    <meta name="robots" content="noindex, nofollow">
    <title>Page not found | St. John Nepomucene Parish</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo-modified.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo-modified.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=EB+Garamond:wght@400;500;600&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
    @vite('resources/css/app.css')
</head>
<body class="min-h-dvh bg-slate-50 text-navy">
    <main class="grid min-h-dvh place-items-center px-5 py-10 sm:px-8">
        <section aria-labelledby="error-heading" class="w-full max-w-3xl overflow-hidden rounded-2xl border border-navy/10 bg-white shadow-[0_20px_60px_rgb(19_35_63/0.12)]">
            <div class="grid md:grid-cols-[0.78fr_1.22fr]">
                <div class="flex min-h-56 flex-col justify-between gap-8 bg-navy p-7 text-white sm:p-10">
                    <a href="{{ route('home') }}" aria-label="St. John Nepomucene Parish home" class="flex w-fit items-center gap-3 rounded-lg">
                        <span class="grid size-14 place-items-center rounded-full bg-white p-2">
                            <img src="{{ asset('images/logo-modified.png') }}" alt="" class="size-full object-contain">
                        </span>
                        <span class="font-display text-xl leading-tight">St. John Nepomucene<span class="mt-1 block font-sans text-[0.6rem] tracking-[0.2em] text-white/65 uppercase">Parish</span></span>
                    </a>

                    <div>
                        <p class="font-display text-7xl leading-none text-[#d4ba80] sm:text-8xl" aria-hidden="true">404</p>
                        <p class="mt-3 text-xs font-semibold tracking-[0.16em] text-white/65 uppercase">Page not found</p>
                    </div>
                </div>

                <div class="grid content-center gap-6 p-7 sm:p-10 md:p-12">
                    <div>
                        <p class="text-xs font-semibold tracking-[0.16em] text-[#80662e] uppercase">Lost your way?</p>
                        <h1 id="error-heading" class="mt-3 text-4xl leading-tight sm:text-5xl">We couldn't find that page.</h1>
                        <p class="mt-4 text-sm leading-6 text-muted">
                            {{ $isAdminRequest
                                ? 'The administration page at this address does not exist. Check the URL or return to the office overview.'
                                : 'The address may be mistyped, or the page may have moved. Use one of the links below to continue.' }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row">
                        <a href="{{ $primaryRoute }}" class="admin-button min-h-12 w-full gap-3 sm:w-auto">
                            {{ $primaryLabel }}
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" class="size-4"><path d="m9 18 6-6-6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </a>
                        @if ($isAdminRequest)
                            <a href="{{ route('home') }}" class="admin-secondary min-h-12 w-full sm:w-auto">Visit parish website</a>
                        @else
                            <a href="{{ route('contact') }}" class="admin-secondary min-h-12 w-full sm:w-auto">Contact the parish office</a>
                        @endif
                    </div>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
