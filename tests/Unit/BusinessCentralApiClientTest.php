<?php

namespace Tests\Unit;

use App\Services\Erp\BusinessCentralApiClient;
use App\Services\Erp\ErpAuthService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BusinessCentralApiClientTest extends TestCase
{
    public function test_get_customers_uses_filter_when_modified_since_provided(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            '*customers*' => Http::response(['value' => [
                ['number' => 'C001', 'name' => 'Acme', 'lastDateModified' => '2026-02-04T00:00:00Z'],
            ]]),
        ]);

        config([
            'erp.base_url' => 'https://api.example.com/companies(abc)',
            'erp.tenant_id' => 'tenant',
            'erp.client_id' => 'client',
            'erp.client_secret' => 'secret',
            'erp.scope' => 'https://api.businesscentral.dynamics.com/.default',
            'erp.customer_path' => 'customers',
            'erp.incremental_filter_field' => 'lastDateModified',
        ]);

        $client = BusinessCentralApiClient::fromConfig();
        $result = $client->getCustomers('2026-02-01T00:00:00Z', 10, 0);

        $this->assertArrayHasKey('value', $result);
        $this->assertCount(1, $result['value']);
        $this->assertSame('C001', $result['value'][0]['number']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'customers')
                && str_contains($request->url(), 'lastDateModified');
        });
    }

    public function test_get_customers_uses_configurable_incremental_filter_field(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            '*customers*' => Http::response(['value' => []]),
        ]);

        config([
            'erp.base_url' => 'https://api.example.com',
            'erp.tenant_id' => 'tenant',
            'erp.client_id' => 'client',
            'erp.client_secret' => 'secret',
            'erp.scope' => 'scope',
            'erp.customer_path' => 'customers',
            'erp.incremental_filter_field' => 'systemModifiedAt',
        ]);

        $client = BusinessCentralApiClient::fromConfig();
        $client->getCustomers('2026-01-28T00:00:00Z', 10, 0);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'systemModifiedAt')
                && str_contains($request->url(), '2026-01-28');
        });
    }

    public function test_get_customers_returns_empty_value_on_empty_response(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            '*customers*' => Http::response(['value' => []]),
        ]);

        config([
            'erp.base_url' => 'https://api.example.com/companies(abc)',
            'erp.tenant_id' => 'tenant',
            'erp.client_id' => 'client',
            'erp.client_secret' => 'secret',
            'erp.scope' => 'scope',
            'erp.customer_path' => 'customers',
        ]);

        $client = BusinessCentralApiClient::fromConfig();
        $result = $client->getCustomers(null, 10, 0);

        $this->assertSame(['value' => []], $result);
    }
}
