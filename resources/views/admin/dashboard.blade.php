@extends('admin.layout')

@section('title', 'Dashboard')

@section('content')
<div class="dashboard-page">
    <div class="page-header">
        <h1 class="page-welcome">Good day, <strong>{{ Auth::guard('admin')->user()->name ?? Auth::guard('admin')->user()->email ?? 'Admin' }}</strong>!</h1>
        <p class="page-subtitle">Sync status, health, and integration metrics</p>
    </div>

    <section class="dashboard-section">
        <h2 class="section-heading">Quick action</h2>
        <div class="card card-action card-animate" style="--ani-order: 0">
            <div class="card-action-inner">
                <div class="card-action-text">
                    <strong>Run ERP to Opera sync (manual)</strong>
                    <span class="text-muted">Incremental run: uses last sync time and pulls only new/updated ERP records.</span>
                    <span class="text-muted">Runs in background so you can keep working.</span>
                    <span class="text-muted">Auto-sync runs daily at 12:00 by default (change in Configuration).</span>
                </div>
                <form method="post" action="{{ route('admin.sync.run') }}" class="card-action-form">
                    @csrf
                    <button type="submit" class="btn btn-primary-lg">Run sync now</button>
                </form>
            </div>
        </div>
        <div class="card card-action card-animate" style="--ani-order: 0">
            <div class="card-action-inner">
                <div class="card-action-text">
                    <strong>Run full fetch (manual)</strong>
                    <span class="text-muted">Full refresh: ignores last sync and fetches all ERP records.</span>
                    <span class="text-muted">Reprocesses records (no duplicates created). Runs in background.</span>
                </div>
                <form method="post" action="{{ route('admin.sync.run-full') }}" class="card-action-form">
                    @csrf
                    <button type="submit" class="btn btn-primary-lg">Fetch all now</button>
                </form>
            </div>
        </div>
       
    </section>

    <section class="dashboard-section">
        <h2 class="section-heading">Overview</h2>
        <div class="dashboard-cards-2x2">
            <div class="paces-card paces-card-primary card-animate" style="--ani-order: 1">
                <div class="paces-card-icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div class="paces-card-body">
                    <div class="paces-card-value">{{ $lastSyncAt ? $lastSyncAt->format('M j, H:i') : '—' }}</div>
                    <div class="paces-card-label">Last sync</div>
                    <p class="paces-card-meta">When the integration last ran successfully</p>
                </div>
            </div>
            <div class="paces-card {{ ($periodFailed ?? 0) > 0 ? 'paces-card-warning' : 'paces-card-success' }} card-animate" style="--ani-order: 2">
                <div class="paces-card-icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div class="paces-card-body">
                    <div class="paces-card-value" data-success="{{ $periodSuccess ?? 0 }}" data-failed="{{ $periodFailed ?? 0 }}"><span class="text-success count-success">0</span> / <span class="text-failed count-failed">0</span></div>
                    <div class="paces-card-label">Success vs Failed</div>
                    <p class="paces-card-meta">{{ $periodLabel ?? 'All time' }}</p>
                </div>
            </div>
            <div class="paces-card paces-card-{{ $healthStatus === 'healthy' ? 'success' : ($healthStatus === 'critical' ? 'danger' : ($healthStatus === 'warning' ? 'warning' : 'info')) }} card-animate" style="--ani-order: 3">
                <div class="paces-card-icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                </div>
                <div class="paces-card-body">
                    <div class="paces-card-value"><span class="badge badge-{{ $healthStatus }}">{{ ucfirst($healthStatus) }}</span></div>
                    <div class="paces-card-label">Health</div>
                    <p class="paces-card-meta">Overall integration status</p>
                </div>
            </div>
            <div class="paces-card {{ ($syncFailures->count() + count($failedJobs)) > 0 ? 'paces-card-warning' : 'paces-card-info' }} card-animate" style="--ani-order: 4">
                <div class="paces-card-icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <div class="paces-card-body">
                    <div class="paces-card-value" data-count="{{ $syncFailures->count() + count($failedJobs) }}"><span class="count-num">0</span></div>
                    <div class="paces-card-label">Needs attention</div>
                    <p class="paces-card-meta">Pending retries &amp; failed queue jobs</p>
                </div>
            </div>
        </div>
    </section>

    <section class="dashboard-section">
        <div class="section-heading-row">
            <h2 class="section-heading">Analytics</h2>
            <form method="get" class="section-filter">
                <label class="sr-only" for="period">Period</label>
                <select id="period" name="period" class="filter-select" onchange="this.form.submit()">
                    <option value="all" {{ ($period ?? 'all') === 'all' ? 'selected' : '' }}>All time</option>
                    <option value="day" {{ ($period ?? 'all') === 'day' ? 'selected' : '' }}>Last 24 hours</option>
                    <option value="week" {{ ($period ?? 'all') === 'week' ? 'selected' : '' }}>Last 7 days</option>
                    <option value="month" {{ ($period ?? 'all') === 'month' ? 'selected' : '' }}>Last 30 days</option>
                </select>
            </form>
        </div>
        <div class="chart-grid">
            <div class="card card-chart card-animate" style="--ani-order: 5">
                <h3 class="card-title">Success vs Failed</h3>
                <div class="chart-container">
                    <canvas id="chartPie" width="300" height="280"></canvas>
                </div>
                <p class="card-caption">{{ $periodLabel ?? 'All time' }} breakdown.</p>
            </div>
            <div class="card card-chart card-animate" style="--ani-order: 6">
                <h3 class="card-title">Section overview</h3>
                <div class="chart-container">
                    <canvas id="chartSection" width="300" height="280"></canvas>
                </div>
                <p class="card-caption">Period success, execution history, failed records, failed jobs, payload audit.</p>
            </div>
        </div>
    </section>

    <section class="dashboard-section">
        <div class="card card-table card-animate" style="--ani-order: 7">
            <div class="card-header-flex">
                <h3 class="card-title">Recent runs</h3>
                <a href="{{ route('admin.execution-history') }}" class="card-header-link">View full history</a>
            </div>
            @if ($recentLogs->isEmpty())
                <div class="empty-state">
                    <p class="text-muted">No sync runs yet. Use &quot;Run sync now&quot; to start.</p>
                </div>
            @else
                <div class="table-wrap">
                    <table class="table-dashboard">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Total</th>
                                <th>Success</th>
                                <th>Failed</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentLogs->take(10) as $log)
                            <tr>
                                <td><span class="cell-time">{{ $log->created_at->format('M j, Y') }}</span> <span class="cell-muted">{{ $log->created_at->format('H:i:s') }}</span></td>
                                <td>{{ $log->total }}</td>
                                <td><span class="cell-success">{{ $log->success }}</span></td>
                                <td><span class="cell-failed">{{ $log->failed }}</span></td>
                                <td>
                                    @if($log->failed > 0)
                                        <span class="badge badge-warning">Partial</span>
                                    @else
                                        <span class="badge badge-healthy">OK</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>
