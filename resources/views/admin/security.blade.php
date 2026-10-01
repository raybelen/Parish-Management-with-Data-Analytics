@extends('admin.layout')
@section('title', 'Account security')
@section('content')
    @if (! $enabled)
        <section class="grid gap-6 bg-white p-6 sm:p-8">
            <h2 class="text-2xl">Set up your authenticator</h2>
            <p>You must finish this step before accessing administration.</p>
            @if ($qrCode)
                <p>Scan this QR code with your authenticator app. Keep the setup key private.</p>
                <div class="w-fit bg-white p-3">{!! $qrCode !!}</div>
                <p class="break-all text-sm">Manual setup key: <code>{{ $setupKey }}</code></p>
                <form method="POST" action="{{ route('admin.security.confirm') }}" class="grid gap-5">
                    @csrf
                    <x-parish.form-field id="code" name="code" label="Six-digit authenticator code" required>
                        <input id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required class="min-h-12 border border-navy/25 px-4">
                    </x-parish.form-field>
                    <button class="min-h-12 bg-navy px-6 py-3 text-ivory">Confirm authenticator</button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.security.enable') }}" class="grid gap-5">
                    @csrf
                    <x-parish.form-field id="password" name="password" label="Confirm your password" required>
                        <input id="password" name="password" type="password" autocomplete="current-password" required class="min-h-12 border border-navy/25 px-4">
                    </x-parish.form-field>
                    <button class="min-h-12 bg-navy px-6 py-3 text-ivory">Set up authenticator</button>
                </form>
            @endif
        </section>
    @else
        <section class="grid gap-5 bg-white p-6 sm:p-8">
            <h2 class="text-2xl">Two-factor authentication is active</h2>
            <p>Keep your recovery codes in a password manager or another secure place. Each code works once. Generating new codes invalidates all previous codes.</p>
            @if ($recoveryCodes)
                <ul class="grid gap-2 font-mono sm:grid-cols-2">
                    @foreach ($recoveryCodes as $recoveryCode)<li>{{ $recoveryCode }}</li>@endforeach
                </ul>
            @endif
            <form method="POST" action="{{ route('admin.security.recovery-codes') }}" class="grid gap-5">
                @csrf
                <x-parish.form-field id="recovery-password" name="password" label="Confirm your password" required>
                    <input id="recovery-password" name="password" type="password" autocomplete="current-password" required class="min-h-12 border border-navy/25 px-4">
                </x-parish.form-field>
                <button class="min-h-12 border border-navy px-6 py-3">Generate new recovery codes</button>
            </form>
        </section>
        <section class="grid gap-5 bg-white p-6 sm:p-8">
            <h2 class="text-2xl">Change password</h2>
            <p>Changing your password signs you out of all devices.</p>
            <form method="POST" action="{{ route('admin.password.update') }}" class="grid gap-5">
                @csrf
                @method('PUT')
                <x-parish.form-field id="current_password" name="current_password" label="Current password" required>
                    <input id="current_password" name="current_password" type="password" autocomplete="current-password" required class="min-h-12 border border-navy/25 px-4">
                </x-parish.form-field>
                <x-parish.form-field id="new-password" name="password" label="New password" required help="Use 12–72 characters with uppercase and lowercase letters, a number, and a symbol.">
                    <input id="new-password" name="password" type="password" autocomplete="new-password" required class="min-h-12 border border-navy/25 px-4">
                </x-parish.form-field>
                <x-parish.form-field id="password_confirmation" name="password_confirmation" label="Confirm new password" required>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="min-h-12 border border-navy/25 px-4">
                </x-parish.form-field>
                <button class="min-h-12 bg-navy px-6 py-3 text-ivory">Change password and sign out</button>
            </form>
        </section>
        <a href="{{ route('admin.dashboard') }}" class="text-link justify-self-start">Back to administration</a>
    @endif
@endsection
