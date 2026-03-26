<?php

namespace App\Services\Mapping;

use Illuminate\Support\Facades\Log;
use App\Services\Mapping\ErpMappingConfig;

class ErpCustomerMapper
{
    /**
     * Map raw ERP (BC OData) customer record to normalized internal array.
     * Applies: active only, account type = Company if applicable, credit flag/limit.
     *
     * @param  array<string, mixed>  $raw
     * @return array{erp_number: string, ar_number: ?string, name: string, status: ?string, account_type: ?string, active: bool, blocked: bool, has_credit: bool, credit_limit: ?float, payment_terms_code: ?string, catalog_code: ?string, system_modified_at: ?string, last_modified_at: ?string, payload: array}|null  Null if record should be skipped
     */
    public function mapFromErp(array $raw): ?array
    {
        $fieldKeys = app(ErpMappingConfig::class)->customerFieldKeys();
        $knownGroups = [
            'erp_number',
            'ar_number',
            'name',
            'status',
            'active',
            'blocked',
            'account_type',
            'address_1',
            'address_2',
            'city',
            'state',
            'country',
            'post_code',
            'phone',
            'email',
            'vat_registration_no',
            'has_credit',
            'credit_limit',
            'payment_terms_code',
            'property',
            'last_modified_at',
            'system_modified_at',
            'catalog_code',
            'restricted_reason',
        ];

        $erpNumber = $this->extractFirstString($raw, $this->resolveKeys($fieldKeys, 'erp_number', ['number', 'no', 'id']));
        $arNumber = $this->extractFirstString($raw, $this->resolveKeys($fieldKeys, 'ar_number', ['arNumber', 'accountNo', 'account_no']));
        $name = $this->extractFirstString($raw, $this->resolveKeys($fieldKeys, 'name', ['name', 'displayName']));

        if ($erpNumber === null || $erpNumber === '' || $name === null || $name === '') {
            Log::channel('integration')->debug('ErpCustomerMapper: skipping record, missing number or name', [
                'keys' => array_keys($raw),
            ]);
            return null;
        }

        $status = $this->extractFirstString($raw, $this->resolveKeys($fieldKeys, 'status', ['status']));
        $active = $this->extractFirstBool($raw, $this->resolveKeys($fieldKeys, 'active', ['active']));
        if ($active === null && $status !== null) {
            $active = strtolower(trim($status)) === 'active';
        }
        if ($active === false) {
            Log::channel('integration')->debug('ErpCustomerMapper: skipping inactive customer', ['erp_number' => $erpNumber]);
            return null;
        }
        $blocked = $this->extractFirstBool($raw, $this->resolveKeys($fieldKeys, 'blocked', ['blocked'])) ?? false;

        $accountType = $this->extractFirstString($raw, $this->resolveKeys($fieldKeys, 'account_type', ['accountType', 'type']));
        if ($accountType !== null && strtolower((string) $accountType) !== 'company') {
            Log::channel('integration')->debug('ErpCustomerMapper: skipping non-company account type', [
                'erp_number' => $erpNumber,
                'account_type' => $accountType,
            ]);
            return null;
        }

        $hasCredit = $this->extractFirstBool($raw, $this->resolveKeys($fieldKeys, 'has_credit', ['creditApproved', 'hasCredit'])) ?? false;
        $creditLimit = $this->extractFirstDecimal($raw, $this->resolveKeys($fieldKeys, 'credit_limit', ['creditLimit', 'creditLimitLCY']));
        $lastModified = $this->extractFirstIso8601($raw, $this->resolveKeys($fieldKeys, 'last_modified_at', ['lastDateModified', 'systemModifiedAt', 'lastModifiedDateTime']));
        $restrictedReason = $this->extractFirstString($raw, $this->resolveKeys($fieldKeys, 'restricted_reason', ['restrictedReason', 'blockedReason']));

        $address1 = $this->extractFirstString($raw, $this->resolveKeys($fieldKeys, 'address_1', ['address']));
        $address2 = $this->extractFirstString($raw, $this->resolveKeys($fieldKeys, 'address_2', ['address2']));
        $city = $this->extractFirstString($raw, $this->resolveKeys($fieldKeys, 'city', ['city', 'cityName']));
        $state = $this->extractFirstString($raw, $this->resolveKeys($fieldKeys, 'state', ['state', 'stateName']));
        $country = $this->extractFirstString($raw, $this->resolveKeys($fieldKeys, 'country', ['country']));
        $postCode = $this->extractFirstString($raw, $this->resolveKeys($fieldKeys, 'post_code', ['postCode', 'postalCode']));
        $phone = $this->extractFirstString($raw, $this->resolveKeys($fieldKeys, 'phone', ['phoneNo', 'phone', 'telephone']));
        $email = $this->extractFirstString($raw, $this->resolveKeys($fieldKeys, 'email', ['email', 'emailAddress']));
        $vatRegistrationNo = $this->extractFirstString($raw, $this->resolveKeys($fieldKeys, 'vat_registration_no', ['vatRegistrationNo', 'vatRegitrationNo', 'tax1No']));
        $paymentTermsCode = $this->extractFirstString($raw, $this->resolveKeys($fieldKeys, 'payment_terms_code', ['paymentTermsCode', 'paymentTremsCode']));
        $property = $this->extractFirstString($raw, $this->resolveKeys($fieldKeys, 'property', ['property', 'hotelId', 'HotelID']));

        $catalogCode = $this->extractFirstString($raw, $this->resolveKeys($fieldKeys, 'catalog_code', ['catalogCode']));
        $systemModifiedAt = $this->extractFirstIso8601($raw, $this->resolveKeys($fieldKeys, 'system_modified_at', ['systemModifiedAt']));

        $customFields = [];
        foreach ($fieldKeys as $group => $keys) {
            if (in_array($group, $knownGroups, true)) {
                continue;
            }
            if (! is_array($keys) || $keys === []) {
                continue;
            }
            $value = $this->extractFirstScalar($raw, $keys);
            if ($value !== null && $value !== '') {
                $customFields[$group] = $value;
            }
        }

        return [
            'erp_number' => (string) $erpNumber,
            'ar_number' => $arNumber,
            'name' => (string) $name,
            'status' => $status,
            'account_type' => $accountType,
            'active' => $active ?? true,
            'blocked' => $blocked,
            'has_credit' => $hasCredit,
            'credit_limit' => $creditLimit,
            'restricted_reason' => $restrictedReason,
            'address_1' => $address1,
            'address_2' => $address2,
            'city' => $city,
            'state' => $state,
            'country' => $country,
            'post_code' => $postCode,
            'phone' => $phone,
            'email' => $email,
            'vat_registration_no' => $vatRegistrationNo,
            'payment_terms_code' => $paymentTermsCode,
            'property' => $property,
            'catalog_code' => $catalogCode,
            'system_modified_at' => $systemModifiedAt,
            'last_modified_at' => $lastModified,
            'payload' => $raw,
            'custom_fields' => $customFields,
        ];
    }