</div>
@endsection

@push('head')
<style>
.dashboard-page .page-header { margin-bottom: 1.75rem; }
.dashboard-page .page-welcome {
    font-size: 1.375rem;
    font-weight: 500;
    letter-spacing: -0.02em;
    color: var(--text-primary);
    margin: 0 0 0.25rem 0;
}
.dashboard-page .page-welcome strong { font-weight: 700; color: var(--text-primary); }
.dashboard-page .page-subtitle { font-size: 0.9375rem; margin: 0; }

.dashboard-section { margin-bottom: 2rem; }
.dashboard-section:last-of-type { margin-bottom: 0; }
.section-heading {
    font-size: 0.8125rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--text-muted);
    margin: 0 0 0.75rem 0;
}
.section-heading-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
    margin-bottom: 0.75rem;
}
.section-heading-row .section-heading { margin-bottom: 0; }
.section-filter { margin-left: auto; }
.filter-select {
    appearance: none;
    border: 1px solid var(--card-border);
    background: var(--card-bg);
    color: var(--text-primary);
    padding: 0.4rem 2rem 0.4rem 0.7rem;
    border-radius: 0.5rem;
    font-size: 0.8125rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    background-image: linear-gradient(45deg, transparent 50%, var(--text-muted) 50%), linear-gradient(135deg, var(--text-muted) 50%, transparent 50%);
    background-position: calc(100% - 0.85rem) 50%, calc(100% - 0.55rem) 50%;
    background-size: 6px 6px, 6px 6px;
    background-repeat: no-repeat;
}
.filter-select:focus {
    outline: 2px solid rgba(13, 148, 136, 0.4);
    outline-offset: 2px;
}
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    border: 0;
}

