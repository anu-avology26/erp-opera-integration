<?php

namespace Tests\Unit;

use App\Services\Opera\OperaOhipClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OperaOhipClientTest extends TestCase
{
    public function test_build_ar_payload_from_normalized(): void
    {
        config([
            'opera.enterprise_id' => 'ENT1',
            'opera.property_ids' => ['PROP1', 'PROP2'],
        ]);

        $client = OperaOhipClient::fromConfig();
        $normalized = [
            'erp_number' => 'C001',
            'name' => 'Acme Corp',
            'has_credit' => true,
            'credit_limit' => 5000.0,
        ];

        $payload = $client->buildArPayload($normalized);

        $this->assertSame('Acme Corp', $payload['name']);
        $this->assertSame('C001', $payload['externalReference']);
        $this->assertTrue($payload['creditApproved']);
        $this->assertSame(5000.0, $payload['creditLimit']);
        $this->assertSame('PROP1', $payload['propertyId']);
        $this->assertSame('ENT1', $payload['enterpriseId']);
    }

    public function test_build_ar_payload_includes_restricted_reason_and_credit_behaviour_when_no_credit(): void
    {
        config([
            'opera.enterprise_id' => 'ENT1',
            'opera.property_ids' => ['PROP1'],
            'opera.ar_restricted_reason' => 'NOT_APPROVED',
            'opera.credit_limit_behaviour' => 'block',
        ]);

        $client = OperaOhipClient::fromConfig();
        $payload = $client->buildArPayload([
            'erp_number' => 'C002',
            'name' => 'No Credit Corp',
            'has_credit' => false,
            'credit_limit' => 0,
        ]);

        $this->assertFalse($payload['creditApproved']);
        $this->assertArrayHasKey('restrictedReason', $payload);
        $this->assertSame('NOT_APPROVED', $payload['restrictedReason']);
        $this->assertSame('block', $payload['creditLimitBehaviour']);
    }

    public function test_build_ar_payload_uses_external_ref_prefix_when_configured(): void
    {
        config([
            'opera.enterprise_id' => 'ENT1',
            'opera.property_ids' => ['PROP1'],
            'opera.ar_external_ref_prefix' => 'ERP-',
        ]);

        $client = OperaOhipClient::fromConfig();
        $payload = $client->buildArPayload([
            'erp_number' => 'C001',
            'name' => 'Acme Corp',
            'has_credit' => true,
            'credit_limit' => 5000.0,
        ]);

        $this->assertSame('ERP-C001', $payload['externalReference']);
    }

    public function test_create_ar_account_returns_account_number_from_response(): void
    {
        Http::fake([
            'https://opera.example.com/oauth/v1/tokens' => Http::response(['access_token' => 'fake_token'], 200),
            'https://opera.example.com/ar/accounts' => Http::response(['accountNumber' => 'OPERA-123'], 201),
        ]);

        config([
            'opera.gateway_url' => 'https://opera.example.com',
            'opera.client_id' => 'cid',
            'opera.client_secret' => 'secret',
            'opera.username' => 'user',
            'opera.password' => 'pass',
            'opera.scope' => 'scope',
            'opera.app_key' => 'key',
            'opera.enterprise_id' => 'ENT1',
            'opera.property_ids' => ['PROP1'],
            'opera.ar_account_path' => 'ar/accounts',
        ]);

        $client = OperaOhipClient::fromConfig();
        $number = $client->createArAccount([
            'name' => 'Test',
            'externalReference' => 'C001',
            'creditApproved' => false,
            'creditLimit' => 0,
            'propertyId' => 'PROP1',
            'enterpriseId' => 'ENT1',
        ]);

        $this->assertSame('OPERA-123', $number);
    }
}
