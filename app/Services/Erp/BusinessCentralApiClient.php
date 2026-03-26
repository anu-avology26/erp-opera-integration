<?php

namespace App\Services\Erp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BusinessCentralApiClient
{
    public function __construct(
        protected string $baseUrl,
        protected ErpAuthService $authService,
        protected string $customerPath = 'customers',
        protected ?string $companyId = null
    ) {
    }

    public static function fromConfig(): self
    {
        $baseUrl = rtrim(config('erp.base_url', ''), '/');
        $customerPath = config('erp.customer_path', 'customers');
        $companyId = config('erp.company_id') ?: null;

        return new self(
            baseUrl: $baseUrl,
            authService: ErpAuthService::fromConfig(),
            customerPath: $customerPath,
            companyId: $companyId
        );
    }

    /**
     * Resolve customer path, replacing {company} placeholder with company ID when set.
     * E.g. api/bensaude/customer/v1.0/companies({company})/pmscustomers -> .../companies(guid)/pmscustomers
     */
    protected function resolveCustomerPath(): string
    {
        $path = ltrim($this->customerPath, '/');
        if ($this->companyId !== null && $this->companyId !== '' && str_contains($path, '{company}')) {
            $path = str_replace('{company}', $this->companyId, $path);
        }
        return $path;
    }

