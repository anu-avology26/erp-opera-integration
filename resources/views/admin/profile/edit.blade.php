@extends('admin.layout')

@section('title', 'Profile')

@section('content')
<div class="page-header">
    <h1 class="page-title">Profile</h1>
    <p class="page-subtitle">Edit your admin profile and password</p>
</div>

@if (session('status'))
    <div class="message">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="card" style="border-left: 4px solid var(--critical);">
        <ul style="margin: 0; padding-left: 1.25rem; color: var(--critical);">
            @foreach ($errors->all() as $e)
                <li>{{ $e }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="post" action="{{ route('admin.profile.update') }}">
    @csrf
    @method('PUT')
    <div class="card">
        <div class="card-title">Account</div>
        <div class="form-group">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" value="{{ old('name', $admin->name) }}" required class="form-input">
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email', $admin->email) }}" required class="form-input">
        </div>
    </div>

    <div class="card">
        <div class="card-title">Change password</div>
        <p class="text-muted" style="margin: 0 0 1rem 0;">Leave blank to keep your current password.</p>
        <div class="form-group">
            <label for="current_password">Current password</label>
            <input type="password" id="current_password" name="current_password" class="form-input" autocomplete="current-password">
        </div>
        <div class="form-group">
            <label for="password">New password</label>
            <input type="password" id="password" name="password" class="form-input" autocomplete="new-password">
        </div>
        <div class="form-group">
            <label for="password_confirmation">Confirm new password</label>
            <input type="password" id="password_confirmation" name="password_confirmation" class="form-input" autocomplete="new-password">
        </div>
    </div>

    <button type="submit" class="btn">Save profile</button>
</form>
@endsection
