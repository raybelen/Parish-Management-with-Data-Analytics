@extends('admin.layout')
@section('title', 'User accounts')
@section('actions')<a class="admin-secondary" href="{{ route('admin.roles.index') }}">Manage roles</a>@endsection
@section('content')
    <div class="admin-card overflow-x-auto !p-0"><table class="admin-table"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Two-factor authentication</th></tr></thead><tbody>@foreach($users as $user)<tr><td>{{ $user->name }}</td><td>{{ $user->email }}</td><td>{{ str_replace('_', ' ', $user->role) }}</td><td>{{ $user->hasEnabledTwoFactorAuthentication() ? 'Enrolled' : 'Enrollment required' }}</td></tr>@endforeach</tbody></table></div>{{ $users->links() }}
    <section class="admin-card"><h2 class="mb-4 text-2xl">Create office account</h2><form class="grid gap-4 sm:grid-cols-2" method="POST" action="{{ route('admin.users.store') }}">@csrf
        <x-admin.field label="Full name" name="name" required /><x-admin.field label="Email" name="email" type="email" required /><x-admin.field label="Role" name="role"><select name="role" class="admin-input">@foreach(['staff','priest','admin','super_admin'] as $role)<option value="{{ $role }}">{{ \Illuminate\Support\Str::headline($role) }}</option>@endforeach</select></x-admin.field>
        <x-admin.field label="Your current password" name="password" type="password" autocomplete="current-password" required />
        <x-admin.field label="New user's password (12+ characters)" name="new_password" type="password" autocomplete="new-password" required /><x-admin.field label="Confirm new password" name="new_password_confirmation" type="password" autocomplete="new-password" required />
        <p class="text-sm text-muted">Use uppercase and lowercase letters, numbers and symbols. Each user must enroll in two-factor authentication before entering the office workspace.</p><button class="admin-button">Create account</button>
    </form></section>
@endsection