/* Run sync card - Paces style */
.card-action {
    padding: 1.5rem 1.75rem;
    border-radius: 0.625rem;
    border: 1px solid var(--card-border);
    border-left: 4px solid var(--primary);
    background: var(--card-bg);
    box-shadow: 0 1px 3px rgba(0,0,0,.05);
    transition: transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s ease, border-color 0.25s ease, background 0.25s ease;
}
.card-action:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 24px rgba(13, 148, 136, 0.35);
    border-color: rgba(13, 148, 136, 0.6);
    background: linear-gradient(135deg, var(--card-bg) 0%, rgba(13, 148, 136, 0.14) 100%);
}
.card-action-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    flex-wrap: wrap;
}
.card-action-text {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}
.card-action-text strong { font-size: 1.0625rem; color: var(--text-primary); }
.card-action-text .text-muted { font-size: 0.875rem; margin: 0; }
.card-action-form { flex-shrink: 0; }
.btn-primary-lg {
    padding: 0.625rem 1.25rem;
    font-size: 0.9375rem;
    font-weight: 600;
    border-radius: 0.5rem;
    background: var(--primary);
    color: #fff;
    border: none;
    cursor: pointer;
    font-family: inherit;
    transition: background .2s, transform .1s;
}
.btn-primary-lg:hover { background: var(--accent); }
.btn-primary-lg:active { transform: scale(0.98); }

