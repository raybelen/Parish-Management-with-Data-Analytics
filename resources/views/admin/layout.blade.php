<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Administration') | St. John Nepomucene Parish</title>
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen">
    <header class="bg-navy text-ivory">
        <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4 px-6 py-5">
            <a href="{{ route('home') }}" class="font-display text-xl">St. John Nepomucene Parish</a>
            @auth
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="min-h-11 border border-ivory/40 px-5 text-sm">Sign out</button>
                </form>
            @endauth
        </div>
    </header>
    <main class="mx-auto grid max-w-3xl gap-8 px-6 py-12">
        <div>
            <p class="section-label text-muted">Parish administration</p>
            <h1 class="mt-4 text-4xl">@yield('title', 'Administration')</h1>
        </div>
        @if (session('status'))
            <p role="status" class="border-l-4 border-gold bg-white p-5">{{ session('status') }}</p>
        @endif
        @if ($errors->any() || $errors->getBag('confirmTwoFactorAuthentication')->any())
            <div role="alert" class="border-l-4 border-red-700 bg-red-50 p-5 text-red-900">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    @foreach ($errors->getBag('confirmTwoFactorAuthentication')->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif
        @yield('content')
    </main>
</body>
</html>
