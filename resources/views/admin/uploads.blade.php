@extends('admin.layout')

@section('title', 'File Uploads')

@section('content')
    <div class="page-header">
        <h1 class="page-title">File Uploads</h1>
        <p class="page-subtitle">Upload CSV or JSON files for ERP data and push to Opera (Company Profile + AR Account) for testing.</p>
    </div>

    <section class="card">
        <h2 class="card-title">Upload AR Accounts File</h2>
        <form method="post" action="{{ route('admin.uploads.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label for="data_file">CSV or JSON file</label>
                <input type="file" id="data_file" name="data_file" class="form-input" required>
            </div>
            <button type="submit" class="btn">Upload</button>
        </form>
        <p class="text-muted" style="margin-top: 0.75rem;">Expected columns should include <strong>Account ID</strong> and <strong>AR Number</strong>. <strong>Property</strong> is optional if you want to pass a hotel/property code. Use Field Mapping to match your column names.</p>
    </section>

    <section class="card">
        <h2 class="card-title">Latest Upload Preview</h2>
        @if ($meta)
            <p class="text-muted" style="margin: 0 0 0.5rem 0;">
                File: <strong>{{ $meta['original_name'] ?? 'unknown' }}</strong> ·
                Rows: {{ $meta['row_count'] ?? 0 }} ·
                Uploaded: {{ $meta['uploaded_at'] ?? 'unknown' }}
            </p>

            @if (!empty($meta['errors']))
                <div class="errors-pre">{{ implode("\n", $meta['errors']) }}</div>
            @endif

            @if (!empty($previewRows))
                @php
                    $allColumns = array_keys($previewRows[0] ?? []);
                    $preferred = [
                        'Account ID',
                        'account_id',
                        'accountId',
                        'profileId',
                        'companyId',
                        'AR Number',
                        'ar_number',
                        'arNumber',
                        'accountNo',
                        'Property',
                        'property',
                        'hotelId',
                    ];
                    $visibleColumns = array_values(array_intersect($preferred, $allColumns));
                    if ($visibleColumns === []) {
                        $visibleColumns = array_slice($allColumns, 0, 6);
                    }
                @endphp
                <div class="table-wrap" style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                @foreach ($visibleColumns as $col)
                                    <th>{{ $col }}</th>
                                @endforeach
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($previewRows as $rowIndex => $row)
                                <tr>
                                    @foreach ($visibleColumns as $col)
                                        @php($value = $row[$col] ?? null)
                                        <td>{{ is_scalar($value) || $value === null ? $value : json_encode($value) }}</td>
                                    @endforeach
                                    <td>
                                        <a class="btn btn-secondary" href="{{ route('admin.uploads.show', ['index' => $baseIndex + $rowIndex]) }}">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if (($totalPages ?? 1) > 1)
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.75rem;">
                        <div class="text-muted">Page {{ $page }} of {{ $totalPages }}</div>
                        <div style="display: flex; gap: 0.5rem;">
                            @if ($page > 1)
                                <a class="btn btn-secondary" href="{{ route('admin.uploads.index', ['page' => $page - 1]) }}">Prev</a>
                            @endif
                            @if ($page < $totalPages)
                                <a class="btn btn-secondary" href="{{ route('admin.uploads.index', ['page' => $page + 1]) }}">Next</a>
                            @endif
                        </div>
                    </div>
                @endif
            @else
                <p class="text-muted" style="margin: 0;">No preview available.</p>
            @endif

            <form method="post" action="{{ route('admin.uploads.run') }}" style="margin-top: 1rem;">
                @csrf
                <button type="submit" class="btn btn-success">Run Sync From Upload</button>
            </form>
        @else
            <p class="text-muted" style="margin: 0;">No upload found yet.</p>
        @endif
    </section>
@endsection
