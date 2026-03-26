<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RetryFailedSyncJob;
use App\Jobs\SyncErpToOperaAccountsJob;
use App\Models\PayloadAudit;
use App\Models\SyncFailure;
use App\Models\SyncLog;
use App\Models\SyncState;
use App\Models\IntegrationSetting;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class SyncDashboardController extends Controller
{
    /**
     * Dashboard data
     * @return array
     */
    protected function dashboardData(): array
    {
        $period = request()->get('period', 'all');
        if (! in_array($period, ['day', 'week', 'month', 'all'], true)) {
            $period = 'all';
        }
        $periodLabel = match ($period) {
            'day' => 'Last 24 hours',
            'week' => 'Last 7 days',
            'month' => 'Last 30 days',
            default => 'All time',
        };

        $lastSyncAt = SyncState::where('sync_type', SyncErpToOperaAccountsJob::SYNC_TYPE)->first()?->last_sync_at;
        $lastRun = SyncLog::where('type', SyncErpToOperaAccountsJob::LOG_TYPE)->latest('id')->first();
        $recentLogs = SyncLog::where('type', SyncErpToOperaAccountsJob::LOG_TYPE)->orderByDesc('created_at')->limit(20)->get();
        $syncFailures = collect([]);
        if (DB::getSchemaBuilder()->hasTable('sync_failures')) {
            $syncFailures = SyncFailure::with('syncLog')->whereNull('retried_at')->orderByDesc('id')->limit(50)->get();
        }
        $failedJobs = [];
        if (DB::getSchemaBuilder()->hasTable('failed_jobs')) {
            $failedJobs = DB::table('failed_jobs')->orderByDesc('failed_at')->limit(10)->get();
        }
        $failureRate = null;
        if ($lastRun && $lastRun->total > 0) {
            $failureRate = round(100 * $lastRun->failed / $lastRun->total, 1);
        }
        $healthStatus =  $this->healthStatus($lastRun, $failureRate);
        $periodQuery = SyncLog::where('type', SyncErpToOperaAccountsJob::LOG_TYPE);
        if ($period !== 'all') {
            $startAt = match ($period) {
                'day' => now()->subDay(),
                'week' => now()->subDays(7),
                'month' => now()->subDays(30),
                default => null,
            };
            if ($startAt !== null) {
                $periodQuery->where('created_at', '>=', $startAt);
            }
        }
        $periodSuccess = (int) $periodQuery->sum('success');
        $periodFailed = (int) $periodQuery->sum('failed');
        $periodTotal = $periodSuccess + $periodFailed;

        $chartPie = ['labels' => ['Success', 'Failed'], 'values' => [$periodSuccess, $periodFailed]];
        if ($periodTotal === 0) {
            $chartPie = ['labels' => ['No data'], 'values' => [1]];
        }
        $executionHistoryQuery = SyncLog::where('type', SyncErpToOperaAccountsJob::LOG_TYPE);
        if ($period !== 'all') {
            $startAt = match ($period) {
                'day' => now()->subDay(),
                'week' => now()->subDays(7),
                'month' => now()->subDays(30),
                default => null,
            };
            if ($startAt !== null) {
                $executionHistoryQuery->where('created_at', '>=', $startAt);
            }
        }
        $executionHistoryCount = (int) $executionHistoryQuery->count();

        $pendingRetriesCount = 0;
        if (DB::getSchemaBuilder()->hasTable('sync_failures')) {
            $pendingRetriesQuery = SyncFailure::whereNull('retried_at');
            if ($period !== 'all') {
                $startAt = match ($period) {
                    'day' => now()->subDay(),
                    'week' => now()->subDays(7),
                    'month' => now()->subDays(30),
                    default => null,
                };
                if ($startAt !== null) {
                    $pendingRetriesQuery->where('created_at', '>=', $startAt);
                }
            }
            $pendingRetriesCount = (int) $pendingRetriesQuery->count();
        }

        $failedJobsCount = 0;
        if (DB::getSchemaBuilder()->hasTable('failed_jobs')) {
            $failedJobsQuery = DB::table('failed_jobs');
            if ($period !== 'all') {
                $startAt = match ($period) {
                    'day' => now()->subDay(),
                    'week' => now()->subDays(7),
                    'month' => now()->subDays(30),
                    default => null,
                };
                if ($startAt !== null) {
                    $failedJobsQuery->where('failed_at', '>=', $startAt);
                }
            }
            $failedJobsCount = (int) $failedJobsQuery->count();
        }

        $payloadAuditCount = 0;
        if (DB::getSchemaBuilder()->hasTable('payload_audits')) {
            $payloadAuditQuery = PayloadAudit::query();
            if ($period !== 'all') {
                $startAt = match ($period) {
                    'day' => now()->subDay(),
                    'week' => now()->subDays(7),
                    'month' => now()->subDays(30),
                    default => null,
                };
                if ($startAt !== null) {
                    $payloadAuditQuery->where('created_at', '>=', $startAt);
                }
            }
            $payloadAuditCount = (int) $payloadAuditQuery->count();
        }
        $chartSectionOverview = [
            'labels' => ['Success (period)', 'Execution history', 'Pending retries', 'Failed jobs', 'Payload audit'],
            'values' => [$periodSuccess, $executionHistoryCount, $pendingRetriesCount, $failedJobsCount, $payloadAuditCount],
        ];
        return compact(
            'lastSyncAt',
            'lastRun',
            'recentLogs',
            'syncFailures',
            'failedJobs',
            'failureRate',
            'healthStatus',
            'chartPie',
            'chartSectionOverview',
            'period',
            'periodLabel',
            'periodSuccess',
            'periodFailed'
        );
    }
    /**
     * Dashboard
     * @return View
     */
    public function dashboard(): View
    {
        $data = $this->dashboardData();
        return view('admin.dashboard', $data);
    }
    /**
     * Execution history
     * @return View
     */
    public function executionHistory(): View
    {
        $recentLogs = SyncLog::where('type', SyncErpToOperaAccountsJob::LOG_TYPE)
            ->orderByDesc('created_at')
            ->paginate(20);
        return view('admin.execution-history', ['recentLogs' => $recentLogs]);
    }
    /**
     * Failed records
     * @return View
     */
    public function failedRecords(): View
    {
        $syncFailures = DB::getSchemaBuilder()->hasTable('sync_failures')
            ? SyncFailure::with('syncLog')->whereNull('retried_at')->orderByDesc('id')->paginate(25)
            : new LengthAwarePaginator([], 0, 25);
        return view('admin.failed-records', ['syncFailures' => $syncFailures]);
    }
    /**
     * Failed jobs
     * @return View
     */
    public function failedJobs(): View
    {
        $failedJobs = DB::getSchemaBuilder()->hasTable('failed_jobs')
            ? DB::table('failed_jobs')->orderByDesc('failed_at')->paginate(20)
            : new LengthAwarePaginator([], 0, 20);
        return view('admin.failed-jobs', ['failedJobs' => $failedJobs]);
    }
    /**
     * Payload audit
     * @return View
     */
    public function payloadAudit(): View
    {
        $payloadAuditRecent = DB::getSchemaBuilder()->hasTable('payload_audit')
            ? PayloadAudit::orderByDesc('id')->paginate(25)
            : new LengthAwarePaginator([], 0, 25);
        return view('admin.payload-audit', ['payloadAuditRecent' => $payloadAuditRecent]);
    }
    /**
     * View a single payload audit entry (decrypted).
     * @param PayloadAudit $payloadAudit
     * @return View
     */
    public function payloadAuditShow(PayloadAudit $payloadAudit): View
    {
        $payload = null;
        if (! empty($payloadAudit->payload_encrypted)) {
            try {
                $decrypted = Crypt::decryptString($payloadAudit->payload_encrypted);
                $decoded = json_decode($decrypted, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $payload = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                } else {
                    $payload = $decrypted;
                }
            } catch (\Throwable) {
                $payload = null;
            }
        }

        return view('admin.payload-audit-show', [
            'entry' => $payloadAudit,
            'payload' => $payload,
        ]);
    }
    /**
     * Configuration
     * @return View
     */
    public function configuration(): View
    {
        $overrideSyncTime = IntegrationSetting::getValue('sync_time');
        $overrideSyncFrequency = IntegrationSetting::getValue('sync_frequency');
        $overrideSyncCron = IntegrationSetting::getValue('sync_cron');
        $configSummary = [
            'data_types' => config('integration.data_types', []),
            'schedules' => config('integration.schedules', []),
            'opera_rate_limit' => config('opera.rate_limit_per_minute', 0),
            'opera_request_delay_ms' => config('opera.request_delay_ms', 0),
            'erp_configured' => ! empty(config('erp.base_url')),
            'opera_configured' => ! empty(config('opera.gateway_url')),
            'payload_audit_enabled' => config('integration.payload_audit_enabled', false),
            'override_sync_time' => $overrideSyncTime,
            'override_sync_frequency' => $overrideSyncFrequency,
            'override_sync_cron' => $overrideSyncCron,
        ];
        return view('admin.configuration', ['configSummary' => $configSummary]);
    }

    public function updateSchedule(Request $request)
    {
        $time = $request->input('sync_time');
        $frequency = $request->input('sync_frequency');
        $cronExpr = $request->input('sync_cron');

        if (is_string($time) && $time !== '') {
            if (! preg_match('/^\d{2}:\d{2}$/', $time)) {
                return back()->with('message', 'Invalid time format. Use HH:MM (24h).');
            }
            [$hour, $minute] = array_map('intval', explode(':', $time));
            if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
                return back()->with('message', 'Invalid time value. Use HH:MM (24h).');
            }
            IntegrationSetting::setValue('sync_time', sprintf('%02d:%02d', $hour, $minute));
        } else {
            IntegrationSetting::setValue('sync_time', null);
        }

        $allowedFrequencies = [
            'daily_at',
            'hourly',
            'every_2_hours',
            'every_4_hours',
            'every_6_hours',
            'every_12_hours',
            'every_5_minutes',
            'every_10_minutes',
            'every_15_minutes',
            'every_30_minutes',
            'weekly',
            'monthly',
            'custom_cron',
        ];
        if (is_string($frequency) && $frequency !== '') {
            if (! in_array($frequency, $allowedFrequencies, true)) {
                return back()->with('message', 'Invalid frequency value.');
            }
            IntegrationSetting::setValue('sync_frequency', $frequency);
        } else {
            IntegrationSetting::setValue('sync_frequency', null);
        }

        if ($frequency === 'custom_cron') {
            if (! is_string($cronExpr) || trim($cronExpr) === '') {
                return back()->with('message', 'Cron expression is required for custom frequency.');
            }
            $cronExpr = trim($cronExpr);
            if (! preg_match('/^[\d\*\-,\/]+\s+[\d\*\-,\/]+\s+[\d\*\-,\/]+\s+[\d\*\-,\/]+\s+[\d\*\-,\/]+$/', $cronExpr)) {
                return back()->with('message', 'Invalid cron format. Use 5-field cron, e.g. */8 * * * *');
            }
            IntegrationSetting::setValue('sync_cron', $cronExpr);
        } else {
            IntegrationSetting::setValue('sync_cron', null);
        }

        return back()->with('message', 'Sync schedule updated.');
    }

    public function clearSyncHistory()
    {
        SyncFailure::whereHas('syncLog', function ($q) {
            $q->where('type', SyncErpToOperaAccountsJob::LOG_TYPE);
        })->delete();

        SyncLog::where('type', SyncErpToOperaAccountsJob::LOG_TYPE)->delete();
        SyncState::where('sync_type', SyncErpToOperaAccountsJob::SYNC_TYPE)->delete();

        return back()->with('message', 'Sync history cleared.');
    }
    /**
     * Dashboard
     * @return View
     */
    public function index(): View
    {
        return $this->dashboard();
    }
    /**
     * Run sync
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function runSync(Request $request)
    {
        SyncErpToOperaAccountsJob::dispatch();
        $message = 'Sync started in background. You can continue working and refresh later to see results.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('message', $message);
    }

    /**
     * Run full sync (ignore last sync cursor)
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function runSyncFull(Request $request)
    {
        SyncState::where('sync_type', SyncErpToOperaAccountsJob::SYNC_TYPE)->delete();

        return $this->runSync($request);
    }
    /**
     * Retry failed records
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function retryFailed(Request $request)
    {
        RetryFailedSyncJob::dispatch($request->integer('sync_log_id'));

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Retry failed job dispatched.']);
        }

        return back()->with('message', 'Retry failed job dispatched. Only failed records will be reprocessed.');
    }
    /**
     * View application logs
     * @param Request $request
     * @return View|\Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     */
    public function logs(Request $request): View|\Illuminate\Http\JsonResponse|\Illuminate\Http\Response
    {
        $path = storage_path('logs/integration.log');
        $lines = (int) $request->get('lines', 100);
        $lines = min(max($lines, 10), 500);

        if (! File::exists($path)) {
            if ($request->wantsJson()) {
                return response()->json(['lines' => [], 'path' => $path]);
            }
            return view('admin.logs', ['logLines' => [], 'linesCount' => $lines]);
        }

        $content = File::get($path);
        $allLines = explode("\n", $content);
        $tail = array_slice($allLines, -$lines);
        $tail = array_values(array_filter($tail));

        if ($request->wantsJson()) {
            return response()->json(['lines' => $tail, 'path' => $path]);
        }

        if ($request->get('format') === 'raw') {
            return response('<pre>' . e(implode("\n", $tail)) . '</pre>')
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        return view('admin.logs', ['logLines' => $tail, 'linesCount' => $lines]);
    }
    /**
     * Calculate health status
     * @param SyncLog $lastRun
     * @param float $failureRate
     * @return string
     */
    protected function healthStatus(?SyncLog $lastRun, ?float $failureRate): string
    {
        if ($lastRun === null) {
            return 'unknown';
        }
        if ($failureRate === null || $failureRate <= 5) {
            return 'healthy';
        }
        if ($failureRate <= 20) {
            return 'warning';
        }
        return 'critical';
    }
}
