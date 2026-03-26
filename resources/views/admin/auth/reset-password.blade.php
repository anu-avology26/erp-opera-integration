@extends('admin.auth.layout')

@section('title', 'Reset password')

@section('content')
<h1 class="auth-title">Reset password</h1>
<p class="auth-subtitle">Enter your new password below.</p>

@if ($errors->any())
    <div class="auth-message error">{{ $errors->first() }}</div>
@endif

<form class="auth-form" method="post" action="{{ route('admin.password.update') }}">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="{{ $email }}" required autocomplete="email">
    </div>
    <div class="form-group">
        <label for="password">New password</label>
        <input type="password" id="password" name="password" required autocomplete="new-password" autofocus>
    </div>
    <div class="form-group">
        <label for="password_confirmation">Confirm password</label>
        <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
    </div>
    <button type="submit" class="btn-auth">Reset password</button>
</form>

<div class="auth-footer">
    <a href="{{ route('admin.login') }}">Back to sign in</a>
</div>
@endsection
