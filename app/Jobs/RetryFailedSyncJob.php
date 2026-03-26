<?php

namespace App\Jobs;

use App\Models\ErpCustomer;
use App\Models\SyncFailure;
use App\Services\SyncOneCustomerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RetryFailedSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        public ?int $syncLogId = null
    ) {
        $this->onQueue(config('queue.integration_queue', 'integration'));
    }

    public function handle(SyncOneCustomerService $syncOneService): void
    {
        $failures = SyncFailure::whereNull('retried_at')
            ->when($this->syncLogId, fn ($q) => $q->where('sync_log_id', $this->syncLogId))
            ->orderBy('id')
            ->get();

        if ($failures->isEmpty()) {
            Log::channel('integration')->info('RetryFailedSyncJob: no unretried failures');
            return;
        }

        foreach ($failures as $failure) {
            $customer = ErpCustomer::where('erp_number', $failure->erp_number)->first();
            if (! $customer) {
                Log::channel('integration')->warning('RetryFailedSyncJob: customer not found', ['erp_number' => $failure->erp_number]);
                continue;
            }

            $item = [
                'erp_number' => $customer->erp_number,
                'name' => $customer->name,
                'status' => $customer->status,
                'account_type' => $customer->account_type,
                'active' => $customer->active,
                'blocked' => $customer->blocked,
                'has_credit' => $customer->has_credit,
                'credit_limit' => $customer->credit_limit,
                'restricted_reason' => $customer->restricted_reason ?? null,
                'address_1' => $customer->address_1,
                'address_2' => $customer->address_2,
                'country' => $customer->country,
                'post_code' => $customer->post_code,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'ar_number' => $customer->ar_number,
                'payment_terms_code' => $customer->payment_terms_code,
                'property' => $customer->property,
                'catalog_code' => $customer->catalog_code,
                'system_modified_at' => $customer->system_modified_at?->toIso8601String(),
                'payload' => $customer->payload,
                'last_modified_at' => $customer->last_modified_at?->toIso8601String(),
            ];

            try {
                $syncOneService->syncOne($item);
                $failure->update(['retried_at' => now()]);
            } catch (\Throwable $e) {
                Log::channel('integration')->warning('RetryFailedSyncJob: retry failed', [
                    'erp_number' => $failure->erp_number,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
