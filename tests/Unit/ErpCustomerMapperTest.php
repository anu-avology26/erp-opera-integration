<?php

namespace Tests\Unit;

use App\Services\Mapping\ErpCustomerMapper;
use Tests\TestCase;

class ErpCustomerMapperTest extends TestCase
{
    protected ErpCustomerMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new ErpCustomerMapper;
    }

    public function test_maps_valid_company_customer(): void
    {
        $raw = [
            'number' => 'C001',
            'name' => 'Acme Corp',
            'blocked' => false,
            'accountType' => 'Company',
            'creditApproved' => true,
            'creditLimit' => 10000.50,
            'systemModifiedAt' => '2026-02-04T12:00:00Z',
        ];

        $result = $this->mapper->mapFromErp($raw);

        $this->assertNotNull($result);
        $this->assertSame('C001', $result['erp_number']);
        $this->assertSame('Acme Corp', $result['name']);
        $this->assertTrue($result['has_credit']);
        $this->assertSame(10000.50, $result['credit_limit']);
        $this->assertSame('2026-02-04T12:00:00Z', $result['last_modified_at']);
        $this->assertSame($raw, $result['payload']);
    }

    public function test_returns_null_when_missing_number(): void
    {
        $raw = [
            'name' => 'Acme',
            'accountType' => 'Company',
        ];

        $this->assertNull($this->mapper->mapFromErp($raw));
    }

    public function test_returns_null_when_missing_name(): void
    {
        $raw = [
            'number' => 'C001',
            'accountType' => 'Company',
        ];

        $this->assertNull($this->mapper->mapFromErp($raw));
    }

    public function test_returns_null_when_blocked(): void
    {
        $raw = [
            'number' => 'C001',
            'name' => 'Acme',
            'blocked' => true,
            'accountType' => 'Company',
        ];

        $this->assertNull($this->mapper->mapFromErp($raw));
    }

    public function test_returns_null_when_account_type_not_company(): void
    {
        $raw = [
            'number' => 'C001',
            'name' => 'Acme',
            'blocked' => false,
            'accountType' => 'Person',
        ];

        $this->assertNull($this->mapper->mapFromErp($raw));
    }

    public function test_maps_with_alternative_field_names(): void
    {
        $raw = [
            'no' => 'C002',
            'displayName' => 'Beta Inc',
            'accountType' => 'Company',
            'hasCredit' => true,
            'creditLimitLCY' => 5000,
        ];

        $result = $this->mapper->mapFromErp($raw);

        $this->assertNotNull($result);
        $this->assertSame('C002', $result['erp_number']);
        $this->assertSame('Beta Inc', $result['name']);
        $this->assertTrue($result['has_credit']);
        $this->assertSame(5000.0, $result['credit_limit']);
    }

    public function test_map_many_filters_invalid_and_returns_only_valid(): void
    {
        $rawItems = [
            ['number' => 'A', 'name' => 'Valid A', 'accountType' => 'Company'],
            ['name' => 'Missing number', 'accountType' => 'Company'],
            ['number' => 'C', 'name' => 'Valid C', 'accountType' => 'Company'],
        ];

        $mapped = $this->mapper->mapManyFromErp($rawItems);

        $this->assertCount(2, $mapped);
        $this->assertSame('A', $mapped[0]['erp_number']);
        $this->assertSame('C', $mapped[1]['erp_number']);
    }
}
