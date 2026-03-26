<?php

namespace Tests\Feature;

use App\Jobs\SyncErpToOperaAccountsJob;
use App\Models\SyncLog;
use App\Models\SyncState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SyncErpOperaAccountsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'login.microsoftonline.com/*' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ]),
            '*customers*' => Http::response([
                'value' => [
                    [
                        'number' => 'C001',
                        'name' => 'Acme Corp',
                        'blocked' => false,
                        'accountType' => 'Company',
                        'creditApproved' => true,
                        'creditLimit' => 10000,
                        'systemModifiedAt' => '2026-02-04T12:00:00Z',
                    ],
                ],
            ]),
            '*ar/accounts*' => Http::response([
                'accountNumber' => 'OPERA-001',
            ], 201),
        ]);

        config([
            'erp.base_url' => 'https://api.example.com/companies(abc)',
            'erp.tenant_id' => 't',
            'erp.client_id' => 'c',
            'erp.client_secret' => 's',
            'erp.scope' => 'scope',
            'erp.customer_path' => 'customers',
            'opera.gateway_url' => 'https://opera.example.com',
            'opera.client_id' => 'oc',
            'opera.client_secret' => 'os',
            'opera.app_key' => 'ok',
            'opera.enterprise_id' => 'ENT1',
            'opera.property_ids' => ['PROP1'],
            'opera.ar_account_path' => 'ar/accounts',
        ]);
    }

    public function test_sync_command_dispatches_job(): void
    {
        Queue::fake();

        $this->artisan('sync:erp-opera-accounts')
            ->assertSuccessful();

        Queue::assertPushed(SyncErpToOperaAccountsJob::class);
    }

    public function test_sync_command_sync_option_runs_synchronously(): void
    {
        $this->artisan('sync:erp-opera-accounts', ['--sync' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('sync_logs', [
            'type' => SyncErpToOperaAccountsJob::LOG_TYPE,
        ]);
        $log = SyncLog::where('type', SyncErpToOperaAccountsJob::LOG_TYPE)->first();
        $this->assertGreaterThanOrEqual(0, $log->total);
        $this->assertNotNull(SyncState::where('sync_type', SyncErpToOperaAccountsJob::SYNC_TYPE)->first());
    }
}
