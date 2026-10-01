@extends('admin.layout')
@section('title', 'Administration')
@section('content')
    <div class="grid gap-5 bg-white p-8">
        <p>Welcome, {{ auth()->user()->name }}. Your administrator session is protected with two-factor authentication.</p>
        <a href="{{ route('admin.security') }}" class="text-link justify-self-start">Account security</a>
        @can('manage-admin-roles')
            <a href="{{ route('admin.roles.index') }}" class="text-link justify-self-start">Manage administrator roles</a>
        @endcan
    </div>
@endsection
