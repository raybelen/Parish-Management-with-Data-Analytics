<!DOCTYPE html>
<html lang="en" class="admin-document">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Administrator sign in | {{ config('office.defaults.parish_name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/admin.js'])
</head>
<body class="admin-workspace flex min-h-dvh flex-col bg-ivory/50 text-navy">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-50 focus:rounded-lg focus:bg-white focus:p-4">Skip to sign in</a>
    <header class="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-5 py-5 sm:px-8">
        <a href="{{ route('home') }}" class="flex min-h-11 items-center gap-2 rounded-lg text-sm font-medium text-muted hover:text-navy">
            <x-parish.icon name="arrow" class="size-4 rotate-180" />
            Back to parish website
        </a>
        <span class="hidden text-xs text-muted sm:block">Parish office</span>
    </header>
    <main id="main-content" class="mx-auto grid w-full max-w-5xl flex-1 content-center gap-5 px-5 py-6 sm:px-8 sm:py-10" tabindex="-1">
        @if(\App\Support\OfficeSettings::demo())
            <p role="status" class="admin-alert border-amber-300 bg-amber-50 text-sm text-amber-950">Demo workspace — simulated records do not describe actual parish activity.</p>
        @endif
        <div class="grid overflow-hidden rounded-2xl border border-navy/10 bg-white shadow-sm md:grid-cols-[0.9fr_1.1fr]">
            <section aria-label="Parish office" class="flex flex-col justify-between gap-8 bg-navy p-6 text-white sm:p-8 md:p-10">
                <div class="flex items-center gap-4 md:flex-col md:items-start md:gap-6">
                    <div class="grid size-16 shrink-0 place-items-center rounded-full bg-white p-2 md:size-20">
                        <img src="{{ asset('images/logo-modified.png') }}" alt="" class="size-full object-contain">
                    </div>
                    <div class="grid gap-2">
                        <p class="text-xs font-semibold tracking-[0.16em] text-[#d4ba80] uppercase">Office administration</p>
                        <p class="font-display text-2xl leading-tight md:text-3xl">{{ config('office.defaults.parish_name') }}</p>
                    </div>
                </div>
                <div class="hidden border-t border-white/15 pt-6 md:block">
                    <p class="font-display text-2xl">Bonga, Bacacay, Albay</p>
                    <p class="mt-3 text-sm leading-6 text-white/75">A central space for coordination and daily operations.</p>
                </div>
            </section>
            <section aria-labelledby="sign-in-heading" class="grid content-start gap-6 p-6 sm:p-8 md:p-10">
                <div>
                    <h1 id="sign-in-heading" class="text-3xl leading-tight sm:text-4xl">Welcome back</h1>
                    <p class="mt-3 text-sm leading-6 text-muted">Sign in with your parish office account.</p>
                </div>
                @if(session('status'))
                    <p role="status" class="admin-alert border-emerald-200 bg-emerald-50 text-sm text-emerald-950">{{ session('status') }}</p>
                @endif
                @if($errors->any())
                    <div role="alert" class="admin-alert border-red-200 bg-red-50 text-sm text-red-950">
                        <p class="font-semibold">We couldn't sign you in.</p>
                        <ul class="list-inside list-disc">
                            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                        </ul>
                    </div>
                @endif
                <form method="POST" action="{{ route('login.store') }}" class="grid gap-5">
                    @csrf
                    <div class="grid gap-2">
                        <label for="email" class="text-sm font-medium">Email address</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus class="admin-input min-h-12" @if($errors->has('email')) aria-invalid="true" @endif>
                    </div>
                    <div class="grid gap-2">
                        <label for="password" class="text-sm font-medium">Password</label>
                        <input id="password" name="password" type="password" autocomplete="current-password" required class="admin-input min-h-12" @if($errors->has('password')) aria-invalid="true" @endif>
                    </div>
                    <button class="admin-button min-h-12 w-full gap-3">Continue securely <x-parish.icon name="arrow" class="size-4" /></button>
                </form>
                <div class="flex items-start gap-3 border-t border-navy/10 pt-5">
                    <x-parish.icon name="shield" class="mt-0.5 size-5 shrink-0 text-muted" />
                    <p class="text-xs leading-5 text-muted">After your password is verified, use your authenticator to complete sign-in.</p>
                </div>
            </section>
        </div>
        <p class="text-center text-xs leading-5 text-muted">Private parish office workspace. Authorized personnel only.</p>
    </main>
</body>
</html>
