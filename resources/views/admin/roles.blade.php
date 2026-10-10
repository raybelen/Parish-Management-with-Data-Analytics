@extends('admin.layout')
@section('title', 'Administrator roles')
@section('content')
    <p class="text-sm text-muted">Only super administrators can assign roles. You cannot change your own role.</p>
    <div class="grid gap-4 xl:grid-cols-2">
        @foreach ($users as $user)
            <section class="admin-card grid content-start gap-4">
                <div><h2 class="text-xl">{{ $user->name }}</h2><p class="mt-1 break-all text-sm text-muted">{{ $user->email }} · {{ \Illuminate\Support\Str::headline($user->role) }}</p></div>
                @if (! auth()->user()->is($user))
                    <form method="POST" action="{{ route('admin.roles.update', $user) }}" class="grid gap-4">
                        @csrf
                        @method('PATCH')
                        <x-parish.form-field :id="'role-'.$user->id" name="role" label="Role">
                            <select id="role-{{ $user->id }}" name="role" class="admin-input">
                                @foreach (['user' => 'User (no admin access)', 'staff' => 'Staff / secretary', 'priest' => 'Priest (assigned appointments)', 'admin' => 'Administrator', 'super_admin' => 'Super administrator'] as $value => $label)
                                    <option value="{{ $value }}" @selected($user->role === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </x-parish.form-field>
                        <x-parish.form-field :id="'password-'.$user->id" name="password" label="Your password" required>
                            <input id="password-{{ $user->id }}" name="password" type="password" autocomplete="current-password" required class="admin-input">
                        </x-parish.form-field>
                        <button class="admin-button">Update role</button>
                    </form>
                @endif
            </section>
        @endforeach
    </div>
    {{ $users->links() }}
    <a href="{{ route('admin.dashboard') }}" class="text-link justify-self-start">Back to administration</a>
@endsection
