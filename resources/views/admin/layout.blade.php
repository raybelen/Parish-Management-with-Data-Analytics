@php
    $parishName = auth()->check() ? \App\Support\OfficeSettings::all()['parish_name'] : config('office.defaults.parish_name');
    $navigationSections = [
        'Workspace' => [
            ['route' => 'admin.dashboard', 'label' => 'Overview', 'ability' => null, 'icon' => 'dashboard', 'active' => ['admin.dashboard']],
            ['route' => 'admin.calendar.index', 'label' => 'Calendar & schedules', 'ability' => 'schedules.view', 'icon' => 'calendar', 'active' => ['admin.calendar.*']],
            ['route' => 'admin.appointments.index', 'label' => 'Appointments', 'ability' => 'appointments.view', 'icon' => 'calendar', 'active' => ['admin.appointments.*']],
            ['route' => 'admin.records.index', 'label' => 'Service records', 'ability' => 'records.view', 'icon' => 'book', 'active' => ['admin.records.*']],
            ['route' => 'admin.notifications.index', 'label' => 'Office inbox', 'ability' => 'notifications.manage', 'icon' => 'inbox', 'active' => ['admin.notifications.*']],
        ],
        'Insights' => [
            ['route' => 'admin.reports.index', 'label' => 'Reports & exports', 'ability' => 'reports.view', 'icon' => 'report', 'active' => ['admin.reports.*']],
            ['route' => 'admin.forecasts.index', 'label' => 'Forecasts', 'ability' => 'forecasts.manage', 'icon' => 'chart', 'active' => ['admin.forecasts.*']],
        ],
        'Administration' => [
            ['route' => 'admin.website-content.index', 'label' => 'Website content', 'ability' => 'content.manage', 'icon' => 'book', 'active' => ['admin.website-content.*']],
            ['route' => 'admin.settings.index', 'label' => 'Parish settings', 'ability' => 'settings.manage', 'icon' => 'settings', 'active' => ['admin.settings.*']],
            ['route' => 'admin.users.index', 'label' => 'User accounts', 'ability' => 'manage-admin-roles', 'icon' => 'people', 'active' => ['admin.users.*']],
            ['route' => 'admin.roles.index', 'label' => 'Roles', 'ability' => 'manage-admin-roles', 'icon' => 'shield', 'active' => ['admin.roles.*']],
            ['route' => 'admin.audit.index', 'label' => 'Audit trail', 'ability' => 'audit.view', 'icon' => 'audit', 'active' => ['admin.audit.*']],
            ['route' => 'admin.security', 'label' => 'Account security', 'ability' => null, 'icon' => 'lock', 'active' => ['admin.security', 'admin.security.*', 'admin.password.*']],
        ],
    ];
    if (! config('office.analytics_enabled')) {
        $navigationSections['Insights'] = array_values(array_filter(
            $navigationSections['Insights'],
            fn (array $item): bool => $item['route'] !== 'admin.forecasts.index',
        ));
    }
@endphp
<!DOCTYPE html>
<html lang="en" class="admin-document">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Administration') | {{ $parishName }}</title>
    @vite(['resources/css/app.css', 'resources/js/admin.js'])
