<?php

namespace App\Jobs;

use App\DataSources\UploadedDataAdapter;
use App\Models\SyncFailure;
use App\Models\SyncLog;
use App\Models\SyncState;
use App\Services\Mapping\ErpCustomerMapper;
use App\Services\PayloadAuditService;
use App\Services\SyncFailureNotifier;
use App\Services\SyncOneCustomerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncUploadedArAccountsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const SYNC_TYPE = 'uploaded_ar_accounts';
    public const LOG_TYPE = 'UPLOAD_TO_OPERA';

    public int $tries = 3;
    public int $backoff = 120;

    public function __construct()
    {
        $this->onQueue(config('queue.integration_queue', 'integration'));
    }

    public function handle(
        UploadedDataAdapter $adapter,
        ErpCustomerMapper $mapper,
        PayloadAuditService $payloadAudit
    ): void {
        Log::channel('integration')->info('SyncUploadedArAccountsJob started');

        $chunkSize = (int) config('integration.upload_chunk_size', 200);
        $chunkSize = $chunkSize > 0 ? $chunkSize : 200;

        $total = 0;
        $success = 0;
        $failed = 0;
        $errors = [];
        $failureRecords = [];
        $errorLimit = (int) config('integration.sync_error_log_limit', 50);

        $syncOneService = app(SyncOneCustomerService::class);
        $hasRecords = false;
        foreach ($adapter->fetchRecordsInChunks('ar_accounts', $chunkSize) as $chunk) {
            $hasRecords = true;
            $normalized = $mapper->mapManyFromUploadedArAccounts($chunk);
            $total += count($normalized);

            foreach ($normalized as $item) {
                try {
                    $syncOneService->syncOne($item, false, self::LOG_TYPE, true);
                    $success++;
                } catch (\Throwable $e) {
                    $failed++;
                    $responseCode = $this->extractResponseCode($e);
                    $erpNumber = $item['erp_number'] ?? 'unknown';
                    $payloadForLog = $item['payload'] ?? $item;
                    if (count($errors) < $errorLimit) {
                        $errors[] = [
                            'erp_number' => $erpNumber,
                            'message' => $e->getMessage(),
                            'response_code' => $responseCode,
                        ];
                    }
                    $failureRecords[] = [
                        'erp_number' => $erpNumber,
                        'opera_account_number' => null,
                        'error_message' => $e->getMessage(),
                        'response_code' => $responseCode,
                    ];
                    $payloadAudit->logMetadata(self::LOG_TYPE, $erpNumber, 'failed', $responseCode, null);
                    $payloadAudit->storeEncrypted(self::LOG_TYPE, $erpNumber, 'failed', $responseCode, $item, true);
                    Log::channel('integration')->warning('SyncUploadedArAccountsJob: record failed', [
                        'erp_number' => $erpNumber,
                        'error' => $e->getMessage(),
                        'response_code' => $responseCode,
                        'exception_class' => $e::class,
                        'exception_file' => $e->getFile(),
                        'exception_line' => $e->getLine(),
                        'payload' => $payloadForLog,
                    ]);
                }
            }
        }

        if ($hasRecords && $failed === 0) {
            SyncState::setLastSyncAt(self::SYNC_TYPE);
        }
        $syncLog = SyncLog::create([
            'type' => self::LOG_TYPE,
            'total' => $total,
            'success' => $success,
            'failed' => $failed,
            'errors' => $errors,
        ]);

        foreach ($failureRecords as $f) {
            SyncFailure::create([
                'sync_log_id' => $syncLog->id,
                'erp_number' => $f['erp_number'],
                'opera_account_number' => $f['opera_account_number'],
                'error_message' => $f['error_message'],
                'response_code' => $f['response_code'],
            ]);
        }

        Log::channel('integration')->info('SyncUploadedArAccountsJob finished', [
            'total' => $total,
            'success' => $success,
            'failed' => $failed,
        ]);
    }

    protected function extractResponseCode(\Throwable $e): ?int
    {
        if ($e instanceof RequestException && $e->response) {
            return $e->response->status();
        }
        if (method_exists($e, 'getResponse') && $e->getResponse()) {
            return $e->getResponse()->getStatusCode();
        }
        return null;
    }

    public function failed(\Throwable $exception): void
    {
        app(SyncFailureNotifier::class)->notify('SyncUploadedArAccountsJob', $exception);
    }
}
