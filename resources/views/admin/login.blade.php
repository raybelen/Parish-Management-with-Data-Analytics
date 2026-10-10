@extends('admin.layout')
@section('title', 'Administrator sign in')
@section('content')
    <p class="text-muted">Use your parish administrator account. An authenticator code is required to access administration.</p>
    <form method="POST" action="{{ route('login.store') }}" class="admin-card grid gap-5">
        @csrf
        <x-parish.form-field id="email" name="email" label="Email address" required>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus class="admin-input">
        </x-parish.form-field>
        <x-parish.form-field id="password" name="password" label="Password" required>
            <input id="password" name="password" type="password" autocomplete="current-password" required class="admin-input">
        </x-parish.form-field>
        <button class="admin-button w-full">Continue securely</button>
    </form>
@endsection
