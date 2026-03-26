@extends('admin.layout')

@section('title', 'Upload Record Details')

@section('content')
    <div class="page-header">
        <h1 class="page-title">Upload Record Details</h1>
        <p class="page-subtitle">
            File: <strong>{{ $meta['original_name'] ?? 'unknown' }}</strong> &middot;
            Record #{{ $recordIndex + 1 }}
        </p>
    </div>

    <section class="card">
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem;">
            <h2 class="card-title" style="margin: 0;">Full Record</h2>
            <a class="btn btn-secondary" href="{{ route('admin.uploads.index') }}">Back to Uploads</a>
        </div>

        <div class="table-wrap" style="margin-top: 0.75rem; overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th style="width: 30%;">Field</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($record as $key => $value)
                        <tr>
                            <td>{{ $key }}</td>
                            <td>{{ is_scalar($value) || $value === null ? $value : json_encode($value) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
