@extends('admin.layout')
@section('title', 'Administrator roles')
@section('content')
    <p>Only super administrators can assign roles. You cannot change your own role.</p>
    <div class="grid gap-5">
        @foreach ($users as $user)
            <section class="grid gap-4 bg-white p-6">
                <h2 class="text-xl">{{ $user->name }}</h2>
                <p class="break-all text-sm">{{ $user->email }} · {{ $user->role }}</p>
                @if (! auth()->user()->is($user))
                    <form method="POST" action="{{ route('admin.roles.update', $user) }}" class="grid gap-4">
                        @csrf
                        @method('PATCH')
                        <x-parish.form-field :id="'role-'.$user->id" name="role" label="Role">
                            <select id="role-{{ $user->id }}" name="role" class="min-h-12 border border-navy/25 bg-white px-4">
                                @foreach (['user' => 'User (no admin access)', 'admin' => 'Administrator', 'super_admin' => 'Super administrator'] as $value => $label)
                                    <option value="{{ $value }}" @selected($user->role === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </x-parish.form-field>
                        <x-parish.form-field :id="'password-'.$user->id" name="password" label="Your password" required>
                            <input id="password-{{ $user->id }}" name="password" type="password" autocomplete="current-password" required class="min-h-12 border border-navy/25 px-4">
                        </x-parish.form-field>
                        <button class="min-h-12 bg-navy px-6 py-3 text-ivory">Update role</button>
                    </form>
                @endif
            </section>
        @endforeach
    </div>
    {{ $users->links() }}
    <a href="{{ route('admin.dashboard') }}" class="text-link justify-self-start">Back to administration</a>
@endsection
