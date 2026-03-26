@extends('admin.layout')

@section('title', 'Configuration')

@section('content')
<div class="page-header">
    <h1 class="page-title">Configuration</h1>
    <p class="page-subtitle">Read-only – edit .env and config files to change</p>
</div>

<div class="card">
    <div class="card-title">Status</div>
    <p style="margin: 0 0 0.5rem 0;">ERP: {{ $configSummary['erp_configured'] ? 'Configured' : 'Not configured' }} · Opera: {{ $configSummary['opera_configured'] ? 'Configured' : 'Not configured' }} · Payload audit: {{ $configSummary['payload_audit_enabled'] ?? false ? 'On' : 'Off' }}</p>
    <p style="margin: 0;">OHIP rate limit: {{ $configSummary['opera_rate_limit'] ?: 'Off' }} req/min · Request delay: {{ $configSummary['opera_request_delay_ms'] ?: 0 }} ms</p>
</div>

<div class="card">
    <div class="card-title">Data types</div>
    <ul style="margin: 0; padding-left: 1.25rem;">
        @foreach ($configSummary['data_types'] ?? [] as $key => $dt)
        <li>{{ $dt['label'] ?? $key }} ({{ $key }}) — command: {{ $dt['command'] ?? '—' }}</li>
        @endforeach
    </ul>
</div>