    /**
     * Fetch customers from BC OData v4 API with optional incremental filter.
     * Filter field is configurable via erp.incremental_filter_field (lastDateModified or systemModifiedAt).
     *
     * @param  string|null  $modifiedSince  ISO 8601 timestamp for $filter={field} ge {value}
     * @param  int  $top  Page size
     * @param  int  $skip  Skip count for paging
     * @return array{ value: array<int, array> }
     */
    public function getCustomers(?string $modifiedSince = null, int $top = 100, int $skip = 0): array
    {
        $path = $this->resolveCustomerPath();
        $url = $this->baseUrl . '/' . $path;

        $query = [
            '$top' => $top,
            '$skip' => $skip,
            '$format' => 'application/json',
        ];
        $filters = [];
        $catalogCode = trim((string) config('erp.catalog_code', ''));
        if ($catalogCode !== '') {
            $filters[] = "catalogCode eq '" . addslashes($catalogCode) . "'";
        }

        if ($modifiedSince !== null && $modifiedSince !== '') {
            $quoted = $this->formatODataDateTime($modifiedSince);
            $filterField = config('erp.incremental_filter_field', 'lastDateModified');
            $filters[] = $filterField . ' ge ' . $quoted;
        }
        if ($filters !== []) {
            $query['$filter'] = implode(' and ', $filters);
        }

        $response = $this->request('GET', $url, ['query' => $query]);

        if (! $response->successful()) {
            Log::channel('integration')->error('BC getCustomers failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('BC getCustomers failed: ' . $response->body());
        }

        $data = $response->json();
        return is_array($data) ? $data : ['value' => []];
    }

    /**
     * Fetch all customers using paging (incremental filter optional).
     *
     * @return array<int, array>
     */
    public function getAllCustomers(?string $modifiedSince = null, int $pageSize = 100): array
    {
        $all = [];
        $skip = 0;

        do {
            $result = $this->getCustomers($modifiedSince, $pageSize, $skip);
            $value = $result['value'] ?? [];
            $all = array_merge($all, $value);
            $skip += $pageSize;
        } while (count($value) === $pageSize);

        return $all;
    }

    /**
     * Update customer in BC with Opera Account Number.
     * PATCH to customer entity; field name may be custom (e.g. operaAccountNumber).
     */
    public function updateCustomerOperaAccountNumber(string $erpNumber, string $operaAccountNumber): bool
    {
        $baseUrl = rtrim($this->baseUrl, '/');
        $path = $this->resolveCustomerPath();
        $filters = [];
        $filters[] = "number eq '" . addslashes($erpNumber) . "'";
        $catalogCode = trim((string) config('erp.catalog_code', ''));
        if ($catalogCode !== '') {
            $filters[] = "catalogCode eq '" . addslashes($catalogCode) . "'";
        } else {
            Log::channel('integration')->warning('BC update: catalogCode is required but ERP_CATALOG_CODE is empty', [
                'erp_number' => $erpNumber,
            ]);
        }
        $filter = implode(' and ', $filters);
        $url = $baseUrl . '/' . $path;
        $query = [
            '$top' => 1,
            '$filter' => $filter,
            '$format' => 'application/json',
            '$select' => 'number,catalogCode',
        ];

        $getResponse = $this->request('GET', $url, ['query' => $query]);
        if (! $getResponse->successful()) {
            Log::channel('integration')->warning('BC get customer for update failed', [
                'erp_number' => $erpNumber,
                'status' => $getResponse->status(),
                'body' => $getResponse->body(),
                'url' => $url,
                'filter' => $filter,
            ]);
            return false;
        }

        $data = $getResponse->json();
        $value = $data['value'] ?? [];
        if (empty($value)) {
            Log::channel('integration')->warning('BC customer not found for update', ['erp_number' => $erpNumber]);
            return false;
        }

        $customer = $value[0];
        $entityId = $customer['@odata.etag'] ?? null;
        $editUrl = $customer['@odata.editLink'] ?? null;
        if ($editUrl && str_starts_with($editUrl, 'http')) {
            $patchUrl = $editUrl;
        } else {
            $catalogCode = trim((string) config('erp.catalog_code', ''));
            if ($catalogCode !== '') {
                $escapedNumber = rawurlencode((string) $erpNumber);
                $escapedCatalog = rawurlencode($catalogCode);
                $patchUrl = $baseUrl . '/' . $path . "(number='" . $escapedNumber . "',catalogCode='" . $escapedCatalog . "')";
            } else {
                Log::channel('integration')->warning('BC update: missing catalogCode; cannot build PATCH URL', [
                    'erp_number' => $erpNumber,
                ]);
                return false;
            }
        }

        $fieldName = config('erp.opera_account_number_field', 'operaAccountNumber');
        $payload = [
            $fieldName => $operaAccountNumber,
        ];

        $patchResponse = $this->request('PATCH', $patchUrl, [
            'json' => $payload,
            'headers' => ['If-Match' => $entityId ?? '*'],
        ]);

        if (! $patchResponse->successful()) {
            Log::channel('integration')->warning('BC PATCH customer failed', [
                'erp_number' => $erpNumber,
                'status' => $patchResponse->status(),
                'body' => $patchResponse->body(),
            ]);
            return false;
        }

        return true;
    }

    protected function request(string $method, string $url, array $options = []): \Illuminate\Http\Client\Response
    {
        $token = $this->authService->getAccessToken();

        $defaultHeaders = [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        $headers = array_merge($defaultHeaders, $options['headers'] ?? []);
        $http = Http::withHeaders($headers);

        $verb = strtoupper($method);
        if ($verb === 'GET') {
            return $http->get($url, $options['query'] ?? []);
        }
        if (in_array($verb, ['PATCH', 'POST', 'PUT']) && array_key_exists('json', $options)) {
            return $http->{strtolower($verb)}($url, $options['json']);
        }

        return $http->{strtolower($verb)}($url);
    }

    /**
     * Format ISO 8601 datetime for OData v4 $filter (quoted).
     */
    protected function formatODataDateTime(string $iso8601): string
    {
        $iso8601 = trim($iso8601);
        if ($iso8601 === '') {
            return "''";
        }
        try {
            $dt = new \DateTimeImmutable($iso8601);
            $normalized = $dt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\\TH:i:s\\Z');
            return $normalized;
        } catch (\Throwable) {
            return str_replace("'", '', $iso8601);
        }
    }
}
