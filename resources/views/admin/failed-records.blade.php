@extends('admin.layout')

@section('title', 'Failed records')

@section('content')
<div class="page-header">
    <h1 class="page-title">Failed records</h1>
    <p class="page-subtitle">Retry without reprocessing successful records</p>
</div>

<div class="card">
    <p class="text-muted" style="margin: 0 0 0.75rem 0;">Error message, API response code, and customer/account reference are stored.</p>
    @if ($syncFailures->count() > 0)
        <form method="post" action="{{ route('admin.sync.retry-failed') }}" style="margin-bottom: 1rem;">
            @csrf
            <button type="submit" class="btn">Retry failed records</button>
        </form>
        <table>
            <thead>
                <tr>
                    <th>ERP number</th>
                    <th>Opera account</th>
                    <th>Error message</th>
                    <th>Response code</th>
                    <th>Sync run</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($syncFailures as $f)
                <tr>
                    <td>{{ $f->erp_number }}</td>
                    <td>{{ $f->opera_account_number ?? '—' }}</td>
                    <td style="max-width: 20rem; overflow: hidden; text-overflow: ellipsis;">{{ $f->error_message }}</td>
                    <td>{{ $f->response_code ?? '—' }}</td>
                    <td>{{ $f->syncLog?->created_at?->format('Y-m-d H:i') ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @include('admin.partials.pagination_v2', ['paginator' => $syncFailures])
    @else
        <p class="text-muted" style="margin: 0;">No unretried failed records.</p>
    @endif
</div>
@endsection
