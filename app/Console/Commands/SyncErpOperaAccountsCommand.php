<?php

namespace App\Console\Commands;

use App\Jobs\SyncErpToOperaAccountsJob;
use Illuminate\Console\Command;

class SyncErpOperaAccountsCommand extends Command
{
    protected $signature = 'sync:erp-opera-accounts {--sync : Run synchronously instead of dispatching job}';

    protected $description = 'Sync ERP (Business Central) customers to Opera Cloud AR accounts';

    public function handle(): int
    {
        if ($this->option('sync')) {
            $job = new SyncErpToOperaAccountsJob;
            $job->handle(
                app(\App\Services\Erp\BusinessCentralApiClient::class),
                app(\App\Services\Opera\OperaOhipClient::class),
                app(\App\Services\Mapping\ErpCustomerMapper::class),
                app(\App\Services\PayloadAuditService::class)
            );
            $this->info('Sync completed (synchronous).');
            return self::SUCCESS;
        }

        SyncErpToOperaAccountsJob::dispatch();
        $this->info('Sync job dispatched to integration queue.');
        return self::SUCCESS;
    }
}
