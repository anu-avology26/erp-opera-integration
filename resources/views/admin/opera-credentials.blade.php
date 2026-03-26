@extends('admin.layout')

@section('title', 'Environment Credentials')

@section('content')
<div class="page-header">
    <h1 class="page-title">Environment Credentials</h1>
    <p class="page-subtitle">Manage OHIP and ERP overrides here. Stored values apply immediately without editing <code>.env</code>.</p>
</div>

@if (session('message'))
    <div class="notice" style="margin-bottom: 1rem;">{{ session('message') }}</div>
@endif

@if ($errors->any())
    <div class="errors-pre" style="margin-bottom: 1rem;">{{ implode("
", $errors->all()) }}</div>
@endif

<form method="post" action="{{ route('admin.opera-credentials.update') }}">
    @csrf

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; align-items: start;">
        <section class="card" style="margin: 0;">
            <h2 class="card-title">OHIP Credentials</h2>
            @foreach ($ohipFields as $key => $label)
                <div class="form-group">
                    <label for="ohip_{{ $key }}">{{ $label }}</label>
                    <input
                        type="{{ $key === 'client_secret' ? 'password' : 'text' }}"
                        id="ohip_{{ $key }}"
                        name="ohip[{{ $key }}]"
                        class="form-input"
                        value="{{ $key === 'client_secret' ? '' : old('ohip.' . $key, $ohipOverrides[$key] ?? '') }}"
                        placeholder="{{ $key === 'client_secret' ? 'Current: (hidden)' : 'Current: ' . ($ohipEffective[$key] ?? '') }}"
                    >
                </div>
            @endforeach
            <p class="text-muted" style="margin-top: 0.75rem; margin-bottom: 0;">
                Use this section to switch OHIP / Opera environments by overriding the gateway and token-related values.
            </p>
        </section>

        <section class="card" style="margin: 0;">
            <h2 class="card-title">ERP Credentials</h2>
            @foreach ($erpFields as $key => $label)
                <div class="form-group">
                    <label for="erp_{{ $key }}">{{ $label }}</label>
                    <input
                        type="{{ $key === 'client_secret' ? 'password' : 'text' }}"
                        id="erp_{{ $key }}"
                        name="erp[{{ $key }}]"
                        class="form-input"
                        value="{{ $key === 'client_secret' ? '' : old('erp.' . $key, $erpOverrides[$key] ?? '') }}"
                        placeholder="{{ $key === 'client_secret' ? 'Current: (hidden)' : 'Current: ' . ($erpEffective[$key] ?? '') }}"
                    >
                </div>
            @endforeach
            <p class="text-muted" style="margin-top: 0.75rem; margin-bottom: 0;">
                Use this section to switch ERP environments by overriding the API URL, tenant, client, and customer path values.
            </p>
        </section>
    </div>

    <div style="margin-top: 1rem; display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
        <button type="submit" class="btn btn-success">Save Environment Overrides</button>
        <span class="text-muted">Leave a field empty to keep using the current environment value. Secret fields stay unchanged when left blank.</span>
    </div>
</form>
@endsection
