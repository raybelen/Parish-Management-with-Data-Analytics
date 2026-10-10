@extends('admin.layout')
@section('title', 'Verify your sign in')
@section('content')
    <p class="text-muted">Enter the six-digit code from your authenticator, or use one unused recovery code.</p>
    <form method="POST" action="{{ route('two-factor.login.store') }}" class="admin-card grid gap-5">
        @csrf
        <x-parish.form-field id="code" name="code" label="Authenticator code">
            <input id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" autofocus class="admin-input font-mono tracking-[0.3em]">
        </x-parish.form-field>
        <details class="border-t border-navy/15 pt-4">
            <summary class="cursor-pointer py-2">Use a recovery code instead</summary>
            <x-parish.form-field id="recovery_code" name="recovery_code" label="Recovery code" class="mt-4">
                <input id="recovery_code" name="recovery_code" autocomplete="off" class="admin-input font-mono">
            </x-parish.form-field>
        </details>
        <button class="admin-button w-full">Verify and sign in</button>
    </form>
    <a href="{{ route('login') }}" class="text-link justify-self-start">Start again</a>
@endsection
