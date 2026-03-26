<?php

namespace App\DataSources;

use App\Contracts\DataSourceAdapterInterface;
use App\Services\Erp\BusinessCentralApiClient;
use App\Services\Mapping\ErpCustomerMapper;

class ErpRestApiAdapter implements DataSourceAdapterInterface
{
    public function __construct(
        protected BusinessCentralApiClient $client,
        protected ErpCustomerMapper $mapper
    ) {
    }

    public function name(): string
    {
        return 'ERP (Business Central REST API)';
    }

    public function fetchRecords(string $dataType, ?string $modifiedSince = null): array
    {
        if (! $this->supports($dataType)) {
            return [];
        }

        $raw = $this->client->getAllCustomers($modifiedSince);

        return $this->mapper->mapManyFromErp($raw);
    }

    public function supports(string $dataType): bool
    {
        return $dataType === 'ar_accounts';
    }
}
