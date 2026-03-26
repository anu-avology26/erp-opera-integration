<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Mapping\ErpMappingConfig;
use Illuminate\Http\Request;

class MappingController extends Controller
{
    public function edit()
    {
        $config = app(ErpMappingConfig::class);
        $fields = $config->customerFieldKeys();
        $meta = $config->customMeta();

        $fixedKeys = [
            'erp_number',
            'ar_number',
            'name',
            'active',
            'blocked',
            'account_type',
            'address_1',
            'address_2',
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
            'restricted_reason',
        ];

        $customRows = [];
        foreach ($fields as $key => $list) {
            if (in_array($key, $fixedKeys, true)) {
                continue;
            }
            $customRows[] = [
                'key' => $key,
                'erp_fields' => implode(', ', $list),
                'ohip' => $meta[$key]['ohip'] ?? $key,
                'note' => $meta[$key]['note'] ?? '',
            ];
        }

        return view('admin.mapping', ['fields' => $fields, 'customRows' => $customRows]);
    }

    public function update(Request $request)
    {
        $inputs = $request->all();
        $fieldKeys = [
            'erp_number',
            'ar_number',
            'name',
            'active',
            'blocked',
            'account_type',
            'address_1',
            'address_2',
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
            'restricted_reason',
        ];

        $updated = [];
        foreach ($fieldKeys as $key) {
            $raw = $inputs[$key] ?? '';
            $list = array_values(array_filter(array_map('trim', explode(',', (string) $raw)), fn ($v) => $v !== ''));
            $updated[$key] = $list;
        }

        $customErp = $inputs['custom_erp'] ?? [];
        $customOhip = $inputs['custom_ohip'] ?? [];
        $customNote = $inputs['custom_note'] ?? [];
        $customMeta = [];
        if (is_array($customErp) && is_array($customOhip)) {
            foreach ($customOhip as $idx => $ohip) {
                $key = trim((string) $ohip);
                $raw = $customErp[$idx] ?? '';
                $note = is_array($customNote) ? (string) ($customNote[$idx] ?? '') : '';
                $list = array_values(array_filter(array_map('trim', explode(',', (string) $raw)), fn ($v) => $v !== ''));
                if ($key === '' || $list === [] || in_array($key, $fieldKeys, true)) {
                    continue;
                }
                $updated[$key] = $list;
                $customMeta[$key] = [
                    'ohip' => $key,
                    'note' => trim($note),
                ];
            }
        }

        app(ErpMappingConfig::class)->saveCustomerFieldKeys($updated);
        app(ErpMappingConfig::class)->saveCustomMeta($customMeta);

        return back()->with('message', 'Field mapping saved.');
    }
}
