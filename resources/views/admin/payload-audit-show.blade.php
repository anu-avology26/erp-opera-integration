@extends('admin.layout')

@section('title', 'Payload audit entry')

@section('content')
<div class="page-header">
    <h1 class="page-title">Payload audit entry</h1>
    <p class="page-subtitle">Decrypted payload view (admin only).</p>
    <a href="{{ route('admin.payload-audit') }}" class="btn btn-secondary">Back</a>
</div>

<div class="card" style="margin-bottom: 1rem;">
    <table>
        <tbody>
            <tr>
                <th style="width: 180px;">Direction</th>
                <td>{{ $entry->direction }}</td>
            </tr>
            <tr>
                <th>Entity ref</th>
                <td>{{ $entry->entity_ref ?? '-' }}</td>
            </tr>
            <tr>
                <th>Status</th>
                <td>{{ $entry->status ?? '-' }}</td>
            </tr>
            <tr>
                <th>Response code</th>
                <td>{{ $entry->response_code ?? '-' }}</td>
            </tr>
            <tr>
                <th>Created</th>
                <td>{{ $entry->created_at->format('Y-m-d H:i:s') }}</td>
            </tr>
            <tr>
                <th>Expires</th>
                <td>{{ $entry->expires_at?->format('Y-m-d') ?? '-' }}</td>
            </tr>
        </tbody>
    </table>
</div>

<div class="card">
    @if (! empty($payload))
        <pre style="white-space: pre-wrap; word-break: break-word; margin: 0;">{{ $payload }}</pre>
    @else
        <p class="text-muted" style="margin: 0;">No payload available or decryption failed.</p>
    @endif
</div>
@endsection