/* Paces-style stat cards - colored borders & icon */
.dashboard-cards-2x2 {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.25rem;
}
@media (max-width: 640px) {
    .dashboard-cards-2x2 { grid-template-columns: 1fr; }
}
.paces-card {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    background: var(--card-bg);
    border-radius: 0.625rem;
    padding: 1.35rem 1.5rem;
    border: 1px solid var(--card-border);
    border-left: 4px solid var(--primary);
    box-shadow: 0 1px 3px rgba(0,0,0,.05);
    transition: transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s ease, border-color 0.25s ease, background 0.25s ease;
}
.paces-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 10px 28px rgba(0,0,0,.12);
    border-color: rgba(0,0,0,.1);
    background: var(--card-bg);
}
.paces-card-primary:hover { border-left-color: #0a7c73; box-shadow: 0 10px 28px rgba(13, 148, 136, 0.28); background: rgba(13, 148, 136, 0.12); }
.paces-card-success:hover { border-left-color: #047857; box-shadow: 0 10px 28px rgba(5, 150, 105, 0.28); background: rgba(5, 150, 105, 0.12); }
.paces-card-warning:hover { border-left-color: #b45309; box-shadow: 0 10px 28px rgba(217, 119, 6, 0.28); background: rgba(217, 119, 6, 0.12); }
.paces-card-danger:hover { border-left-color: #b91c1c; box-shadow: 0 10px 28px rgba(220, 38, 38, 0.28); background: rgba(220, 38, 38, 0.12); }
.paces-card-info:hover { border-left-color: #0284c7; box-shadow: 0 10px 28px rgba(14, 165, 233, 0.28); background: rgba(14, 165, 233, 0.12); }
.paces-card-icon {
    flex-shrink: 0;
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
}
.paces-card-icon svg { width: 1.4rem; height: 1.4rem; }
.paces-card-primary { border-left-color: var(--primary); }
.paces-card-primary .paces-card-icon { background: var(--primary-light); color: var(--primary); }
.paces-card-success { border-left-color: var(--success); }
.paces-card-success .paces-card-icon { background: var(--success-light); color: var(--success); }
.paces-card-warning { border-left-color: var(--warning); }
.paces-card-warning .paces-card-icon { background: var(--warning-light); color: var(--warning); }
.paces-card-danger { border-left-color: var(--critical); }
.paces-card-danger .paces-card-icon { background: var(--critical-light); color: var(--critical); }
.paces-card-info { border-left-color: var(--info); }
.paces-card-info .paces-card-icon { background: var(--info-light); color: var(--info); }
.paces-card-body { min-width: 0; }
.paces-card-value { font-size: 1.5rem; font-weight: 700; color: var(--text-primary); line-height: 1.2; }
.paces-card-label { font-size: 0.8125rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em; margin-top: 0.2rem; }
.paces-card-meta { font-size: 0.8125rem; color: var(--text-muted); margin: 0.5rem 0 0 0; line-height: 1.4; }
.paces-card .text-success { color: var(--success); }
.paces-card .text-failed { color: var(--critical); }
.paces-card .badge { font-size: 0.875rem; padding: 0.35rem 0.65rem; }

/* Card with header row (Paces Recent Orders style) */
.card-header-flex {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 1rem;
}
.card-table .card-title { margin-bottom: 0; }
.card-header-link {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--primary);
    text-decoration: none;
}
.card-header-link:hover { text-decoration: underline; }

/* Card entrance animation */
@keyframes cardFadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.card-animate {
    animation: cardFadeInUp 0.5s ease-out forwards;
    opacity: 0;
    animation-delay: calc(0.08s * var(--ani-order, 0));
}

.card-chart { padding: 1.5rem; }
.card-chart { transition: transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s ease; }
.card-chart:hover { transform: translateY(-5px); box-shadow: 0 10px 28px rgba(0,0,0,.12); }
.card-chart .card-title { font-size: 0.9375rem; font-weight: 600; margin-bottom: 0.5rem; }
.card-caption { font-size: 0.8125rem; color: var(--text-muted); margin: 0.75rem 0 0 0; }
.card-table { transition: transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s ease; }
.card-table:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(0,0,0,.12); }

.table-wrap { overflow-x: auto; }
.table-dashboard { font-size: 0.875rem; }
.table-dashboard th { padding: 0.75rem 1rem; }
.table-dashboard td { padding: 0.75rem 1rem; }
.table-dashboard tbody tr { transition: background .15s; }
.table-dashboard tbody tr:hover { background: var(--primary-light); }
.cell-time { font-weight: 500; }
.cell-muted { color: var(--text-muted); font-size: 0.8125rem; }
.cell-success { color: var(--success); font-weight: 600; }
.cell-failed { color: var(--critical); font-weight: 600; }
.card-footer { margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border); }
.link-more { font-size: 0.875rem; color: var(--primary); text-decoration: none; font-weight: 500; }
.link-more:hover { text-decoration: underline; }
.empty-state { padding: 1.5rem 0; text-align: center; }
.empty-state .text-muted { margin: 0; }

@media (max-width: 640px) {
    .card-action-inner { flex-direction: column; align-items: stretch; }
    .card-action-form { width: 100%; }
    .btn-primary-lg { width: 100%; }
}
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function() {
    const pieData = @json($chartPie);
    const sectionData = @json($chartSectionOverview);
    const colors = { success: '#059669', failed: '#dc2626', unknown: '#64748b' };
    const sectionColors = ['#10b981', '#3b82f6', '#f59e0b', '#dc2626', '#8b5cf6'];

    if (document.getElementById('chartPie')) {
        new Chart(document.getElementById('chartPie'), {
            type: 'pie',
            data: {
                labels: pieData.labels,
                datasets: [{
                    data: pieData.values,
                    backgroundColor: [colors.success, colors.failed, colors.unknown].slice(0, pieData.values.length),
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }
    if (document.getElementById('chartSection') && sectionData.labels.length) {
        new Chart(document.getElementById('chartSection'), {
            type: 'bar',
            data: {
                labels: sectionData.labels,
                datasets: [{
                    label: 'Count',
                    data: sectionData.values,
                    backgroundColor: sectionColors.slice(0, sectionData.values.length),
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { beginAtZero: true }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: function(ctx) { return ctx.raw + ' item(s)'; } } }
                }
            }
        });
    }
})();

(function() {
    function animateValue(el, start, end, duration) {
        if (!el || end === undefined) return;
        var startTime = null;
        function step(timestamp) {
            if (!startTime) startTime = timestamp;
            var progress = Math.min((timestamp - startTime) / duration, 1);
            var easeOut = 1 - Math.pow(1 - progress, 2);
            var current = Math.round(start + (end - start) * easeOut);
            el.textContent = current;
            if (progress < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }
    function runCountUps() {
        document.querySelectorAll('.paces-card-value[data-count]').forEach(function(wrapper) {
            var el = wrapper.querySelector('.count-num');
            var end = parseInt(wrapper.getAttribute('data-count'), 10) || 0;
            if (el) animateValue(el, 0, end, 800);
        });
        document.querySelectorAll('.paces-card-value[data-success]').forEach(function(wrapper) {
            var successEl = wrapper.querySelector('.count-success');
            var failedEl = wrapper.querySelector('.count-failed');
            var successEnd = parseInt(wrapper.getAttribute('data-success'), 10) || 0;
            var failedEnd = parseInt(wrapper.getAttribute('data-failed'), 10) || 0;
            if (successEl) animateValue(successEl, 0, successEnd, 700);
            if (failedEl) animateValue(failedEl, 0, failedEnd, 700);
        });
    }
    setTimeout(runCountUps, 500);
})();
</script>
@endpush

