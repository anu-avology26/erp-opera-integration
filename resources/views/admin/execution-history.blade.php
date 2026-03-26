@extends('admin.layout')

@section('title', 'Execution history')

@section('content')
<div class="page-header">
    <h1 class="page-title">Execution history</h1>
    <p class="page-subtitle">Sync run history with totals and errors</p>
</div>

<div class="card">
    @if ($recentLogs->count() === 0)
        <p class="text-muted" style="margin: 0;">No sync runs yet.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Total</th>
                    <th>Success</th>
                    <th>Failed</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($recentLogs as $log)
                <tr>
                    <td>{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $log->total }}</td>
                    <td>{{ $log->success }}</td>
                    <td>{{ $log->failed }}</td>
                    <td>
                        @if (!empty($log->errors))
                        <details>
                            <summary>Errors</summary>
                            <pre class="errors-pre">{{ json_encode($log->errors, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        </details>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @include('admin.partials.pagination_v2', ['paginator' => $recentLogs])
    @endif
</div>
@endsection
