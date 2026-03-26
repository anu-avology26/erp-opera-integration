@extends('admin.auth.layout')

@section('title', 'Forgot password')

@section('content')
<h1 class="auth-title">Forgot password</h1>
<p class="auth-subtitle">Enter your admin email and we’ll send a reset link.</p>

@if (session('status'))
    <div class="auth-message success">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="auth-message error">{{ $errors->first() }}</div>
@endif

<form class="auth-form" method="post" action="{{ route('admin.password.email') }}">
    @csrf
    <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
    </div>
    <button type="submit" class="btn-auth">Send reset link</button>
</form>

<div class="auth-footer">
    <a href="{{ route('admin.login') }}">Back to sign in</a>
</div>
@endsection
