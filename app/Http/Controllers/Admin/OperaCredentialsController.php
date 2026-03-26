<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IntegrationSetting;
use App\Services\Erp\ErpConfig;
use App\Services\Opera\OperaConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class OperaCredentialsController extends Controller
{
    public function edit(): View
    {
        $ohipFields = [
            'gateway_url' => 'Gateway URL',
            'client_id' => 'Client ID',
            'client_secret' => 'Client Secret',
            'scope' => 'OAuth Scope',
            'app_key' => 'App Key',
            'enterprise_id' => 'Enterprise ID',
            'property_ids' => 'Hotel IDs (comma-separated)',
            'token_cache_ttl' => 'Token cache TTL (seconds)',
        ];

        $erpFields = [
            'base_url' => 'Base URL',
            'token_url' => 'Token URL',
            'tenant_id' => 'Tenant ID',
            'client_id' => 'Client ID',
            'client_secret' => 'Client Secret',
            'scope' => 'OAuth Scope',
            'company_id' => 'Company ID',
            'customer_path' => 'Customer Path',
            'token_cache_ttl' => 'Token cache TTL (seconds)',
        ];

        return view('admin.opera-credentials', [
            'ohipFields' => $ohipFields,
            'erpFields' => $erpFields,
            'ohipOverrides' => $this->loadOverrides('opera', array_keys($ohipFields)),
            'erpOverrides' => $this->loadOverrides('erp', array_keys($erpFields)),
            'ohipEffective' => [
                'gateway_url' => OperaConfig::getString('gateway_url', (string) config('opera.gateway_url', '')),
                'client_id' => OperaConfig::getString('client_id', (string) config('opera.client_id', '')),
                'client_secret' => OperaConfig::getString('client_secret', (string) config('opera.client_secret', '')),
                'scope' => OperaConfig::getString('scope', (string) config('opera.scope', '')),
                'app_key' => OperaConfig::getString('app_key', (string) config('opera.app_key', '')),
                'enterprise_id' => OperaConfig::getString('enterprise_id', (string) config('opera.enterprise_id', '')),
                'property_ids' => implode(',', OperaConfig::getPropertyIds(config('opera.property_ids', []))),
                'token_cache_ttl' => (string) OperaConfig::getInt('token_cache_ttl', (int) config('opera.token_cache_ttl', 3300)),
            ],
            'erpEffective' => [
                'base_url' => ErpConfig::getString('base_url', (string) config('erp.base_url', '')),
                'token_url' => ErpConfig::getString('token_url', (string) config('erp.token_url', '')),
                'tenant_id' => ErpConfig::getString('tenant_id', (string) config('erp.tenant_id', '')),
                'client_id' => ErpConfig::getString('client_id', (string) config('erp.client_id', '')),
                'client_secret' => ErpConfig::getString('client_secret', (string) config('erp.client_secret', '')),
                'scope' => ErpConfig::getString('scope', (string) config('erp.scope', 'https://api.businesscentral.dynamics.com/.default')),
                'company_id' => ErpConfig::getString('company_id', (string) config('erp.company_id', '')),
                'customer_path' => ErpConfig::getString('customer_path', (string) config('erp.customer_path', 'customers')),
                'token_cache_ttl' => (string) ErpConfig::getInt('token_cache_ttl', (int) config('erp.token_cache_ttl', 3300)),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $ohipFields = [
            'gateway_url',
            'client_id',
            'client_secret',
            'scope',
            'app_key',
            'enterprise_id',
            'property_ids',
            'token_cache_ttl',
        ];

        $erpFields = [
            'base_url',
            'token_url',
            'tenant_id',
            'client_id',
            'client_secret',
            'scope',
            'company_id',
            'customer_path',
            'token_cache_ttl',
        ];

        $this->storeOverrides((array) $request->input('ohip', []), 'opera', $ohipFields, ['client_secret']);
        $this->storeOverrides((array) $request->input('erp', []), 'erp', $erpFields, ['client_secret']);

        Cache::forget('opera_oauth_token');
        Cache::forget('erp_oauth_token');

        return back()->with('message', 'Environment credentials saved. Overrides apply immediately.');
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<string, string|null>
     */
    protected function loadOverrides(string $prefix, array $keys): array
    {
        $values = [];
        foreach ($keys as $key) {
            $values[$key] = IntegrationSetting::getValue($prefix . '_' . $key);
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $fields
     * @param  array<int, string>  $preserveIfBlank
     */
    protected function storeOverrides(array $data, string $prefix, array $fields, array $preserveIfBlank = []): void
    {
        foreach ($fields as $field) {
            $value = $data[$field] ?? null;
            if (is_string($value)) {
                $value = trim($value);
            }

            if (in_array($field, $preserveIfBlank, true) && ($value === null || $value === '')) {
                continue;
            }

            IntegrationSetting::setValue($prefix . '_' . $field, is_string($value) ? $value : null);
        }
    }
}
