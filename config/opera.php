<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OHIP Gateway URL
    |--------------------------------------------------------------------------
    */
    'gateway_url'  => env('OPERA_GATEWAY_URL'),

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    'client_id'    => env('OPERA_CLIENT_ID'),
    'client_secret' => env('OPERA_CLIENT_SECRET'),
    'username'     => env('OPERA_USERNAME'),
    'password'     => env('OPERA_PASSWORD'),
    'scope'        => env('OPERA_SCOPE'),
    'app_key'      => env('OPERA_APP_KEY'),
    'token_cache_ttl' => env('OPERA_TOKEN_CACHE_TTL', 3300),


    /*
    |--------------------------------------------------------------------------
    | Enterprise & Properties
    |--------------------------------------------------------------------------
    */
    'enterprise_id' => env('OPERA_ENTERPRISE_ID'),
    'property_ids' => array_filter(array_map('trim', explode(',', env('OPERA_PROPERTY_IDS', '')))),

    /*
    |--------------------------------------------------------------------------
    | API paths (relative to gateway_url)
    |--------------------------------------------------------------------------
    | Reference: rest-api-specs/property/ars.json. Update when final API from client.
    */
    'ar_account_path' => env('OPERA_AR_ACCOUNT_PATH', 'ars/v1/hotels/{hotelId}/accounts'),
    'ar_account_update_method' => strtoupper(env('OPERA_AR_ACCOUNT_UPDATE_METHOD', 'PUT')),
    'company_profile_path' => env('OPERA_COMPANY_PROFILE_PATH', 'crm/v1/profiles'),
    'company_profile_sync_enabled' => (bool) env('OPERA_COMPANY_PROFILE_SYNC_ENABLED', true),
    'company_profile_validate_existing' => (bool) env('OPERA_COMPANY_PROFILE_VALIDATE_EXISTING', true),
    'company_external_lookup_path' => env('OPERA_COMPANY_EXTERNAL_LOOKUP_PATH', 'crm/v1/externalSystems/{extSystemCode}/profiles/{profileExternalId}'),
    'company_external_system_code' => env('OPERA_COMPANY_EXTERNAL_SYSTEM_CODE', ''),
    'company_external_id_context' => env('OPERA_COMPANY_EXTERNAL_ID_CONTEXT', 'ERPID'),
    'company_keyword_search_path' => env('OPERA_COMPANY_KEYWORD_SEARCH_PATH', 'crm/v1/profiles?keywordType={keywordType}&keyword={keyword}'),
    'company_keyword_type' => env('OPERA_COMPANY_KEYWORD_TYPE', 'ERPID'),
    'profile_map_path' => env('OPERA_PROFILE_MAP_PATH', ''),
    'reservation_bulk_update_path' => env('OPERA_RESERVATION_BULK_UPDATE_PATH', 'rsv/v1/hotels/{hotelId}/reservations'),
    'reservation_bulk_update_method' => strtoupper(env('OPERA_RESERVATION_BULK_UPDATE_METHOD', 'PUT')),

    /*
    |--------------------------------------------------------------------------
    | AR response: field(s) used to read Opera account number from create/update
    |--------------------------------------------------------------------------
    | Comma-separated; first found in response is used. Update when final response from client.
    */
    'ar_account_response_fields' => array_filter(array_map('trim', explode(',', env('OPERA_AR_ACCOUNT_RESPONSE_FIELDS', 'accountNumber,account_number,id')))),

    /*
    |--------------------------------------------------------------------------
    | AR external reference prefix (coexist use case)
    |--------------------------------------------------------------------------
    | When set, ERP-originated AR accounts use (prefix + erp_number) as externalReference
    | in Opera, so they coexist as separate entities from any Opera-native entity with the
    | same customer number. E.g. prefix "ERP-" => externalReference "ERP-C001".
    | Leave empty for backward compatibility (externalReference = erp_number only).
    */
    'ar_external_ref_prefix' => env('OPERA_AR_EXTERNAL_REF_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Credit management (OHIP AR)
    |--------------------------------------------------------------------------
    | restricted_reason: code/label recorded in Opera when credit is not approved (AR Restricted Reason).
    | credit_limit_behaviour: block | alert – how Opera treats credit limit exceeded (to be validated with OHIP).
    */
    'ar_restricted_reason' => env('OPERA_AR_RESTRICTED_REASON', 'NOT_APPROVED'),
    'credit_limit_behaviour' => env('OPERA_CREDIT_LIMIT_BEHAVIOUR', 'block'),
    /*
    |----------------------------------------------------------------------
    | Profile restriction default reason
    |----------------------------------------------------------------------
    | If profileRestrictions.restricted=true, Opera may require a reason.
    | profile_restricted_reason_field lets you control the field name sent.
    */
    'profile_restricted_reason' => env('OPERA_PROFILE_RESTRICTED_REASON', 'NC'),
    'profile_restricted_reason_field' => env('OPERA_PROFILE_RESTRICTED_REASON_FIELD', 'restrictionReason'),

    /*
    |--------------------------------------------------------------------------
    | Rate limiting (requests per minute; 0 = disabled)
    |--------------------------------------------------------------------------
    */
    'rate_limit_per_minute' => (int) env('OPERA_RATE_LIMIT_PER_MINUTE', 60),

    /*
    |--------------------------------------------------------------------------
    | Minimum delay between requests in milliseconds (0 = no delay)
    |--------------------------------------------------------------------------
    */
    'request_delay_ms' => (int) env('OPERA_REQUEST_DELAY_MS', 0),

    /*
    |--------------------------------------------------------------------------
    | Token cache TTL (seconds)
    |--------------------------------------------------------------------------
    */
    'token_cache_ttl' => (int) env('OPERA_TOKEN_CACHE_TTL', 3300),

];
