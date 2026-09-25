<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#13233F">
    <meta name="description" content="Welcome to St. John Nepomucene Parish. Find Mass times, discover our community, explore ministries, and plan a visit to the parish office.">
    <title>{{ $pageTitle }} | St. John Nepomucene Parish</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=EB+Garamond:ital,wght@0,400;0,500;0,600;1,400;1,500&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <a href="#main-content" class="fixed top-3 left-3 z-70 -translate-y-24 bg-ivory px-5 py-3 text-navy focus:translate-y-0">Skip to content</a>
    <header class="sticky top-0 z-40 border-b border-ivory/10 bg-navy text-ivory">
        <div class="mx-auto flex max-w-[1500px] items-center justify-between gap-5 px-5 py-5 sm:px-9 xl:px-12">
            <a href="{{ route('home') }}" aria-label="St. John Nepomucene Parish home" class="flex shrink-0 items-center gap-3">
                <img src="{{ asset('images/logo-modified.png') }}" alt="St. John Nepomucene Parish logo" width="48" height="48" class="size-12 shrink-0 object-contain">
                <span class="font-display text-lg leading-tight sm:text-xl">St. John Nepomucene<span class="mt-1 block font-sans text-[9px] tracking-[0.28em] uppercase text-ivory/65">Parish</span></span>
            </a>
            <nav aria-label="Main navigation" class="hidden items-center gap-5 text-xs xl:flex 2xl:gap-7">
                @foreach ($navigation as $label => $href)
                    <a href="{{ route($href) }}" @if (request()->routeIs($href)) aria-current="page" @endif @class(['flex min-h-11 items-center transition-colors hover:text-gold', 'text-gold' => request()->routeIs($href), 'text-ivory/80' => ! request()->routeIs($href)])>{{ $label }}</a>
                @endforeach
            </nav>
            <div class="flex items-center gap-3">
                <div class="hidden sm:block"><x-parish.button class="px-5">Set an Appointment</x-parish.button></div>
                <button id="menu-toggle" class="flex size-12 items-center justify-center border border-ivory/25 xl:hidden" aria-label="Open navigation" aria-expanded="false" aria-controls="mobile-navigation"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18"/></svg></button>
            </div>
        </div>
        <nav id="mobile-navigation" aria-label="Mobile navigation" hidden class="max-h-[calc(100dvh-88px)] overflow-y-auto border-t border-ivory/10 px-6 pb-6 xl:hidden">
            <div class="grid gap-1 py-3">@foreach ($navigation as $label => $href)<a href="{{ route($href) }}" class="flex min-h-12 items-center border-b border-ivory/10 text-sm hover:text-gold">{{ $label }}</a>@endforeach</div>
            <x-parish.button class="w-full">Set an Appointment</x-parish.button>
        </nav>
    </header>
    <main id="main-content">
        @yield('content')
    </main>
    <footer class="border-t border-ivory/15 bg-navy text-ivory">
        <div class="section-wrap grid gap-10 py-14 md:grid-cols-[1.3fr_1fr_1fr]">
            <div><a href="{{ route('home') }}" class="font-display text-2xl">St. John Nepomucene Parish</a><p class="mt-4 text-sm text-ivory/60">Faith. Prayer. Community. Service.</p><p class="mt-8 font-display text-xl italic text-[#d4ba80]">You are always welcome here.</p></div>
            <nav aria-label="Footer navigation" class="grid grid-cols-2 content-start gap-x-5 gap-y-2 text-sm text-ivory/65">@foreach ($navigation as $label => $href)<a href="{{ route($href) }}" class="flex min-h-11 items-center hover:text-gold">{{ $label }}</a>@endforeach</nav>
            <div><h2 class="font-sans text-xs tracking-[0.15em] uppercase text-[#d4ba80]">Office Hours</h2><div class="mt-5 flex flex-col gap-2 text-sm text-ivory/65"><p>Tuesdays to Sundays</p><p>8:00 AM – 11:30 AM</p><p>2:00 PM – 5:00 PM</p><p class="mt-2">Monday — No Office Hours</p></div><a href="{{ route('contact') }}" class="text-link mt-5 text-[#d4ba80]">Set an Appointment <x-parish.icon name="arrow" class="size-4" /></a></div>
        </div>
        <div class="section-wrap flex flex-col justify-between gap-3 border-t border-ivory/10 py-6 text-[11px] text-ivory/65 sm:flex-row"><p>© {{ date('Y') }} St. John Nepomucene Parish. All rights reserved.</p><p>With faith, hope, and love.</p></div>
    </footer>
    @stack('dialogs')
</body>
</html>