    /**
     * Map uploaded AR account rows that link directly to existing Opera profiles.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>|null
     */
    public function mapFromUploadedArAccount(array $raw): ?array
    {
        $fieldKeys = app(ErpMappingConfig::class)->customerFieldKeys();

        $operaProfileId = $this->extractFirstString($raw, $this->resolveKeys($fieldKeys, 'opera_profile_id', [
            'account_id',
            'accountId',
            'Account ID',
            'profileId',
            'companyId',
        ]));
        $arNumber = $this->extractFirstString($raw, $this->resolveKeys($fieldKeys, 'ar_number', [
            'ar_number',
            'AR Number',
            'arNumber',
            'accountNo',
            'account_no',
        ]));

        if ($operaProfileId === null || $operaProfileId === '') {
            Log::channel('integration')->debug('ErpCustomerMapper: skipping uploaded row, missing Account ID', [
                'keys' => array_keys($raw),
            ]);
            return null;
        }

        if ($arNumber === null || $arNumber === '') {
            Log::channel('integration')->debug('ErpCustomerMapper: skipping uploaded row, missing AR Number', [
                'profile_id' => $operaProfileId,
                'keys' => array_keys($raw),
            ]);
            return null;
        }

        $name = $this->extractFirstString($raw, $this->resolveKeys($fieldKeys, 'name', ['name', 'displayName']));
        if ($name === null || $name === '') {
            $name = 'Account ' . $arNumber;
        }

        $accountType = $this->extractFirstString($raw, $this->resolveKeys($fieldKeys, 'account_type', ['accountType', 'type']));
        if ($accountType !== null && ! in_array(strtolower((string) $accountType), ['company', 'agent'], true)) {
            Log::channel('integration')->debug('ErpCustomerMapper: skipping uploaded row with unsupported account type', [
                'profile_id' => $operaProfileId,
                'account_type' => $accountType,
            ]);
            return null;
        }

        $status = $this->extractFirstString($raw, $this->resolveKeys($fieldKeys, 'status', ['status']));
        $active = $this->extractFirstBool($raw, $this->resolveKeys($fieldKeys, 'active', ['active']));
        if ($active === null && $status !== null) {
            $active = strtolower(trim($status)) === 'active';
        }
        if ($active === false) {
            Log::channel('integration')->debug('ErpCustomerMapper: skipping inactive uploaded row', [
                'profile_id' => $operaProfileId,
            ]);
            return null;
        }

        $blocked = $this->extractFirstBool($raw, $this->resolveKeys($fieldKeys, 'blocked', ['blocked'])) ?? false;
        $hasCredit = $this->extractFirstBool($raw, $this->resolveKeys($fieldKeys, 'has_credit', ['creditApproved', 'hasCredit'])) ?? false;
        $creditLimit = $this->extractFirstDecimal($raw, $this->resolveKeys($fieldKeys, 'credit_limit', ['creditLimit', 'creditLimitLCY']));
        $restrictedReason = $this->extractFirstString($raw, $this->resolveKeys($fieldKeys, 'restricted_reason', ['restrictedReason', 'blockedReason']));
        $address1 = $this->extractFirstString($raw, $this->resolveKeys($fieldKeys, 'address_1', ['address']));
        $address2 = $this->extractFirstString($raw, $this->resolveKeys($fieldKeys, 'address_2', ['address2']));
        $city = $this->extractFirstString($raw, $this->resolveKeys($fieldKeys, 'city', ['city', 'cityName']));
        $state = $this->extractFirstString($raw, $this->resolveKeys($fieldKeys, 'state', ['state', 'stateName']));
        $country = $this->extractFirstString($raw, $this->resolveKeys($fieldKeys, 'country', ['country']));
        $postCode = $this->extractFirstString($raw, $this->resolveKeys($fieldKeys, 'post_code', ['postCode', 'postalCode']));
        $phone = $this->extractFirstString($raw, $this->resolveKeys($fieldKeys, 'phone', ['phoneNo', 'phone', 'telephone']));
        $email = $this->extractFirstString($raw, $this->resolveKeys($fieldKeys, 'email', ['email', 'emailAddress']));
        $vatRegistrationNo = $this->extractFirstString($raw, $this->resolveKeys($fieldKeys, 'vat_registration_no', ['vatRegistrationNo', 'vatRegitrationNo', 'tax1No']));
        $paymentTermsCode = $this->extractFirstString($raw, $this->resolveKeys($fieldKeys, 'payment_terms_code', ['paymentTermsCode', 'paymentTremsCode']));
        $property = $this->extractFirstString($raw, $this->resolveKeys($fieldKeys, 'property', ['property', 'Property', 'hotelId', 'HotelID']));
        $catalogCode = $this->extractFirstString($raw, $this->resolveKeys($fieldKeys, 'catalog_code', ['catalogCode']));
        $lastModified = $this->extractFirstIso8601($raw, $this->resolveKeys($fieldKeys, 'last_modified_at', ['lastDateModified', 'systemModifiedAt', 'lastModifiedDateTime']));
        $systemModifiedAt = $this->extractFirstIso8601($raw, $this->resolveKeys($fieldKeys, 'system_modified_at', ['systemModifiedAt']));

        return [
            'erp_number' => (string) $arNumber,
            'opera_profile_id' => $operaProfileId,
            'ar_number' => $arNumber,
            'name' => (string) $name,
            'status' => $status,
            'account_type' => $accountType,
            'active' => $active ?? true,
            'blocked' => $blocked,
            'has_credit' => $hasCredit,
            'credit_limit' => $creditLimit,
            'restricted_reason' => $restrictedReason,
            'address_1' => $address1,
            'address_2' => $address2,
            'city' => $city,
            'state' => $state,
            'country' => $country,
            'post_code' => $postCode,
            'phone' => $phone,
            'email' => $email,
            'vat_registration_no' => $vatRegistrationNo,
            'payment_terms_code' => $paymentTermsCode,
            'property' => $property,
            'catalog_code' => $catalogCode,
            'system_modified_at' => $systemModifiedAt,
            'last_modified_at' => $lastModified,
            'payload' => $raw,
            'custom_fields' => [],
        ];
    }