<div class="card">
    <div class="card-title">Schedules (per data type / property)</div>
    <table>
        <thead>
            <tr><th>Data type</th><th>Property</th><th>Time</th></tr>
        </thead>
        <tbody>
            @foreach ($configSummary['schedules'] ?? [] as $s)
            <tr>
                <td>{{ $s['data_type'] ?? '—' }}</td>
                <td>{{ $s['property_id'] ?? 'all' }}</td>
                <td>{{ $s['time'] ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <p class="text-muted" style="margin: 0.75rem 0 0 0; font-size: 0.8rem;">Edit <code>.env</code> and <code>config/integration.php</code>; run <code>php artisan config:clear</code> after changes.</p>
</div>

<div class="card">
    <div class="card-title">Custom Sync Time (AR Accounts)</div>
    <form id="sync_schedule_form" method="post" action="{{ route('admin.configuration.schedule') }}">
        @csrf
        <div class="form-group">
            <label for="sync_frequency">Sync frequency</label>
            <select id="sync_frequency" name="sync_frequency" class="form-input">
                @php
                    $freq = $configSummary['override_sync_frequency'] ?? '';
                @endphp
                <option value="" {{ $freq === '' ? 'selected' : '' }}>Default (daily)</option>
                <option value="every_5_minutes" {{ $freq === 'every_5_minutes' ? 'selected' : '' }}>Every 5 minutes</option>
                <option value="every_10_minutes" {{ $freq === 'every_10_minutes' ? 'selected' : '' }}>Every 10 minutes</option>
                <option value="every_15_minutes" {{ $freq === 'every_15_minutes' ? 'selected' : '' }}>Every 15 minutes</option>
                <option value="every_30_minutes" {{ $freq === 'every_30_minutes' ? 'selected' : '' }}>Every 30 minutes</option>
                <option value="hourly" {{ $freq === 'hourly' ? 'selected' : '' }}>Hourly</option>
                <option value="every_2_hours" {{ $freq === 'every_2_hours' ? 'selected' : '' }}>Every 2 hours</option>
                <option value="every_4_hours" {{ $freq === 'every_4_hours' ? 'selected' : '' }}>Every 4 hours</option>
                <option value="every_6_hours" {{ $freq === 'every_6_hours' ? 'selected' : '' }}>Every 6 hours</option>
                <option value="every_12_hours" {{ $freq === 'every_12_hours' ? 'selected' : '' }}>Every 12 hours</option>
                <option value="daily_at" {{ $freq === 'daily_at' ? 'selected' : '' }}>Daily (at time)</option>
                <option value="weekly" {{ $freq === 'weekly' ? 'selected' : '' }}>Weekly (Mon at time)</option>
                <option value="monthly" {{ $freq === 'monthly' ? 'selected' : '' }}>Monthly (1st at time)</option>
                <option value="custom_cron" {{ $freq === 'custom_cron' ? 'selected' : '' }}>Custom (cron)</option>
            </select>
        </div>
        <div class="form-group" id="custom_cron_group" style="display: none;">
            <label for="sync_cron">Cron expression (5 fields)</label>
            <input
                type="text"
                id="sync_cron"
                name="sync_cron"
                class="form-input"
                placeholder="*/8 * * * *"
                value="{{ $configSummary['override_sync_cron'] ?? '' }}"
            >
            <p class="text-muted" style="margin: 0.5rem 0 0 0; font-size: 0.8rem;">
                Examples: every 5 min = <code>*/5 * * * *</code>, every 10 min = <code>*/10 * * * *</code>,
                every 2 hours = <code>0 */2 * * *</code>, daily at 02:00 = <code>0 2 * * *</code>
            </p>
            <p id="cron_error" style="margin: 0.5rem 0 0 0; font-size: 0.85rem; color: #b91c1c; display: none;">
                Invalid cron format. Use 5 fields with spaces, e.g. <code>*/8 * * * *</code>.
            </p>
        </div>
        <button type="submit" class="btn" id="save_btn">Save</button>
        @if (!empty($configSummary['override_sync_frequency']))
            <p class="text-muted" style="margin: 0.5rem 0 0 0; font-size: 0.8rem;">
                Frequency override active: {{ $configSummary['override_sync_frequency'] }}.
            </p>
        @endif
        @if (!empty($configSummary['override_sync_cron']))
            <p class="text-muted" style="margin: 0.5rem 0 0 0; font-size: 0.8rem;">
                Custom cron active: {{ $configSummary['override_sync_cron'] }}.
            </p>
        @endif
    </form>
</div>

<script>
    (function () {
        var select = document.getElementById('sync_frequency');
        var cronGroup = document.getElementById('custom_cron_group');
        var cronInput = document.getElementById('sync_cron');
        var cronError = document.getElementById('cron_error');
        var form = document.getElementById('sync_schedule_form');
        if (!select || !cronGroup || !cronInput || !cronError || !form) {
            return;
        }
        function isCronValid(val) {
            var parts = val.split(/\s+/).filter(function (p) { return p !== ''; });
            if (parts.length !== 5) {
                return false;
            }
            return parts.every(function (p) {
                return /^[\d*/,\-]+$/.test(p);
            });
        }
        function validateCron(showError) {
            var val = cronInput.value.trim();
            if (select.value !== 'custom_cron') {
                cronError.style.display = 'none';
                return true;
            }
            if (val === '') {
                if (showError) {
                    cronError.innerHTML = 'Cron expression is required. Example: <code>*/8 * * * *</code>.';
                    cronError.style.display = 'block';
                } else {
                    cronError.style.display = 'none';
                }
                return false;
            }
            var ok = isCronValid(val);
            if (!ok && showError) {
                cronError.innerHTML = 'Invalid cron format. Use 5 fields with spaces, e.g. <code>*/8 * * * *</code>.';
                cronError.style.display = 'block';
            } else if (ok) {
                cronError.style.display = 'none';
            } else {
                // user is typing; only show if invalid and not empty
                cronError.style.display = 'block';
            }
            return ok;
        }
        window.toggleCronInput = function (el) {
            var value = el && el.value ? el.value : '';
            cronGroup.style.display = value === 'custom_cron' ? 'block' : 'none';
            validateCron(false);
        };
        select.addEventListener('change', function () {
            window.toggleCronInput(select);
        });
        cronInput.addEventListener('input', function () {
            validateCron(false);
        });
        form.addEventListener('submit', function (e) {
            if (!validateCron(true)) {
                e.preventDefault();
            }
        });
        window.toggleCronInput(select);
    })();
</script>

<div class="card">
    <div class="card-title">Clear Sync History (Testing)</div>
    <form method="post" action="{{ route('admin.configuration.clear-history') }}">
        @csrf
        <p class="text-muted" style="margin: 0 0 0.75rem 0;">
            This clears execution history and failed records for ERP → Opera testing.
        </p>
        <button type="submit" class="btn btn-secondary">Clear History</button>
    </form>
</div>
@endsection



