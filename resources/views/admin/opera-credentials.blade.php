@extends('admin.layout')

@section('title', 'Opera Credentials')

@section('content')
<div class="page-header">
    <h1 class="page-title">Opera Credentials</h1>
    <p class="page-subtitle">Overrides apply immediately without editing .env</p>
</div>

@if (session('message'))
    <div class="notice" style="margin-bottom: 1rem;">{{ session('message') }}</div>
@endif

@if ($errors->any())
    <div class="errors-pre" style="margin-bottom: 1rem;">{{ implode("\n", $errors->all()) }}</div>
@endif

<section class="card">
    <h2 class="card-title">Update Credentials</h2>
    <form method="post" action="{{ route('admin.opera-credentials.update') }}">
        @csrf
        @foreach ($fields as $key => $label)
            <div class="form-group">
                <label for="{{ $key }}">{{ $label }}</label>
                <input
                    type="{{ in_array($key, ['client_secret']) ? 'password' : 'text' }}"
                    id="{{ $key }}"
                    name="{{ $key }}"
                    class="form-input"
                    value="{{ $key === 'client_secret' ? '' : old($key, $overrides[$key] ?? '') }}"
                    placeholder="{{ $key === 'client_secret' ? 'Current: (hidden)' : 'Current: ' . ($effective[$key] ?? '') }}"
                >
            </div>
        @endforeach
        <button type="submit" class="btn btn-success">Save Overrides</button>
    </form>
    <p class="text-muted" style="margin-top: 0.75rem;">
        Leave a field empty to keep the current value. Overrides are stored in the database.
    </p>
</section>
@endsection
