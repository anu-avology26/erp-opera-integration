@extends('admin.auth.layout')

@section('title', 'Login')

@section('content')
<h1 class="auth-title">Sign in</h1>
<p class="auth-subtitle">Admin dashboard</p>

@if (session('status'))
    <div class="auth-message success">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="auth-message error">{{ $errors->first() }}</div>
@endif

<form class="auth-form" method="post" action="{{ route('admin.login.post') }}">
    @csrf
    <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
    </div>
    <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
    </div>
    <div class="form-group checkbox">
        <input type="checkbox" id="remember" name="remember">
        <label for="remember">Remember me</label>
    </div>
    <button type="submit" class="btn-auth">Sign in</button>
</form>

<div class="auth-footer">
    <a href="{{ route('admin.password.request') }}">Forgot password?</a>
</div>
@endsection
