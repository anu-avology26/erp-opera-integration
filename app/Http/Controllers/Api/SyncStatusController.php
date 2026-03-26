<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SyncErpToOperaAccountsJob;
use App\Models\SyncLog;
use App\Models\SyncState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SyncStatusController extends Controller
{
    /**
     * Get sync status
     * @return JsonResponse
     */
    public function status(): JsonResponse
    {
        $lastSyncAt = SyncState::where('sync_type', SyncErpToOperaAccountsJob::SYNC_TYPE)->first()?->last_sync_at;
        $lastRun = SyncLog::where('type', SyncErpToOperaAccountsJob::LOG_TYPE)->latest('id')->first();
        $recentLogs = SyncLog::where('type', SyncErpToOperaAccountsJob::LOG_TYPE)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($log) => [
                'created_at' => $log->created_at->toIso8601String(),
                'total' => $log->total,
                'success' => $log->success,
                'failed' => $log->failed,
                'errors' => $log->errors,
            ]);

        return response()->json([
            'last_sync_at' => $lastSyncAt?->toIso8601String(),
            'last_run' => $lastRun ? [
                'created_at' => $lastRun->created_at->toIso8601String(),
                'total' => $lastRun->total,
                'success' => $lastRun->success,
                'failed' => $lastRun->failed,
            ] : null,
            'recent_logs' => $recentLogs,
        ]);
    }

    /**
     * Run sync
     * @param Request $request
     * @return JsonResponse
     */
    public function run(Request $request): JsonResponse
    {
        SyncErpToOperaAccountsJob::dispatch();

        return response()->json(['message' => 'Sync job dispatched to integration queue.']);
    }
}
