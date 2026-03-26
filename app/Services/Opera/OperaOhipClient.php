<?php

namespace App\Services\Opera;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OperaOhipClient
{
    protected ?array $profileIdMap = null;

    public function __construct(
        protected string $gatewayUrl,
        protected ?OperaAuthService $authService,
        protected string $appKey,
        protected string $enterpriseId,
        protected array $propertyIds,
        protected string $arAccountPath = 'ars/v1/hotels/{hotelId}/accounts',
        protected string $arAccountUpdateMethod = 'PUT',
        protected string $companyProfilePath = 'crm/v1/companies',
        protected bool $companyProfileSyncEnabled = true,
        protected bool $companyProfileValidateExisting = true,
        protected string $companyExternalLookupPath = 'crm/v1/externalSystems/{extSystemCode}/profiles/{profileExternalId}',
        protected string $companyExternalSystemCode = '',
        protected string $companyExternalIdContext = 'ERPID',
        protected string $companyKeywordSearchPath = 'crm/v1/profiles?keywordType={keywordType}&keyword={keyword}',
        protected string $companyKeywordType = 'ERPID',
        protected string $profileRestrictedReason = 'No Credit',
        protected string $profileRestrictedReasonField = 'restrictionReason',
        protected string $reservationBulkUpdatePath = 'rsv/v1/hotels/{hotelId}/reservations',
        protected string $reservationBulkUpdateMethod = 'PUT',
        protected int $rateLimitPerMinute = 0,
        protected int $requestDelayMs = 0
    ) {
    }

    public static function fromConfig(): self
    {
        $gatewayUrl = OperaConfig::getString('gateway_url', rtrim((string) config('opera.gateway_url', ''), '/'));
        $appKey = OperaConfig::getString('app_key', (string) config('opera.app_key', ''));
        $enterpriseId = OperaConfig::getString('enterprise_id', (string) config('opera.enterprise_id', ''));
        $propertyIds = OperaConfig::getPropertyIds(config('opera.property_ids', []));

        return new self(
            gatewayUrl: rtrim($gatewayUrl ?? '', '/'),
            authService: OperaAuthService::fromConfig(),
            appKey: $appKey ?? '',
            enterpriseId: $enterpriseId ?? '',
            propertyIds: $propertyIds,
            arAccountPath: config('opera.ar_account_path', 'ars/v1/hotels/{hotelId}/accounts'),
            arAccountUpdateMethod: config('opera.ar_account_update_method', 'PUT'),
            companyProfilePath: config('opera.company_profile_path', 'crm/v1/companies'),
            companyProfileSyncEnabled: (bool) config('opera.company_profile_sync_enabled', true),
            companyProfileValidateExisting: (bool) config('opera.company_profile_validate_existing', true),
            companyExternalLookupPath: config('opera.company_external_lookup_path', 'crm/v1/externalSystems/{extSystemCode}/profiles/{profileExternalId}'),
            companyExternalSystemCode: config('opera.company_external_system_code', ''),
            companyExternalIdContext: config('opera.company_external_id_context', 'ERPID'),
            companyKeywordSearchPath: config('opera.company_keyword_search_path', 'crm/v1/profiles?keywordType={keywordType}&keyword={keyword}'),
            companyKeywordType: config('opera.company_keyword_type', 'ERPID'),
            profileRestrictedReason: config('opera.profile_restricted_reason', 'No Credit'),
            profileRestrictedReasonField: config('opera.profile_restricted_reason_field', 'restrictionReason'),
            reservationBulkUpdatePath: config('opera.reservation_bulk_update_path', 'rsv/v1/hotels/{hotelId}/reservations'),
            reservationBulkUpdateMethod: config('opera.reservation_bulk_update_method', 'PUT'),
            rateLimitPerMinute: config('opera.rate_limit_per_minute', 0),
            requestDelayMs: config('opera.request_delay_ms', 0)
        );
    }

    /**
     * Create AR account in Opera. Returns Opera account number from response.
     */
    public function createArAccount(array $payload): ?string
    {
        $hotelId = $this->extractHotelIdFromPayload($payload);
        $url = $this->baseArUrl($hotelId);
        $requestBody = $payload['criteria'] ?? $payload;
        if (! array_key_exists('criteria', $requestBody)) {
            $requestBody = ['criteria' => $requestBody];
        }
        $response = $this->request('POST', $url, $requestBody, $this->buildHotelHeaders($hotelId));

        if (! $response->successful()) {
            $errorDetail = $this->parseOhipError($response->json(), $response->body());
            Log::channel('integration')->error('Opera create AR account failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'ohip_error' => $errorDetail,
            ]);
            throw new \RuntimeException('Opera create AR account failed: ' . ($errorDetail ?: $response->body()));
        }

