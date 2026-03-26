@extends('admin.layout')

@section('title', 'Failed jobs')

@section('content')
<div class="page-header">
    <h1 class="page-title">Failed jobs</h1>
    <p class="page-subtitle">Queue jobs that failed (latest first)</p>
</div>

<div class="card">
    @if ($failedJobs->count() > 0)
        <table>
            <thead>
                <tr>
                    <th>Queue</th>
                    <th>Failed at</th>
                    <th>Exception</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($failedJobs as $job)
                <tr>
                    <td>{{ $job->queue ?? 'default' }}</td>
                    <td>{{ \Carbon\Carbon::parse($job->failed_at)->format('Y-m-d H:i:s') }}</td>
                    <td class="mono" style="max-width: 28rem; overflow: hidden; text-overflow: ellipsis;">{{ $job->exception ?? '' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @include('admin.partials.pagination_v2', ['paginator' => $failedJobs])
    @else
        <p class="text-muted" style="margin: 0;">No failed jobs.</p>
    @endif
</div>
@endsection