    /**
     * @param  array<int, array>  $rawItems
     * @return array<int, array>
     */
    public function mapManyFromErp(array $rawItems): array
    {
        $mapped = [];
        foreach ($rawItems as $raw) {
            if (! is_array($raw)) {
                continue;
            }
            $item = $this->mapFromErp($raw);
            if ($item !== null) {
                $mapped[] = $item;
            }
        }
        return $mapped;
    }

    /**
     * @param  array<int, array>  $rawItems
     * @return array<int, array>
     */
    public function mapManyFromUploadedArAccounts(array $rawItems): array
    {
        $mapped = [];
        foreach ($rawItems as $raw) {
            if (! is_array($raw)) {
                continue;
            }
            $item = $this->mapFromUploadedArAccount($raw);
            if ($item !== null) {
                $mapped[] = $item;
            }
        }
        return $mapped;
    }

    protected function extractString(array $raw, string $key): ?string
    {
        $v = $raw[$key] ?? null;
        if ($v === null) {
            return null;
        }
        return is_scalar($v) ? (string) $v : null;
    }

    protected function extractBool(array $raw, string $key): ?bool
    {
        $v = $raw[$key] ?? null;
        if ($v === null) {
            return null;
        }
        if (is_bool($v)) {
            return $v;
        }
        if (is_string($v)) {
            return in_array(strtolower($v), ['true', '1', 'yes'], true);
        }
        return (bool) $v;
    }