</head>
<body class="admin-workspace flex min-h-dvh flex-col bg-slate-50 text-navy">
    <a href="#main-content" class="fixed top-3 left-3 z-50 -translate-y-20 rounded-lg bg-navy px-4 py-3 text-sm font-semibold text-white shadow-lg transition-transform focus:translate-y-0">Skip to content</a>
    <header class="sticky top-0 z-40 border-b border-navy/10 bg-white/95 backdrop-blur-sm">
        <div class="mx-auto flex max-w-screen-2xl flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8">
            <a href="{{ auth()->check() ? route('admin.dashboard') : route('home') }}" class="flex min-w-0 items-center gap-3 rounded-lg">
                <img src="{{ asset('images/logo-modified.png') }}" alt="" class="size-10 shrink-0 object-contain">
                <span class="min-w-0">
                    <span class="block truncate font-display text-xl leading-none sm:text-2xl">{{ $parishName }}</span>
                    <span class="mt-1 block text-[0.65rem] font-semibold tracking-[0.16em] text-muted uppercase">Office administration</span>
                </span>
            </a>
            @auth
                <div class="flex items-center gap-3 text-sm">
                    <div class="hidden text-right sm:block">
                        <span class="block font-semibold leading-tight">{{ auth()->user()->name }}</span>
                        <span class="mt-0.5 block text-xs text-muted">{{ \Illuminate\Support\Str::headline(auth()->user()->role) }}</span>
                    </div>
                    <span aria-hidden="true" class="grid size-10 place-items-center rounded-full bg-ivory font-semibold text-navy">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(auth()->user()->name, 0, 1)) }}</span>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="admin-secondary !min-h-10 px-3 sm:px-4">Sign out</button></form>
                </div>
                <details class="admin-mobile-menu relative w-full lg:hidden">
                    <summary class="admin-secondary flex w-full list-none justify-between px-4 marker:hidden">
                        <span class="flex items-center gap-2"><x-parish.icon name="menu" class="size-5" />Menu</span>
                        <x-parish.icon name="chevron-down" class="menu-chevron size-4" />
                    </summary>
                    <nav aria-label="Administration" class="mt-2 max-h-[50dvh] overflow-y-auto rounded-2xl border border-navy/10 bg-white p-3">
                        @include('admin.partials.navigation', ['navigationSections' => $navigationSections])
                    </nav>
                </details>
            @endauth
        </div>
    </header>
    <div class="mx-auto grid w-full max-w-screen-2xl flex-1 lg:grid-cols-[17rem_minmax(0,1fr)]">
        @auth
            <aside class="hidden border-r border-navy/10 bg-white lg:block">
                <nav aria-label="Administration" class="sticky top-[5.25rem] max-h-[calc(100vh-5.25rem)] overflow-y-auto px-4 py-6">
                    @include('admin.partials.navigation', ['navigationSections' => $navigationSections])
                </nav>
            </aside>
        @endauth
        <main id="main-content" @class(['grid min-w-0 content-start gap-6 px-4 py-7 sm:px-6 sm:py-9 lg:px-8 xl:px-10', 'mx-auto w-full max-w-xl lg:col-span-2 lg:py-14' => ! auth()->check()])>
            @if(\App\Support\OfficeSettings::demo())<p role="status" class="admin-alert border-amber-300 bg-amber-50 text-amber-950"><span class="font-semibold">Demo workspace</span><span class="text-sm">Simulated records do not describe actual parish activity.</span></p>@endif
            <div class="flex flex-wrap items-end justify-between gap-4 border-b border-navy/10 pb-6">
                <div class="min-w-0"><h1 class="text-3xl leading-tight sm:text-4xl">@yield('title', 'Administration')</h1></div>
                <div class="flex flex-wrap items-center gap-2">@yield('actions')</div>
            </div>
            @if(session('status'))<p role="status" class="admin-alert border-emerald-200 bg-emerald-50 text-sm text-emerald-950">{{ session('status') }}</p>@endif
            @if($errors->any() || $errors->getBag('confirmTwoFactorAuthentication')->any())
                <div role="alert" class="admin-alert border-red-200 bg-red-50 text-red-950"><p class="font-semibold">Please review the highlighted information.</p><ul class="list-inside list-disc text-sm">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    @foreach($errors->getBag('confirmTwoFactorAuthentication')->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul></div>
            @endif
            @yield('content')
            <footer class="mt-2 border-t border-navy/10 pt-5 text-xs leading-5 text-muted">Private parish office workspace <span aria-hidden="true">·</span> Access and administrative changes are audited.</footer>
        </main>
    </div>
</body>
</html>
