<!DOCTYPE html>
<html lang="en" class="admin-document">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Verify your sign in | {{ config('office.defaults.parish_name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/admin.js'])
</head>
<body class="admin-workspace flex min-h-dvh flex-col bg-ivory/50 text-navy">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-50 focus:rounded-lg focus:bg-white focus:p-4">Skip to verification</a>
    <header class="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-5 py-5 sm:px-8">
        <a href="{{ route('login') }}" class="flex min-h-11 items-center gap-2 rounded-lg text-sm font-medium text-muted transition hover:text-navy">
            <x-parish.icon name="arrow" class="size-4 rotate-180" />
            Start again
        </a>
        <span class="hidden text-xs text-muted sm:block">Secure verification</span>
    </header>

    <main id="main-content" class="mx-auto grid w-full max-w-5xl flex-1 content-center gap-5 px-5 py-6 sm:px-8 sm:py-10" tabindex="-1">
        @if(\App\Support\OfficeSettings::demo())
            <p role="status" class="admin-alert border-amber-300 bg-amber-50 text-sm text-amber-950">Practice workspace — test records</p>
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
                    <div class="mb-4 grid size-11 place-items-center rounded-full bg-white/10 text-[#d4ba80]">
                        <x-parish.icon name="shield" class="size-6" />
                    </div>
                    <p class="font-display text-2xl">One more secure step</p>
                    <p class="mt-3 text-sm leading-6 text-white/75">Two-factor verification helps keep parish records and administrative access protected.</p>
                </div>
            </section>

            <section aria-labelledby="verification-heading" class="grid content-start gap-6 p-6 sm:p-8 md:p-10">
                <div>
                    <p class="text-xs font-semibold tracking-[0.16em] text-[#80662e] uppercase">Step 2 of 2</p>
                    <h1 id="verification-heading" class="mt-2 text-3xl leading-tight sm:text-4xl">Verify your sign in</h1>
                    <p class="mt-3 text-sm leading-6 text-muted">Enter the six-digit code shown in your authenticator app.</p>
                </div>

                @if($errors->any())
                    <div role="alert" class="admin-alert border-red-200 bg-red-50 text-sm text-red-950">
                        <p class="font-semibold">We couldn't verify that code.</p>
                        <ul class="list-inside list-disc">
                            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('two-factor.login.store') }}" class="grid gap-5">
                    @csrf
                    <div class="grid gap-2">
                        <label for="code" class="text-sm font-medium">Authenticator code</label>
                        <input id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" autofocus placeholder="000000" aria-describedby="code-help" class="admin-input min-h-14 text-center font-mono text-xl tracking-[0.4em] placeholder:tracking-[0.4em]" @if($errors->has('code')) aria-invalid="true" @endif>
                        <p id="code-help" class="text-xs leading-5 text-muted">Codes refresh every 30 seconds. Enter all six digits.</p>
                    </div>

                    <details class="group rounded-xl border border-navy/10 bg-slate-50/70 p-4 open:bg-white" @if($errors->has('recovery_code')) open @endif>
                        <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 rounded-lg font-medium marker:hidden">
                            <span class="flex items-center gap-3">
                                <span class="grid size-9 shrink-0 place-items-center rounded-full bg-ivory text-[#80662e]">
                                    <x-parish.icon name="lock" class="size-4" />
                                </span>
                                <span>
                                    <span class="block text-sm">Use a recovery code</span>
                                    <span class="mt-0.5 block text-xs font-normal text-muted">If your authenticator is unavailable</span>
                                </span>
                            </span>
                            <x-parish.icon name="chevron-down" class="size-4 shrink-0 text-muted transition group-open:rotate-180" />
                        </summary>
                        <div class="mt-4 grid gap-2 border-t border-navy/10 pt-4">
                            <label for="recovery_code" class="text-sm font-medium">Recovery code</label>
                            <input id="recovery_code" name="recovery_code" autocomplete="off" autocapitalize="none" spellcheck="false" class="admin-input min-h-12 font-mono" @if($errors->has('recovery_code')) aria-invalid="true" @endif>
                            <p class="text-xs leading-5 text-muted">Each recovery code can only be used once.</p>
                        </div>
                    </details>

                    <button class="admin-button min-h-12 w-full gap-3">Verify and sign in <x-parish.icon name="arrow" class="size-4" /></button>
                </form>

                <div class="flex items-start gap-3 border-t border-navy/10 pt-5">
                    <x-parish.icon name="shield" class="mt-0.5 size-5 shrink-0 text-muted" />
                    <p class="text-xs leading-5 text-muted">This verification step expires shortly. If needed, start again and enter your password once more.</p>
                </div>
            </section>
        </div>

        <p class="text-center text-xs leading-5 text-muted">Private parish office workspace. Authorized personnel only.</p>
    </main>
</body>
</html>
