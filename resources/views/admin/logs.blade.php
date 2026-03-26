@extends('admin.layout')

@section('title', 'Application logs')

@section('content')
<div class="page-header">
    <h1 class="page-title">Application logs</h1>
    <p class="page-subtitle">Last {{ $linesCount }} lines of integration log – metadata only, no plain-text payloads</p>
</div>

<div class="card">
    <div class="card-title">Integration log</div>
    <form method="get" action="{{ route('admin.logs') }}" style="margin-bottom: 0.75rem;">
        <label for="lines">Lines:</label>
        <select name="lines" id="lines" onchange="this.form.submit()" style="padding: 0.35rem 0.5rem; border-radius: 0.25rem; border: 1px solid var(--border);">
            <option value="50" {{ $linesCount == 50 ? 'selected' : '' }}>50</option>
            <option value="100" {{ $linesCount == 100 ? 'selected' : '' }}>100</option>
            <option value="200" {{ $linesCount == 200 ? 'selected' : '' }}>200</option>
            <option value="500" {{ $linesCount == 500 ? 'selected' : '' }}>500</option>
        </select>
        <button type="submit" class="btn" style="margin-left: 0.5rem;">Refresh</button>
    </form>
    @if (!empty($logLines))
        <pre class="mono" style="margin: 0; padding: 1rem; background: #0f172a; color: #e2e8f0; border-radius: 0.375rem; overflow-x: auto; max-height: 70vh; overflow-y: auto; font-size: 0.8rem;">{{ implode("\n", $logLines) }}</pre>
    @else
        <p class="text-muted" style="margin: 0;">Log file empty or not found.</p>
    @endif
</div>
@endsection
