<?php

namespace App\Jobs;

use App\Services\Erp\BusinessCentralApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PushOperaAccountToErpJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public string $erpNumber,
        public string $operaAccountNumber
    ) {
        $this->onQueue(config('queue.integration_queue', 'integration'));
    }

    public function handle(BusinessCentralApiClient $erpClient): void
    {
        $ok = $erpClient->updateCustomerOperaAccountNumber($this->erpNumber, $this->operaAccountNumber);

        if (! $ok) {
            Log::channel('integration')->warning('PushOperaAccountToErpJob: ERP update failed', [
                'erp_number' => $this->erpNumber,
            ]);
            throw new \RuntimeException('ERP update failed for ' . $this->erpNumber);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::channel('integration')->error('PushOperaAccountToErpJob failed', [
            'erp_number' => $this->erpNumber,
            'opera_account_number' => $this->operaAccountNumber,
            'error' => $exception->getMessage(),
        ]);
    }
}