        return $this->extractAccountNumber($response->json());
    }

    /**
     * Update AR account in Opera. Returns Opera account number from response.
     */
    public function updateArAccount(string $operaAccountNumber, array $payload, ?string $accountNoFallback = null): ?string
    {
        $hotelId = $this->extractHotelIdFromPayload($payload);
        $url = $this->baseArUrl($hotelId) . '/' . $operaAccountNumber;
        $method = strtoupper($this->arAccountUpdateMethod ?: 'PUT');
        if (! in_array($method, ['PUT', 'PATCH'], true)) {
            $method = 'PUT';
        }
        $requestBody = $payload['accountDetails'] ?? $payload;
        if (! array_key_exists('accountDetails', $requestBody)) {
            $requestBody = ['accountDetails' => $requestBody];
        }
        $response = $this->request($method, $url, $requestBody, $this->buildHotelHeaders($hotelId));

        if (! $response->successful()) {
            $json = $response->json();
            $errorDetail = $this->parseOhipError($json, $response->body());
            $errorCode = is_array($json) ? ($json['o:errorCode'] ?? $json['errorCode'] ?? null) : null;

            if ($accountNoFallback !== null && $accountNoFallback !== '' && $accountNoFallback !== $operaAccountNumber && (string) $errorCode === 'FOF00311') {
                $fallbackUrl = $this->baseArUrl($hotelId) . '/' . $accountNoFallback;
                $retry = $this->request($method, $fallbackUrl, $requestBody, $this->buildHotelHeaders($hotelId));
                if ($retry->successful()) {
                    return $this->extractAccountNumber($retry->json()) ?? $accountNoFallback;
                }
            }

            Log::channel('integration')->error('Opera update AR account failed', [
                'account_number' => $operaAccountNumber,
                'status' => $response->status(),
                'body' => $response->body(),
                'ohip_error' => $errorDetail,
            ]);
            throw new \RuntimeException('Opera update AR account failed: ' . ($errorDetail ?: $response->body()));
        }

        return $this->extractAccountNumber($response->json()) ?? $operaAccountNumber;
    }

    /**
     * Build payload for Opera AR from normalized internal customer data.
     * Credit activation/deactivation via AR Accounts; reason for restriction recorded when credit not approved.
     *
     * @param  array{erp_number: string, name: string, has_credit: bool, credit_limit: ?float, restricted_reason?: string}  $normalized
     */
    public function buildArPayload(array $normalized, ?string $propertyId = null): array
    {
        // Always prefer configured Opera hotel for this project (e.g. CHR).
        $propertyId = $propertyId ?? ($this->propertyIds[0] ?? null) ?? ($normalized['property'] ?? null);
        $hasCredit = (bool) ($normalized['has_credit'] ?? false);
        $creditApproved = $hasCredit;
        $erpNumber = $normalized['erp_number'] ?? '';
        $arNumberRaw = $normalized['ar_number'] ?? null;
        $arNumber = $arNumberRaw;
        if (is_string($arNumberRaw) && str_contains($arNumberRaw, '_')) {
            [$base, $suffix] = explode('_', $arNumberRaw, 2);
            $arNumber = $base;
            if (! empty($suffix)) {
                $propertyId = $suffix;
            }
        }
        $externalRefPrefix = config('opera.ar_external_ref_prefix', '');
        $externalReference = $externalRefPrefix !== '' ? $externalRefPrefix . $erpNumber : $erpNumber;
        $addressId = $normalized['opera_ar_address_id'] ?? null;
        $addressType = $normalized['opera_ar_address_type'] ?? 'AR ADDRESS';
        $addressLine = $normalized['address_1'] ?? null;
        $postCode = $normalized['post_code'] ?? null;
        $country = $normalized['country'] ?? null;

        // Client rule: keep restricted false for now (even if ERP says no credit),
        // so AR creation is not blocked by restricted reason requirements.
        $restricted = false;

        $base = [
            'accountNo' => $arNumber ?: $externalReference,
            'status' => [
                // Client rule: ERP hasCredit=true => Opera restricted=false (ignore blocked/active here)
                'restricted' => $restricted,
            ],
            'creditLimit' => [
                'amount' => (string) ($normalized['credit_limit'] ?? 0),
            ],
            'accountName' => $normalized['name'] ?? '',
            'monthEndCalcYN' => true,
            'batchStatement' => true,
            'emailStatementsReminders' => false,
            'primary' => true,
            'type' => 'DFT',
            'permanent' => true,
            'hotelId' => $propertyId,
        ];
        // Keep AR payload aligned with working schema (no restrictedReason/restriction fields).

        if (! empty($normalized['opera_profile_id'])) {
            $base['profileId'] = [
                'type' => 'Profile',
                'idContext' => 'OPERA',
                'id' => (string) $normalized['opera_profile_id'],
            ];
        }
        if ($addressId !== null && $addressId !== '') {
            $base['address'] = [
                'address' => [
                    'addressLine' => array_values(array_filter([
                        $addressLine ?: '',
                    ], fn ($v) => $v !== '')),
                    'postalCode' => $postCode ?: '',
                    'country' => [
                        'code' => $country ?: '',
                    ],
                    'type' => $addressType,
                    'typeDescription' => 'AR Address',
                ],
                'id' => (string) $addressId,
                'type' => 'Address',
            ];
        }
        $normalizedEmail = $this->normalizeEmail($normalized['email'] ?? null);
        if (is_string($normalizedEmail) && $normalizedEmail !== '') {
            $base['email'] = [
                'email' => [
                    'emailAddress' => $normalizedEmail,
                    'type' => 'EMAIL BUSINESS',
                    'emailFormat' => 'HTML',
                    'primaryInd' => true,
                    'orderSequence' => '1',
                ],
            ];
        }

        return [
            'criteria' => $base,
            'accountDetails' => $base,
        ];
    }

    /**
     * Build minimal payload for AR updates (when account exists).
     * Only updates restricted + creditLimit to avoid overwriting other fields.
     */
    public function buildArUpdatePayload(array $normalized, ?string $propertyId = null): array
    {
        $propertyId = $propertyId ?? ($this->propertyIds[0] ?? null) ?? ($normalized['property'] ?? null);
        $erpNumber = $normalized['erp_number'] ?? '';
        $arNumberRaw = $normalized['ar_number'] ?? null;
        $arNumber = $arNumberRaw;
        if (is_string($arNumberRaw) && str_contains($arNumberRaw, '_')) {
            [$base, $suffix] = explode('_', $arNumberRaw, 2);
            $arNumber = $base;
            if (! empty($suffix)) {
                $propertyId = $suffix;
            }
        }
        $externalRefPrefix = config('opera.ar_external_ref_prefix', '');
        $externalReference = $externalRefPrefix !== '' ? $externalRefPrefix . $erpNumber : $erpNumber;

        $profileId = $normalized['opera_profile_id'] ?? null;
        $addressId = $normalized['opera_ar_address_id'] ?? null;
        $addressType = $normalized['opera_ar_address_type'] ?? 'AR ADDRESS';

        $restricted = false;
        $base = [
            'accountNo' => $arNumber ?: $externalReference,
            'status' => [
                'restricted' => $restricted,
            ],
            'creditLimit' => [
                'amount' => (string) ($normalized['credit_limit'] ?? 0),
            ],
            'hotelId' => $propertyId,
            'type' => 'DFT',
        ];

        if (! empty($profileId)) {
            $base['profileId'] = [
                'type' => 'Profile',
                'idContext' => 'OPERA',
                'id' => (string) $profileId,
            ];
        }

        if ($addressId !== null && $addressId !== '') {
            $base['address'] = [
                'address' => [
                    'type' => $addressType,
                    'typeDescription' => 'AR Address',
                ],
                'id' => (string) $addressId,
                'type' => 'Address',
            ];
        }

        Log::channel('integration')->info('Opera AR update payload meta', [
            'erp_number' => $erpNumber ?: null,
            'account_no' => $base['accountNo'] ?? null,
            'hotel_id' => $propertyId,
            'profile_id' => $profileId ?: null,
            'ar_address_id' => $addressId ?: null,
        ]);

        return [
            'accountDetails' => $base,
        ];
    }

    /**
     * Create company profile in Opera when missing and return Opera profile ID.
     */
    public function syncCompanyProfile(array $normalized, ?string $existingProfileId = null, bool $allowCreate = true): ?string
    {
        if (! $this->companyProfileSyncEnabled) {
            return $existingProfileId;
        }
        $providedProfileId = $existingProfileId !== null && $existingProfileId !== '';
        if ($providedProfileId) {
            if ($this->companyProfileValidateExisting && ! $this->profileExists($existingProfileId)) {
                Log::channel('integration')->warning('Opera company profile: stored profile_id is invalid; stopping sync for this record', [
                    'erp_number' => $normalized['erp_number'] ?? null,
                    'profile_id' => $existingProfileId,
                ]);
                throw new \RuntimeException('Opera company profile id is invalid; cannot proceed without a valid mapping. profile_id=' . $existingProfileId);
            } else {
            Log::channel('integration')->info('Opera company profile: using existing profile_id', [
                'erp_number' => $normalized['erp_number'] ?? null,
                'profile_id' => $existingProfileId,
            ]);
            return $existingProfileId;
            }
        }

        $erpNumber = (string) ($normalized['erp_number'] ?? '');
        if ($erpNumber === '') {
            return null;
        }

        $mappedProfileId = $this->resolveProfileIdFromMapping($erpNumber);
        if ($mappedProfileId !== null) {
            if ($this->companyProfileValidateExisting && ! $this->profileExists($mappedProfileId)) {
                Log::channel('integration')->warning('Opera company profile: mapped profile_id is invalid, skipping', [
                    'erp_number' => $erpNumber,
                    'profile_id' => $mappedProfileId,
                ]);
            } else {
            Log::channel('integration')->info('Opera company profile: matched from mapping', [
                'erp_number' => $erpNumber,
                'profile_id' => $mappedProfileId,
            ]);
            return $mappedProfileId;
            }
        }

        $profileId = $this->findProfileByExternalReference($erpNumber);
        if ($profileId !== null) {
            if ($this->companyProfileValidateExisting && ! $this->profileExists($profileId)) {
                Log::channel('integration')->warning('Opera company profile: external lookup profile_id is invalid, skipping', [
                    'erp_number' => $erpNumber,
                    'profile_id' => $profileId,
                ]);
            } else {
            Log::channel('integration')->info('Opera company profile: matched by external reference', [
                'erp_number' => $erpNumber,
                'profile_id' => $profileId,
            ]);
            return $profileId;
            }
        }

        $profileId = $this->findProfileByKeyword($erpNumber);
        if ($profileId !== null) {
            if ($this->companyProfileValidateExisting && ! $this->profileExists($profileId)) {
                Log::channel('integration')->warning('Opera company profile: keyword profile_id is invalid, skipping', [
                    'erp_number' => $erpNumber,
                    'profile_id' => $profileId,
                ]);
            } else {
            Log::channel('integration')->info('Opera company profile: matched by keyword', [
                'erp_number' => $erpNumber,
                'profile_id' => $profileId,
            ]);
            return $profileId;
            }
        }

        if (! $allowCreate) {
            return null;
        }

        try {
            $createdId = $this->createCompanyProfile($normalized);
            if ($createdId) {
                Log::channel('integration')->info('Opera company profile: created', [
                    'erp_number' => $erpNumber,
                    'profile_id' => $createdId,
                ]);
            }
            return $createdId;
        } catch (\Throwable $e) {
            // If profile might already exist, retry external lookup (when configured).
            $profileId = $this->findProfileByExternalReference($erpNumber);
            if ($profileId !== null) {
                Log::channel('integration')->info('Opera company profile: matched after create failure (external ref)', [
                    'erp_number' => $erpNumber,
                    'profile_id' => $profileId,
                ]);
                return $profileId;
            }
            $profileId = $this->findProfileByKeyword($erpNumber);
            if ($profileId !== null) {
                Log::channel('integration')->info('Opera company profile: matched after create failure (keyword)', [
                    'erp_number' => $erpNumber,
                    'profile_id' => $profileId,
                ]);
                return $profileId;
            }
            throw $e;
        }
    }

    /**
     * Send bulk reservation payload (JSON) to Opera endpoint.
     */
    public function bulkUpdateReservations(array $payload): array
    {
        $url = $this->gatewayUrl . '/' . ltrim($this->resolvePath($this->reservationBulkUpdatePath), '/');
        $method = strtoupper($this->reservationBulkUpdateMethod ?: 'PUT');
        if (! in_array($method, ['PUT', 'POST', 'PATCH'], true)) {
            $method = 'PUT';
        }

        $response = $this->request($method, $url, $payload);
        if (! $response->successful()) {
            $errorDetail = $this->parseOhipError($response->json(), $response->body());
            throw new \RuntimeException('Opera reservation bulk update failed: ' . ($errorDetail ?: $response->body()));
        }

        return $response->json() ?? [];
    }

    protected function baseArUrl(?string $hotelId = null): string
    {
        return $this->gatewayUrl . '/' . ltrim($this->resolvePath($this->arAccountPath, $hotelId), '/');
    }

    protected function request(string $method, string $url, array $body = [], array $headers = []): \Illuminate\Http\Client\Response
    {
        $this->applyRateLimit();

        $headers = array_merge($this->authHeaders(), $headers);

        $http = Http::withHeaders($headers);

        $verb = strtoupper($method);
        if ($verb === 'GET' && isset($body['query']) && is_array($body['query'])) {
            $response = $http->get($url, $body['query']);
        } elseif (in_array($verb, ['POST', 'PATCH', 'PUT']) && $body !== []) {
            $response = $http->{strtolower($verb)}($url, $body);
        } else {
            $response = $http->{strtolower($verb)}($url);
        }

        if (! $response->successful()) {
            Log::channel('integration')->warning('Opera API request failed', [
                'method' => $verb,
                'url' => $url,
                'status' => $response->status(),
                'request' => $body,
                'response_body' => $response->body(),
            ]);
        }

        return $response;
    }

    protected function applyRateLimit(): void
    {
        if ($this->requestDelayMs > 0) {
            usleep($this->requestDelayMs * 1000);
        }
        if ($this->rateLimitPerMinute > 0) {
            $key = 'opera_ohip_rate_limit';
            $cache = app('cache.store');
            $count = (int) $cache->get($key, 0);
            $minInterval = 60.0 / $this->rateLimitPerMinute;
            if ($count > 0) {
                $lastHit = (float) $cache->get($key . '_at', 0);
                $elapsed = microtime(true) - $lastHit;
                if ($elapsed < $minInterval) {
                    usleep((int) (($minInterval - $elapsed) * 1_000_000));
                }
            }
            $cache->put($key, $count + 1, 60);
            $cache->put($key . '_at', microtime(true), 60);
        }
    }

    /**
     * Auth headers. OHIP property APIs expect Bearer token from OAuth + x-app-key.
     */
    protected function authHeaders(): array
    {
        $token = $this->authService ? $this->authService->getAccessToken() : 'dummy_token';

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'x-app-key' => $this->appKey,
            'Authorization' => 'Bearer ' . $token,
        ];

        if (! empty($this->propertyIds[0])) {
            $headers['x-hotelid'] = (string) $this->propertyIds[0];
        }

        if ($this->companyExternalSystemCode !== '') {
            $headers['x-externalsystem'] = $this->companyExternalSystemCode;
        }

        return $headers;
    }

    /**
     * Extract Opera account number from AR create/update response.
     * Field names from config (reference: ars.json); update when final response from client.
     */
    protected function extractAccountNumber(array|null $data): ?string
    {
        if ($data === null) {
            return null;
        }
        $fields = config('opera.ar_account_response_fields', [
            'accountNumber',
            'account_number',
            'id',
            'accountId.id',
            'criteria.accountId.id',
            'accountDetails.accountId.id',
        ]);
        if ($fields === []) {
            $fields = [
                'accountNumber',
                'account_number',
                'id',
                'accountId.id',
                'criteria.accountId.id',
                'accountDetails.accountId.id',
            ];
        }
        foreach ($fields as $field) {
            $v = $this->getByDotPath($data, $field);
            if ($v !== null && $v !== '') {
                return is_scalar($v) ? (string) $v : null;
            }
        }
        return null;
    }

    /**
     * Parse OHIP-style error body (int.json / ars.json exceptionDetailType).
     * Returns a short message for logs/exception; empty string if not parseable.
     */
    protected function parseOhipError(array|null $json, string $rawBody): string
    {
        if (! is_array($json)) {
            return '';
        }
        $detail = $json['detail'] ?? $json['title'] ?? null;
        $code = $json['o:errorCode'] ?? $json['errorCode'] ?? null;
        $path = $json['o:errorPath'] ?? $json['errorPath'] ?? null;
        $parts = array_filter([$code, $path, $detail]);
        return $parts !== [] ? implode(' ', $parts) : '';
    }

    protected function findProfileByExternalReference(string $externalId): ?string
    {
        if ($this->companyExternalSystemCode === '') {
            return null;
        }

        $path = str_replace(
            ['{extSystemCode}', '{profileExternalId}'],
            [rawurlencode($this->companyExternalSystemCode), rawurlencode($externalId)],
            $this->companyExternalLookupPath
        );
        $url = $this->gatewayUrl . '/' . ltrim($this->resolvePath($path), '/');
        $response = $this->request('GET', $url);
        if (! $response->successful()) {
            return null;
        }

        return $this->extractProfileId($response->json());
    }

    protected function findProfileByKeyword(string $keyword): ?string
    {
        if ($this->companyKeywordSearchPath === '') {
            return null;
        }

        $path = str_replace(
            ['{keywordType}', '{keyword}'],
            [rawurlencode($this->companyKeywordType), rawurlencode($keyword)],
            $this->companyKeywordSearchPath
        );
        $url = $this->gatewayUrl . '/' . ltrim($this->resolvePath($path), '/');
        $response = $this->request('GET', $url);
        if (! $response->successful()) {
            return null;
        }

        return $this->extractProfileId($response->json());
    }

    protected function profileExists(string $profileId): bool
    {
        $url = $this->companyProfileBaseUrl($profileId);
        $response = $this->request('GET', $url);
        if ($response->successful()) {
            return true;
        }

        $data = $response->json();
        if (is_array($data)) {
            $code = $data['o:errorCode'] ?? $data['errorCode'] ?? null;
            if ((string) $code === 'OPERAWS-CRM00073') {
                return false;
            }
        }

        return false;
    }

    protected function createCompanyProfile(array $normalized): ?string
    {
        $url = $this->gatewayUrl . '/' . ltrim($this->resolvePath($this->companyProfilePath), '/');
        $payload = $this->buildCompanyPayload($normalized);
        $response = $this->request('POST', $url, $payload);

        if (! $response->successful()) {
            $errorDetail = $this->parseOhipError($response->json(), $response->body());
            Log::channel('integration')->error('Opera company profile create failed', [
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
                'ohip_error' => $errorDetail,
            ]);
            throw new \RuntimeException(
                'Opera company profile create failed [status=' . $response->status() . ', url=' . $url . ']: ' . ($errorDetail ?: $response->body())
            );
        }

        return $this->extractProfileId($response->json());
    }

    protected function buildCompanyPayload(array $normalized): array
    {
        $name = $this->sanitizeCompanyName((string) ($normalized['name'] ?? ''));
        $erpNumber = (string) ($normalized['erp_number'] ?? '');
        $email = $this->normalizeEmail($normalized['email'] ?? null);
        $phone = $this->normalizePhone($normalized['phone'] ?? null);
        $address1 = $normalized['address_1'] ?? null;
        $address2 = $normalized['address_2'] ?? null;
        $city = $normalized['payload']['city'] ?? $normalized['payload']['cityName'] ?? $normalized['city'] ?? null;
        $state = $normalized['payload']['state'] ?? $normalized['payload']['stateName'] ?? $normalized['state'] ?? null;
        $country = $normalized['country'] ?? null;
        $postCode = $normalized['post_code'] ?? null;
        $vat = $normalized['vat_registration_no'] ?? null;
        $profileType = $normalized['account_type'] ?? 'Company';

        $rawName = (string) ($normalized['name'] ?? '');
        if ($name === '' || ! preg_match('/[A-Za-z]/', $name)) {
            $fallback = $erpNumber !== '' ? 'Company ' . $erpNumber : 'Company';
            $name = $this->sanitizeCompanyName($fallback);
        }
        if ($rawName !== '' && $name !== $rawName) {
            Log::channel('integration')->info('Opera companyName sanitized', [
                'erp_number' => $erpNumber,
                'raw_name' => $rawName,
                'sanitized_name' => $name,
            ]);
        }
        if ($rawName === '' && $name !== '') {
            Log::channel('integration')->info('Opera companyName fallback', [
                'erp_number' => $erpNumber,
                'sanitized_name' => $name,
            ]);
        }

        $companyName = $name !== '' ? $name : $erpNumber;
        [$companyName1, $companyName2] = $this->splitCompanyName($companyName, 30);
        if ($companyName2 !== '') {
            Log::channel('integration')->info('Opera companyName split', [
                'erp_number' => $erpNumber,
                'company_name' => $companyName1,
                'company_name2' => $companyName2,
            ]);
        }

        $company = [
            'companyName' => $companyName1,
            'iATAInfo' => new \stdClass(),
            'language' => 'E',
        ];
        if ($companyName2 !== '') {
            $company['companyName2'] = $companyName2;
        }

        // Client rule: keep Opera profile restricted=false by default,
        // even if ERP sends restricted/blocked flags. This avoids rejection
        // when the endpoint does not accept restriction reason fields.
        $profileRestrictions = [
            'restricted' => false,
        ];

        $payload = [
            'profileDetails' => [
                'company' => $company,
                'profileType' => $profileType ?: 'Company',
                'statusCode' => ! empty($normalized['active']) || ($normalized['active'] ?? true) ? 'Active' : 'Inactive',
                'keywords' => [
                    'keyword' => [
                        [
                            'keywordDetail' => [
                                'newKeyword' => $erpNumber,
                            ],
                            'type' => 'ERPID',
                        ],
                    ],
                ],
                'profileRestrictions' => $profileRestrictions,
                'mailingActions' => [
                    'active' => true,
                ],
            ],
            'profileIdList' => [],
        ];
        if ($address1 || $address2 || $country || $postCode || $city || $state) {
            $addressLines = array_values(array_filter([
                $address1 ?: '',
                $address2 ?: '',
            ], fn ($v) => $v !== ''));
            $businessAddress = [
                'address' => [
                    'addressLine' => $addressLines,
                    'cityName' => $city ?: '',
                    'state' => $state ?: '',
                    'postalCode' => $postCode ?: '',
                    'country' => [
                        'value' => $country ?: '',
                    ],
                    'language' => 'E',
                    'type' => 'BUSINESS',
                    'primaryInd' => true,
                    'updateReservations' => false,
                ],
            ];
            $arAddress = [
                'address' => [
                    'addressLine' => $addressLines,
                    'cityName' => $city ?: '',
                    'state' => $state ?: '',
                    'postalCode' => $postCode ?: '',
                    'country' => [
                        'value' => $country ?: '',
                    ],
                    'language' => 'E',
                    'type' => 'AR ADDRESS',
                    'primaryInd' => true,
                    'updateReservations' => false,
                ],
            ];
            $payload['profileDetails']['addresses'] = [
                'addressInfo' => [
                    $businessAddress,
                    $arAddress,
                ],
            ];
        }
        if (is_scalar($vat) && trim((string) $vat) !== '') {
            $payload['profileDetails']['taxInfo'] = [
                'tax1No' => (string) $vat,
            ];
        }
        if (is_string($phone) && $phone !== '') {
            $payload['profileDetails']['telephones'] = [
                'telephoneInfo' => [
                    [
                        'telephone' => [
                            'phoneNumber' => (string) $phone,
                        ],
                    ],
                ],
            ];
        }

        if (is_string($email) && $email !== '') {
            $payload['profileDetails']['emails'] = [
                'emailInfo' => [
                    [
                        'email' => [
                            'emailAddress' => (string) $email,
                            'type' => 'EMAIL BUSINESS',
                            'emailFormat' => 'HTML',
                            'primaryInd' => true,
                            'orderSequence' => '1',
                        ],
                    ],
                ],
            ];
        }

        return $payload;
    }

    protected function sanitizeCompanyName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }
        // Remove control chars and normalize whitespace
        $name = preg_replace('/[\\x00-\\x1F\\x7F]/', '', $name) ?? $name;
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        if (is_string($ascii) && $ascii !== '') {
            $name = $ascii;
        }
        // Keep a conservative, Opera-safe character set (letters/spaces only).
        $name = preg_replace('/[^A-Za-z ]/', '', $name) ?? $name;
        $name = preg_replace('/\\s+/', ' ', $name) ?? $name;
        if ($name === '') {
            return '';
        }
        if (! preg_match('/^[A-Za-z]/', $name)) {
            $name = 'Company ' . $name;
            $name = preg_replace('/\\s+/', ' ', $name) ?? $name;
        }
        // Be conservative with length to avoid OHIP validation errors.
        if (mb_strlen($name) > 60) {
            $name = mb_substr($name, 0, 60);
        }
        return $name;
    }

    protected function normalizeEmail(?string $email): ?string
    {
        if (! is_string($email)) {
            return null;
        }
        $email = trim($email);
        if ($email === '') {
            return null;
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            Log::channel('integration')->info('Opera email skipped: invalid format', [
                'email' => $email,
            ]);
            return null;
        }
        return $email;
    }

    protected function normalizePhone(?string $phone): ?string
    {
        if (! is_string($phone)) {
            return null;
        }
        $phone = trim($phone);
        if ($phone === '') {
            return null;
        }
        return $phone;
    }

    /**
     * Split long company names into companyName and companyName2.
     *
     * @return array{0: string, 1: string}
     */
    protected function splitCompanyName(string $name, int $maxLength = 30): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['', ''];
        }
        if (mb_strlen($name) <= $maxLength) {
            return [$name, ''];
        }
        $name1 = rtrim(mb_substr($name, 0, $maxLength));
        $name2 = ltrim(mb_substr($name, $maxLength));
        return [$name1, $name2];
    }

    protected function extractProfileId(array|null $data): ?string
    {
        if (! is_array($data)) {
            return null;
        }

        $profileList = $data['profileIdList'] ?? $data['companyIdList'] ?? null;
        if (is_array($profileList)) {
            foreach ($profileList as $profile) {
                if (is_array($profile) && ! empty($profile['id'])) {
                    return (string) $profile['id'];
                }
            }
        }

        $links = $data['links'] ?? null;
        if (is_array($links)) {
            foreach ($links as $link) {
                if (! is_array($link) || empty($link['href'])) {
                    continue;
                }
                $href = (string) $link['href'];
                if (preg_match('#/(companies|profiles)/([^/?]+)$#', $href, $m)) {
                    return $m[2];
                }
            }
        }

        return null;
    }

    protected function companyProfileBaseUrl(string $profileId): string
    {
        return $this->gatewayUrl . '/crm/v1/profiles/' . rawurlencode($profileId);
    }

    protected function fetchProfileWithAddresses(string $profileId): ?array
    {
        $url = $this->companyProfileBaseUrl($profileId);
        $response = $this->request('GET', $url, [
            'query' => [
                'fetchInstructions' => 'Address',
            ],
        ]);

        Log::channel('integration')->info('Opera profile fetch with Address instructions completed', [
            'profile_id' => $profileId,
            'url' => $url,
            'status' => $response->status(),
            'successful' => $response->successful(),
            'body_preview' => mb_substr($response->body(), 0, 2000),
        ]);

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();
        if (! is_array($data)) {
            Log::channel('integration')->warning('Opera profile fetch returned non-array JSON for address lookup', [
                'profile_id' => $profileId,
                'url' => $url,
                'body_preview' => mb_substr($response->body(), 0, 2000),
            ]);
            return null;
        }

        $entries = $this->extractAddressInfoEntries($data);
        Log::channel('integration')->info('Opera profile address entries extracted from Address fetch', [
            'profile_id' => $profileId,
            'entry_count' => count($entries),
            'top_level_keys' => array_keys($data),
        ]);
        if ($entries !== []) {
            return $data;
        }

        Log::channel('integration')->info('Opera profile address fetch returned no address entries; retrying raw profile fetch', [
            'profile_id' => $profileId,
            'top_level_keys' => array_keys($data),
        ]);

        $fallbackResponse = $this->request('GET', $url);
        Log::channel('integration')->info('Opera profile raw fetch completed for address fallback', [
            'profile_id' => $profileId,
            'url' => $url,
            'status' => $fallbackResponse->status(),
            'successful' => $fallbackResponse->successful(),
            'body_preview' => mb_substr($fallbackResponse->body(), 0, 2000),
        ]);
        if (! $fallbackResponse->successful()) {
            return $data;
        }

        $fallbackData = $fallbackResponse->json();
        if (! is_array($fallbackData)) {
            Log::channel('integration')->warning('Opera profile raw fetch returned non-array JSON for address fallback', [
                'profile_id' => $profileId,
                'url' => $url,
                'body_preview' => mb_substr($fallbackResponse->body(), 0, 2000),
            ]);
            return $data;
        }

        $fallbackEntries = $this->extractAddressInfoEntries($fallbackData);
        Log::channel('integration')->info('Opera profile address entries extracted from raw fetch', [
            'profile_id' => $profileId,
            'entry_count' => count($fallbackEntries),
            'top_level_keys' => array_keys($fallbackData),
        ]);

        return $fallbackData;
    }

    /**
     * Normalize addressInfo collection from possible response shapes.
     *
     * @return array<int, array>
     */
    protected function extractAddressInfoEntries(array $data): array
    {
        $candidates = [
            $data['profileDetails']['addresses']['addressInfo'] ?? null,
            $data['profileDetails']['addresses'] ?? null,
            $data['addresses']['addressInfo'] ?? null,
            $data['addresses'] ?? null,
            $data['addressInfo'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $entries = $this->normalizeAddressInfo($candidate);
            if ($entries !== []) {
                return $entries;
            }
        }

        return $this->collectAddressEntries($data);
    }

    /**
     * @return array<int, array>
     */
    protected function normalizeAddressInfo(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }
        if (array_is_list($value)) {
            return array_values(array_filter($value, fn ($v) => is_array($v)));
        }
        if (isset($value['addressInfo'])) {
            return $this->normalizeAddressInfo($value['addressInfo']);
        }
        if (isset($value['address']) || isset($value['type']) || isset($value['addressType']) || isset($value['id'])) {
            return [$value];
        }
        return [];
    }


    /**
     * @return array<int, array>
     */
    protected function collectAddressEntries(array $data): array
    {
        $entries = [];
        $stack = [$data];

        while ($stack !== []) {
            $current = array_pop($stack);
            if (! is_array($current)) {
                continue;
            }

            if ($this->looksLikeAddressEntry($current)) {
                $entries[] = $current;
                continue;
            }

            foreach ($current as $value) {
                if (is_array($value)) {
                    $stack[] = $value;
                }
            }
        }

        return $entries;
    }

    protected function looksLikeAddressEntry(array $value): bool
    {
        if (isset($value['address']) && is_array($value['address'])) {
            return true;
        }

        $addressKeys = [
            'addressLine',
            'addressLines',
            'line1',
            'line2',
            'cityName',
            'city',
            'state',
            'stateName',
            'postalCode',
            'postCode',
            'zipCode',
            'country',
            'countryCode',
            'type',
            'addressType',
        ];

        foreach ($addressKeys as $key) {
            if (array_key_exists($key, $value)) {
                return true;
            }
        }

        return false;
    }

    protected function extractAddressType(array $entry): ?string
    {
        $type = $entry['address']['type'] ?? $entry['type'] ?? $entry['addressType'] ?? null;
        if (! $type) {
            $type = $entry['address']['typeDescription'] ?? $entry['typeDescription'] ?? null;
        }
        return is_string($type) ? $type : null;
    }

    protected function extractAddressId(array $entry): ?string
    {
        $id = $entry['id'] ?? $entry['address']['id'] ?? $entry['addressId'] ?? $entry['address']['addressId'] ?? null;
        if ($id === null || $id === '') {
            return null;
        }
        return (string) $id;
    }

    protected function extractArAddressIdFromEntries(array $entries): ?string
    {
        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $type = $this->extractAddressType($entry);
            $normalizedType = strtoupper(str_replace('_', ' ', (string) $type));
            if ($normalizedType !== 'AR ADDRESS') {
                continue;
            }
            $id = $this->extractAddressId($entry);
            if ($id !== null) {
                return $id;
            }
        }
        return null;
    }

    public function getProfileArAddressId(string $profileId): ?string
    {
        $data = $this->fetchProfileWithAddresses($profileId);
        if (! is_array($data)) {
            return null;
        }
        $entries = $this->extractAddressInfoEntries($data);
        return $this->extractArAddressIdFromEntries($entries);
    }

    /**
     * Ensure the profile has an AR ADDRESS. If missing, create one using ERP data.
     */
    public function ensureProfileArAddress(string $profileId, array $normalized): ?string
    {
        $profileData = $this->fetchProfileWithAddresses($profileId);
        if (! is_array($profileData)) {
            Log::channel('integration')->warning('Opera profile fetch returned no data for AR ADDRESS ensure', [
                'profile_id' => $profileId,
                'erp_number' => $normalized['erp_number'] ?? null,
            ]);
        }
        $existing = is_array($profileData)
            ? $this->extractArAddressIdFromEntries($this->extractAddressInfoEntries($profileData))
            : null;
        if (! empty($existing)) {
            return $existing;
        }

        Log::channel('integration')->info('Opera profile missing AR ADDRESS, preparing create from ERP data', [
            'profile_id' => $profileId,
            'erp_number' => $normalized['erp_number'] ?? null,
            'address_1' => $normalized['address_1'] ?? null,
            'address_2' => $normalized['address_2'] ?? null,
            'city' => $normalized['payload']['city'] ?? $normalized['payload']['cityName'] ?? ($normalized['city'] ?? null),
            'state' => $normalized['payload']['state'] ?? $normalized['payload']['stateName'] ?? ($normalized['state'] ?? null),
            'post_code' => $normalized['post_code'] ?? null,
            'country' => $normalized['country'] ?? null,
        ]);

        $payload = $this->buildProfileArAddressPayload($normalized);
        if ($payload === null && is_array($profileData)) {
            $payload = $this->buildProfileArAddressPayloadFromProfile($profileData);
            if ($payload !== null) {
                Log::channel('integration')->info('Opera profile missing AR ADDRESS, using existing profile address as source', [
                    'profile_id' => $profileId,
                    'erp_number' => $normalized['erp_number'] ?? null,
                ]);
            }
        }
        if ($payload === null) {
            Log::channel('integration')->warning('Opera profile missing AR ADDRESS and ERP has no address data', [
                'profile_id' => $profileId,
                'erp_number' => $normalized['erp_number'] ?? null,
            ]);
            return null;
        }

        Log::channel('integration')->info('Opera profile AR ADDRESS update payload', [
            'profile_id' => $profileId,
            'erp_number' => $normalized['erp_number'] ?? null,
            'payload' => $payload,
        ]);

        $url = $this->companyProfileBaseUrl($profileId);
        $response = $this->request('PUT', $url, $payload);
        if (! $response->successful()) {
            $errorDetail = $this->parseOhipError($response->json(), $response->body());
            $errorCode = null;
            $json = $response->json();
            if (is_array($json)) {
                $errorCode = $json['o:errorCode'] ?? $json['errorCode'] ?? null;
            }
            Log::channel('integration')->error('Opera profile AR ADDRESS update failed', [
                'profile_id' => $profileId,
                'status' => $response->status(),
                'body' => $response->body(),
                'ohip_error' => $errorDetail,
            ]);
            if ((string) $errorCode === 'OPERAWS-CRM00073') {
                throw new \RuntimeException('Opera profile id invalid (OPERAWS-CRM00073)');
            }
            return null;
        }

        return $this->getProfileArAddressId($profileId);
    }

    /**
     * Build minimal payload to add an AR ADDRESS to a profile.
     */
    protected function buildProfileArAddressPayload(array $normalized): ?array
    {
        $payload = is_array($normalized['payload'] ?? null) ? $normalized['payload'] : [];
        $address1 = $normalized['address_1'] ?? ($payload['address'] ?? null);
        $address2 = $normalized['address_2'] ?? ($payload['address2'] ?? null);
        $city = $normalized['payload']['city'] ?? $normalized['payload']['cityName'] ?? $normalized['city'] ?? null;
        $state = $normalized['payload']['state'] ?? $normalized['payload']['stateName'] ?? $normalized['state'] ?? null;
        $country = $normalized['country'] ?? ($payload['country'] ?? null);
        $postCode = $normalized['post_code'] ?? ($payload['postCode'] ?? ($payload['postalCode'] ?? null));

        if (! $address1 && ! $address2 && ! $city && ! $state && ! $country && ! $postCode) {
            return null;
        }

        $addressLines = array_values(array_filter([
            $address1 ?: '',
            $address2 ?: '',
        ], fn ($v) => $v !== ''));

        $arAddress = [
            'address' => [
                'addressLine' => $addressLines,
                'cityName' => $city ?: '',
                'state' => $state ?: '',
                'postalCode' => $postCode ?: '',
                'country' => [
                    'value' => $country ?: '',
                ],
                'language' => 'E',
                'type' => 'AR ADDRESS',
                'primaryInd' => true,
                'updateReservations' => false,
            ],
        ];

        return [
            'profileDetails' => [
                'addresses' => [
                    'addressInfo' => [
                        $arAddress,
                    ],
                ],
            ],
        ];
    }

    /**
     * Build AR ADDRESS payload using existing profile address data (fallback).
     */
    protected function buildProfileArAddressPayloadFromProfile(array $profileData): ?array
    {
        $entries = $this->extractAddressInfoEntries($profileData);
        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $address = is_array($entry['address'] ?? null) ? $entry['address'] : $entry;
            $lines = $address['addressLine'] ?? $address['addressLines'] ?? $address['lines'] ?? null;
            if (is_string($lines)) {
                $lines = [$lines];
            }
            if (! is_array($lines)) {
                $lines = [];
            }

            $lines = array_values(array_filter(array_merge(
                $lines,
                array_filter([
                    $address['line1'] ?? null,
                    $address['line2'] ?? null,
                    $address['line3'] ?? null,
                    $address['line4'] ?? null,
                ], fn ($value) => is_string($value) && trim($value) !== '')
            ), fn ($value) => is_string($value) && trim($value) !== ''));

            $city = $address['cityName'] ?? $address['city'] ?? null;
            $state = $address['state'] ?? $address['stateName'] ?? null;
            $postCode = $address['postalCode'] ?? $address['postCode'] ?? $address['zipCode'] ?? null;
            $country = $address['country']['value'] ?? $address['country']['code'] ?? $address['countryCode'] ?? $address['country'] ?? null;

            if ($lines === [] && ! $city && ! $state && ! $postCode && ! $country) {
                continue;
            }

            $arAddress = [
                'address' => [
                    'addressLine' => $lines,
                    'cityName' => is_string($city) ? $city : '',
                    'state' => is_string($state) ? $state : '',
                    'postalCode' => is_string($postCode) ? $postCode : '',
                    'country' => [
                        'value' => is_string($country) ? $country : '',
                    ],
                    'language' => 'E',
                    'type' => 'AR ADDRESS',
                    'primaryInd' => true,
                    'updateReservations' => false,
                ],
            ];

            return [
                'profileDetails' => [
                    'addresses' => [
                        'addressInfo' => [
                            $arAddress,
                        ],
                    ],
                ],
            ];
        }

        Log::channel('integration')->warning('Opera profile address fallback found no reusable address details', [
            'top_level_keys' => array_keys($profileData),
        ]);

        return null;
    }

    protected function resolvePath(string $path, ?string $hotelId = null): string
    {
        $hotelId = $hotelId ?? ($this->propertyIds[0] ?? '');
        return str_replace('{hotelId}', $hotelId, $path);
    }

    protected function getByDotPath(array $data, string $field): mixed
    {
        if (! str_contains($field, '.')) {
            return $data[$field] ?? null;
        }

        $cursor = $data;
        foreach (explode('.', $field) as $part) {
            if (! is_array($cursor) || ! array_key_exists($part, $cursor)) {
                return null;
            }
            $cursor = $cursor[$part];
        }

        return $cursor;
    }

    protected function extractHotelIdFromPayload(array $payload): ?string
    {
        $hotelId = $payload['criteria']['hotelId'] ?? $payload['accountDetails']['hotelId'] ?? $payload['hotelId'] ?? null;
        return is_string($hotelId) && $hotelId !== '' ? $hotelId : null;
    }

    protected function buildHotelHeaders(?string $hotelId): array
    {
        if ($hotelId === null || $hotelId === '') {
            return [];
        }
        return ['x-hotelid' => $hotelId];
    }

    public function findArAccountByAccountNo(string $accountNo, ?string $hotelId = null): ?array
    {
        $url = $this->gatewayUrl . '/ars/v1/accounts';
        $query = [
            'accountNo' => $accountNo,
        ];
        if ($hotelId) {
            $query['hotelIds'] = $hotelId;
        }
        $response = $this->request('GET', $url, ['query' => $query]);
        if (! $response->successful()) {
            return null;
        }
        $data = $response->json();
        if (! is_array($data)) {
            return null;
        }
        $accounts = $data['accountsDetails'] ?? [];
        if (! is_array($accounts) || $accounts === []) {
            return null;
        }
        $bestMatch = null;
        $fallbackMatch = null;
        foreach ($accounts as $account) {
            if (! is_array($account)) {
                continue;
            }
            $accountNoValue = $account['accountNo'] ?? $account['accountNumber'] ?? null;
            if ($accountNoValue !== null && (string) $accountNoValue !== (string) $accountNo) {
                continue;
            }
            $accountHotel = $account['hotelId'] ?? null;

            $accountId = $account['accountId']['id'] ?? $account['accountId'] ?? null;
            $profileId = $account['profileId']['id'] ?? $account['profileId'] ?? null;
            $addressId = $account['address']['id'] ?? $account['addressId'] ?? null;

            $candidate = [
                'account_id' => $accountId !== null && $accountId !== '' ? (string) $accountId : null,
                'account_no' => $accountNoValue !== null && $accountNoValue !== '' ? (string) $accountNoValue : null,
                'profile_id' => $profileId !== null && $profileId !== '' ? (string) $profileId : null,
                'ar_address_id' => $addressId !== null && $addressId !== '' ? (string) $addressId : null,
                'hotel_id' => $accountHotel !== null && $accountHotel !== '' ? (string) $accountHotel : null,
                'raw' => $account,
            ];

            if ($hotelId && $accountHotel !== null && (string) $accountHotel === (string) $hotelId) {
                $bestMatch = $candidate;
                break;
            }

            if ($fallbackMatch === null) {
                $fallbackMatch = $candidate;
            }
        }

        if ($bestMatch !== null) {
            return $bestMatch;
        }
        if ($fallbackMatch !== null) {
            return $fallbackMatch;
        }
        return null;
    }

    public function findArAccountIdByAccountNo(string $accountNo, ?string $hotelId = null): ?string
    {
        $account = $this->findArAccountByAccountNo($accountNo, $hotelId);
        return is_array($account) ? ($account['account_id'] ?? null) : null;
    }

    /**
     * Resolve Opera profileId using a local mapping file (CSV or JSON).
     * Intended for testing when external lookup is not available.
     */
    protected function resolveProfileIdFromMapping(string $erpNumber): ?string
    {
        $map = $this->loadProfileIdMap();
        if ($map === []) {
            return null;
        }
        return $map[$erpNumber] ?? null;
    }

    /**
     * @return array<string, string>
     */
    protected function loadProfileIdMap(): array
    {
        if ($this->profileIdMap !== null) {
            return $this->profileIdMap;
        }

        $path = (string) config('opera.profile_map_path', '');
        if ($path === '') {
            $path = storage_path('app/data_sources/opera_profile_map.csv');
        } elseif (! str_contains($path, ':') && ! str_starts_with($path, '/') && ! str_starts_with($path, '\\')) {
            $path = storage_path($path);
        }

        if (! is_file($path) || ! is_readable($path)) {
            $this->profileIdMap = [];
            return $this->profileIdMap;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $map = [];
        if ($ext === 'json') {
            $raw = @file_get_contents($path);
            $data = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($data)) {
                foreach ($data as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $erp = $row['ERPID'] ?? $row['erp_number'] ?? $row['erpNumber'] ?? null;
                    $profileId = $row['companyId'] ?? $row['profileId'] ?? $row['company_id'] ?? null;
                    if (is_string($erp) && is_string($profileId) && $erp !== '' && $profileId !== '') {
                        if (isset($map[$erp])) {
                            Log::channel('integration')->warning('Opera profile map duplicate ERPID (json)', [
                                'erp_number' => $erp,
                                'existing_profile_id' => $map[$erp],
                                'new_profile_id' => $profileId,
                            ]);
                            continue;
                        }
                        $map[$erp] = $profileId;
                    }
                }
            }
        } else {
            $handle = fopen($path, 'r');
            if ($handle !== false) {
                $header = fgetcsv($handle);
                if (is_array($header)) {
                    $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
                    $erpIdx = array_search('erpid', $header, true);
                    if ($erpIdx === false) {
                        $erpIdx = array_search('erp_number', $header, true);
                    }
                    $idIdx = array_search('companyid', $header, true);
                    if ($idIdx === false) {
                        $idIdx = array_search('profileid', $header, true);
                    }
                    if ($erpIdx !== false && $idIdx !== false) {
                        while (($row = fgetcsv($handle)) !== false) {
                            $erp = trim((string) ($row[$erpIdx] ?? ''));
                            $profileId = trim((string) ($row[$idIdx] ?? ''));
                            if ($erp !== '' && $profileId !== '') {
                                if (isset($map[$erp])) {
                                    Log::channel('integration')->warning('Opera profile map duplicate ERPID (csv)', [
                                        'erp_number' => $erp,
                                        'existing_profile_id' => $map[$erp],
                                        'new_profile_id' => $profileId,
                                    ]);
                                    continue;
                                }
                                $map[$erp] = $profileId;
                            }
                        }
                    }
                }
                fclose($handle);
            }
        }

        $this->profileIdMap = $map;
        return $this->profileIdMap;
    }
}
