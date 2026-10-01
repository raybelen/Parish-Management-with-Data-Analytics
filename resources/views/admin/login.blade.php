@extends('admin.layout')
@section('title', 'Administrator sign in')
@section('content')
    <p class="text-muted">Use your parish administrator account. An authenticator code is required to access administration.</p>
    <form method="POST" action="{{ route('login.store') }}" class="grid gap-6 bg-white p-6 sm:p-8">
        @csrf
        <x-parish.form-field id="email" name="email" label="Email address" required>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus class="min-h-12 border border-navy/25 px-4">
        </x-parish.form-field>
        <x-parish.form-field id="password" name="password" label="Password" required>
            <input id="password" name="password" type="password" autocomplete="current-password" required class="min-h-12 border border-navy/25 px-4">
        </x-parish.form-field>
        <button class="min-h-12 bg-navy px-6 py-3 text-ivory">Continue securely</button>
    </form>
@endsection
