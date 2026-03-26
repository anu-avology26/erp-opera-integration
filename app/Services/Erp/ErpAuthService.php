<?php

namespace App\Services\Erp;

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
            tenantId: config('erp.tenant_id', ''),
            tokenUrl: config('erp.token_url', ''),
            clientId: config('erp.client_id', ''),
            clientSecret: config('erp.client_secret', ''),
            scope: config('erp.scope', 'https://api.businesscentral.dynamics.com/.default'),
            tokenCacheTtl: config('erp.token_cache_ttl', 3300)
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