    protected function extractDecimal(array $raw, string $key): ?float
    {
        $v = $raw[$key] ?? null;
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v)) {
            return (float) $v;
        }
        return null;
    }

    protected function extractIso8601(array $raw, string $key): ?string
    {
        $v = $raw[$key] ?? null;
        if ($v === null || $v === '') {
            return null;
        }
        if (is_string($v)) {
            return $v;
        }
        if ($v instanceof \DateTimeInterface) {
            return $v->format(\DateTimeInterface::ATOM);
        }
        return null;
    }

    /**
     * @param array<int, string> $keys
     */
    protected function extractFirstString(array $raw, array $keys): ?string
    {
        foreach ($keys as $key) {
            $v = $this->extractString($raw, $key);
            if ($v !== null && $v !== '') {
                return $v;
            }
        }
        return null;
    }

    /**
     * @param array<int, string> $keys
     */
    protected function extractFirstBool(array $raw, array $keys): ?bool
    {
        foreach ($keys as $key) {
            $v = $this->extractBool($raw, $key);
            if ($v !== null) {
                return $v;
            }
        }
        return null;
    }

    /**
     * @param array<int, string> $keys
     */
    protected function extractFirstDecimal(array $raw, array $keys): ?float
    {
        foreach ($keys as $key) {
            $v = $this->extractDecimal($raw, $key);
            if ($v !== null) {
                return $v;
            }
        }
        return null;
    }

    /**
     * @param array<int, string> $keys
     */
    protected function extractFirstIso8601(array $raw, array $keys): ?string
    {
        foreach ($keys as $key) {
            $v = $this->extractIso8601($raw, $key);
            if ($v !== null && $v !== '') {
                return $v;
            }
        }
        return null;
    }

    /**
     * @param array<int, string> $keys
     */
    protected function extractFirstScalar(array $raw, array $keys): mixed
    {
        foreach ($keys as $key) {
            $v = $raw[$key] ?? null;
            if ($v === null) {
                continue;
            }
            if (is_scalar($v)) {
                return $v;
            }
        }
        return null;
    }

    /**
     * @param array<string, array<int, string>> $fieldKeys
     * @param array<int, string> $defaults
     * @return array<int, string>
     */
    protected function resolveKeys(array $fieldKeys, string $group, array $defaults): array
    {
        $keys = $fieldKeys[$group] ?? [];
        if (! is_array($keys) || $keys === []) {
            return $defaults;
        }

        $keys = array_values(array_filter(array_map(
            static fn($v) => is_string($v) ? trim($v) : '',
            $keys
        )));

        if ($keys === []) {
            return $defaults;
        }

        // Keep user-defined mapping first, but always include defaults as fallback
        // so minor mapping typos do not break ingestion.
        return array_values(array_unique(array_merge($keys, $defaults)));
    }
}
