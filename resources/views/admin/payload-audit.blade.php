@extends('admin.layout')

@section('title', 'Payload audit')

@section('content')
<div class="page-header">
    <h1 class="page-title">Payload audit</h1>
    <p class="page-subtitle">Metadata only - no plain-text payloads. Encrypted payloads retained for limited period.</p>
</div>

<div class="card">
    @if ($payloadAuditRecent->count() > 0)
        <table>
            <thead>
                <tr>
                    <th>Direction</th>
                    <th>Entity ref</th>
                    <th>Status</th>
                    <th>Response code</th>
                    <th>Created</th>
                    <th>Expires</th>
                    <th>Payload</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($payloadAuditRecent as $a)
                <tr>
                    @php
                        $status = strtolower((string) ($a->status ?? ''));
                        $isSuccess = $status === 'success';
                        $isFailed = $status === 'failed';
                        $statusColor = $isSuccess ? '#15803d' : ($isFailed ? '#b91c1c' : '#6b7280');
                        $codeColor = $isSuccess ? '#15803d' : ($isFailed ? '#b91c1c' : '#6b7280');
                    @endphp
                    <td>{{ $a->direction }}</td>
                    <td>{{ $a->entity_ref ?? '-' }}</td>
                    <td><span style="color: {{ $statusColor }}; font-weight: 600;">{{ $a->status ?? '-' }}</span></td>
                    <td><span style="color: {{ $codeColor }}; font-weight: 600;">{{ $a->response_code ?? '-' }}</span></td>
                    <td>{{ $a->created_at->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $a->expires_at?->format('Y-m-d') ?? '-' }}</td>
                    <td><a href="{{ route('admin.payload-audit.show', $a) }}">View</a></td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @include('admin.partials.pagination_v2', ['paginator' => $payloadAuditRecent])
    @else
        <p class="text-muted" style="margin: 0;">No payload audit entries. Enable with <code>PAYLOAD_AUDIT_ENABLED=true</code> or entries appear on errors.</p>
    @endif
</div>
@endsection
