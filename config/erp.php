<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Business Central 365 Base URL
    |--------------------------------------------------------------------------
    |
    | Base URL for the Business Central OData v4 API, e.g. up to environment:
    | https://api.businesscentral.dynamics.com/v2.0/{tenant_id}/{environment}
    | For Bensaude pmscustomers: no /api/... in base_url; use customer_path.
    |
    */
    'base_url' => env('ERP_BASE_URL', ''),

    /*
    |--------------------------------------------------------------------------
    | OAuth2 / Azure AD
    |--------------------------------------------------------------------------
    */
    'tenant_id' => env('ERP_TENANT_ID', ''),
    'token_url' => env('ERP_TOKEN_URL', ''),
    'client_id' => env('ERP_CLIENT_ID', ''),
    'client_secret' => env('ERP_CLIENT_SECRET', ''),
    'scope' => env('ERP_SCOPE', 'https://api.businesscentral.dynamics.com/.default'),

    /*
    |--------------------------------------------------------------------------
    | API paths
    |--------------------------------------------------------------------------
    | customer_path: OData path to customers. For Bensaude pmscustomers use:
    |   api/bensaude/customer/v1.0/companies({company})/pmscustomers
    | The {company} placeholder is replaced with ERP_COMPANY_ID (company GUID).
    | company_id: Required when customer_path contains {company}.
    |
    | Incremental sync: $filter={incremental_filter_field} ge {timestamp}.
    | Client APIs may use lastDateModified or systemModifiedAt; set via ERP_INCREMENTAL_FILTER_FIELD.
    */
    'customer_path' => env('ERP_CUSTOMER_PATH', 'customers'),
    'company_id' => env('ERP_COMPANY_ID', ''),
    'incremental_filter_field' => env('ERP_INCREMENTAL_FILTER_FIELD', 'lastDateModified'),
    'catalog_code' => env('ERP_CATALOG_CODE', ''),

    /*
    |--------------------------------------------------------------------------
    | Token cache TTL (seconds)
    |--------------------------------------------------------------------------
    */
    'token_cache_ttl' => (int) env('ERP_TOKEN_CACHE_TTL', 3300),

    /*
    |--------------------------------------------------------------------------
    | PATCH field name for writing Opera account number back to BC
    |--------------------------------------------------------------------------
    | Update when final API from client confirms the property name (e.g. operaAccountNumber, arNumber).
    */
    'opera_account_number_field' => env('ERP_OPERA_ACCOUNT_NUMBER_FIELD', 'arNumber'),

    /*
    |--------------------------------------------------------------------------
    | Push Opera account back to ERP
    |--------------------------------------------------------------------------
    */
    'push_enabled' => (bool) env('ERP_PUSH_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Paging size for ERP fetch
    |--------------------------------------------------------------------------
    */
    'page_size' => (int) env('ERP_PAGE_SIZE', 200),


];
