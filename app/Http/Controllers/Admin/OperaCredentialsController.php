<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IntegrationSetting;
use App\Services\Opera\OperaConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class OperaCredentialsController extends Controller
{
    public function edit(): View
    {
        $fields = [
            'gateway_url' => 'Gateway URL',
            'client_id' => 'Client ID',
            'client_secret' => 'Client Secret',
            'scope' => 'OAuth Scope',
            'app_key' => 'App Key',
            'enterprise_id' => 'Enterprise ID',
            'property_ids' => 'Hotel IDs (comma-separated)',
            'token_cache_ttl' => 'Token cache TTL (seconds)',
        ];

        $overrides = [];
        foreach (array_keys($fields) as $key) {
            $overrides[$key] = IntegrationSetting::getValue('opera_' . $key);
        }

        $effective = [
            'gateway_url' => OperaConfig::getString('gateway_url', (string) config('opera.gateway_url', '')),
            'client_id' => OperaConfig::getString('client_id', (string) config('opera.client_id', '')),
            'client_secret' => OperaConfig::getString('client_secret', (string) config('opera.client_secret', '')),
            'scope' => OperaConfig::getString('scope', (string) config('opera.scope', '')),
            'app_key' => OperaConfig::getString('app_key', (string) config('opera.app_key', '')),
            'enterprise_id' => OperaConfig::getString('enterprise_id', (string) config('opera.enterprise_id', '')),
            'property_ids' => implode(',', OperaConfig::getPropertyIds(config('opera.property_ids', []))),
            'token_cache_ttl' => (string) OperaConfig::getInt('token_cache_ttl', (int) config('opera.token_cache_ttl', 3300)),
        ];

        return view('admin.opera-credentials', [
            'fields' => $fields,
            'overrides' => $overrides,
            'effective' => $effective,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $fields = [
            'gateway_url',
            'client_id',
            'client_secret',
            'scope',
            'app_key',
            'enterprise_id',
            'property_ids',
            'token_cache_ttl',
        ];

        $data = $request->only($fields);
        foreach ($fields as $key) {
            $value = $data[$key] ?? null;
            if (is_string($value)) {
                $value = trim($value);
            }
            if ($key === 'client_secret' && ($value === null || $value === '')) {
                continue;
            }
            IntegrationSetting::setValue('opera_' . $key, $value);
        }

        Cache::forget('opera_oauth_token');

        return back()->with('message', 'Opera credentials saved. Overrides apply immediately.');
    }
}
