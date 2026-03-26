<?php

namespace App\Console\Commands;

use App\Services\Erp\BusinessCentralApiClient;
use App\Services\Erp\ErpAuthService;
use Illuminate\Console\Command;

class TestErpConnectionCommand extends Command
{
    protected $signature = 'erp:test-connection {--fetch : Also fetch one page of ERP customers}';

    protected $description = 'Test ERP OAuth token generation and optional customer fetch';

    public function handle(): int
    {
        $authService = ErpAuthService::fromConfig();
        $erpClient = BusinessCentralApiClient::fromConfig();

        try {
            $token = $authService->getAccessToken();
            $this->info('ERP token generated successfully.');
            $this->line('Token preview: ' . substr($token, 0, 20) . '...');
        } catch (\Throwable $e) {
            $this->error('ERP token generation failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        if (! $this->option('fetch')) {
            return self::SUCCESS;
        }

        $baseUrl = (string) config('erp.base_url', '');
        if (trim($baseUrl) === '') {
            $this->warn('ERP_BASE_URL is empty. Set ERP_BASE_URL and ERP_CUSTOMER_PATH before running --fetch.');
            return self::FAILURE;
        }

        try {
            $result = $erpClient->getCustomers(null, 5, 0);
            $count = is_array($result['value'] ?? null) ? count($result['value']) : 0;
            $this->info('ERP fetch successful.');
            $this->line('Records fetched: ' . $count);
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('ERP fetch failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
