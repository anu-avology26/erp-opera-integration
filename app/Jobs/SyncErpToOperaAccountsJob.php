<?php

namespace App\Jobs;

use App\Models\ErpCustomer;
use App\Models\SyncFailure;
use App\Models\SyncLog;
use App\Models\SyncState;
use App\Services\Erp\BusinessCentralApiClient;
use App\Services\Mapping\ErpCustomerMapper;
use App\Services\Opera\OperaOhipClient;
use App\Services\PayloadAuditService;
use App\Services\SyncFailureNotifier;
use App\Services\SyncOneCustomerService;
use Illuminate\Bus\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncErpToOperaAccountsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const SYNC_TYPE = 'erp_customers';

    public const LOG_TYPE = 'ERP_TO_OPERA';

    public int $tries = 3;

    public int $backoff = 120;

    public function __construct()
    {
        $this->onQueue(config('queue.integration_queue', 'integration'));
    }

    public function handle(
        BusinessCentralApiClient $erpClient,
        OperaOhipClient $operaClient,
        ErpCustomerMapper $mapper,
        PayloadAuditService $payloadAudit
    ): void {
        Log::channel('integration')->info('SyncErpToOperaAccountsJob started');

        $lastSyncAt = SyncState::getLastSyncAt(self::SYNC_TYPE);
        $pageSize = (int) config('erp.page_size', 200);
        $pageSize = $pageSize > 0 ? $pageSize : 200;

        $lastSuccessfulAt = null;

        $total = 0;
        $success = 0;
        $failed = 0;
        $errors = [];
        $failureRecords = [];
        $errorLimit = (int) config('integration.sync_error_log_limit', 50);

        $syncOneService = app(SyncOneCustomerService::class);
        $skip = 0;
        do {
            try {
                $result = $erpClient->getCustomers($lastSyncAt, $pageSize, $skip);
            } catch (\Throwable $e) {
                $payloadAudit->logMetadata('ERP_FETCH', null, 'failed', $this->extractResponseCode($e), null);
                Log::channel('integration')->error('SyncErpToOperaAccountsJob: ERP fetch failed', [
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
            $rawCustomers = $result['value'] ?? [];
            if (! is_array($rawCustomers) || $rawCustomers === []) {
                break;
            }

            $normalized = $mapper->mapManyFromErp($rawCustomers);
            $total += count($normalized);

            foreach ($normalized as $item) {
                try {
                    $erpNumber = $item['erp_number'] ?? 'unknown';
                    $payload = $item['payload'] ?? $item;
                    if (is_array($payload)) {
                        $payloadAudit->storeEncrypted('ERP_FETCH', $erpNumber, 'success', 200, $payload);
                    }
                    $syncOneService->syncOne($item);
                    $success++;
                    $candidate = $this->extractModifiedAt($item);
                    if ($candidate && ($lastSuccessfulAt === null || $candidate > $lastSuccessfulAt)) {
                        $lastSuccessfulAt = $candidate;
                    }
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
                    Log::channel('integration')->warning('SyncErpToOperaAccountsJob: record failed', [
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

            $skip += $pageSize;
        } while (count($rawCustomers) === $pageSize);

        if ($success > 0) {
            $cursor = $lastSuccessfulAt ?? now();
            // Move cursor slightly forward to avoid inclusive ERP filters reprocessing the same record.
            if ($cursor instanceof \DateTimeInterface) {
                $cursor = \Illuminate\Support\Carbon::instance($cursor)->addSecond();
            } else {
                $cursor = now()->addSecond();
            }
            SyncState::setLastSyncAt(self::SYNC_TYPE, $cursor);
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

        Log::channel('integration')->info('SyncErpToOperaAccountsJob finished', [
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

    protected function extractModifiedAt(array $item): ?\DateTimeImmutable
    {
        $candidates = [
            $item['last_modified_at'] ?? null,
            $item['system_modified_at'] ?? null,
        ];

        foreach ($candidates as $value) {
            if ($value instanceof \DateTimeInterface) {
                return new \DateTimeImmutable($value->format(\DateTimeInterface::ATOM));
            }
            if (is_string($value) && $value !== '') {
                try {
                    return new \DateTimeImmutable($value);
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        return null;
    }

    public function failed(\Throwable $exception): void
    {
        app(SyncFailureNotifier::class)->notify('SyncErpToOperaAccountsJob', $exception);
    }
}
