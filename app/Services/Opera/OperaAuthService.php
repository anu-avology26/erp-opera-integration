<?php

namespace App\Services\Opera;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OperaAuthService
{
    /**
     * Constructor for OperaAuthService.
     *
     * @param string $gatewayUrl The base URL for the Opera API gateway.
     * @param string $clientId The OAuth client ID for authentication.
     * @param string $clientSecret The OAuth client secret for authentication.
     * @param string $scope The OAuth scope for the token request.
     * @param string $appKey The application key for API access.
     * @param string $enterpriseId The enterprise ID for API access.
     * @param int $tokenCacheTtl Time in seconds to cache the token (default: 3300 seconds).
     */
    public function __construct(
        protected string $gatewayUrl,
        protected string $clientId,
        protected string $clientSecret,
        protected string $scope,
        protected string $appKey,
        protected string $enterpriseId,
        protected int $tokenCacheTtl = 3300
    ) {}
    /**
     * Create an instance of OperaAuthService using configuration values.
     */
    public static function fromConfig(): self
    {
        $gatewayUrl = OperaConfig::getString('gateway_url', rtrim((string) config('opera.gateway_url', ''), '/'));
        $clientId = OperaConfig::getString('client_id', (string) config('opera.client_id', ''));
        $clientSecret = OperaConfig::getString('client_secret', (string) config('opera.client_secret', ''));
        $scope = OperaConfig::getString('scope', (string) config('opera.scope', ''));
        $appKey = OperaConfig::getString('app_key', (string) config('opera.app_key', ''));
        $enterpriseId = OperaConfig::getString('enterprise_id', (string) config('opera.enterprise_id', ''));
        $tokenTtl = OperaConfig::getInt('token_cache_ttl', (int) config('opera.token_cache_ttl', 3300));

        return new self(
            gatewayUrl: rtrim($gatewayUrl ?? '', '/'),
            clientId: $clientId ?? '',
            clientSecret: $clientSecret ?? '',
            scope: $scope ?? '',
            appKey: $appKey ?? '',
            enterpriseId: $enterpriseId ?? '',
            tokenCacheTtl: $tokenTtl
        );
    }
    /**
     * Get OAuth access token from Opera. Caches the token until it expires.
     */
    public function getAccessToken(): string
    {
        $cacheKey = 'opera_oauth_token';

        return Cache::remember($cacheKey, $this->tokenCacheTtl, function () {

            $url = $this->gatewayUrl . '/oauth/v1/tokens';

            $payload = [
                'grant_type' => 'client_credentials',
                'scope'      => $this->scope,
            ];

            $response = Http::asForm()
                ->withHeaders([
                    'x-app-key'    => $this->appKey,
                    'enterpriseId' => $this->enterpriseId,
                    'Accept'       => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret),
                ])
                ->post($url, $payload);

            if (! $response->successful()) {
                throw new \RuntimeException(
                    'Opera OAuth2 token request failed: ' . $response->body()
                );
            }

            $data = $response->json();

            if (empty($data['access_token'])) {
                throw new \RuntimeException('Missing access_token in OAuth response');
            }

            return (string) $data['access_token'];
        });
    }
    /**
     * Clear the cached token (for testing or if you want to force refresh)
     */
    public function clearTokenCache(): void
    {
        Cache::forget('opera_oauth_token');
    }
}
