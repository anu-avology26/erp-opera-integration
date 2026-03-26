<?php

namespace App\Services\Erp;

use App\Services\Erp\ErpConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ErpAuthService
{
    public function __construct(
        protected string $tenantId,
        protected string $tokenUrl,
        protected string $clientId,
        protected string $clientSecret,
        protected string $scope,
        protected int $tokenCacheTtl = 3300
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            tenantId: (string) ErpConfig::getString('tenant_id', (string) config('erp.tenant_id', '')),
            tokenUrl: (string) ErpConfig::getString('token_url', (string) config('erp.token_url', '')),
            clientId: (string) ErpConfig::getString('client_id', (string) config('erp.client_id', '')),
            clientSecret: (string) ErpConfig::getString('client_secret', (string) config('erp.client_secret', '')),
            scope: (string) ErpConfig::getString('scope', (string) config('erp.scope', 'https://api.businesscentral.dynamics.com/.default')),
            tokenCacheTtl: ErpConfig::getInt('token_cache_ttl', (int) config('erp.token_cache_ttl', 3300))
        );
    }

    public function getAccessToken(): string
    {
        $cacheKey = 'erp_oauth_token';

        return Cache::remember($cacheKey, $this->tokenCacheTtl, function () {
            $url = trim($this->tokenUrl) !== ''
                ? trim($this->tokenUrl)
                : "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token";

            $response = Http::asForm()->post($url, [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => $this->scope,
            ]);

            if (! $response->successful()) {
                Log::channel('integration')->error('ERP OAuth2 token request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \RuntimeException('ERP OAuth2 token request failed: ' . $response->body());
            }

            $data = $response->json();
            $token = $data['access_token'] ?? null;

            if (empty($token)) {
                throw new \RuntimeException('ERP OAuth2 response missing access_token');
            }

            return $token;
        });
    }

    public function clearTokenCache(): void
    {
        Cache::forget('erp_oauth_token');
    }
}
