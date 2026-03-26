<?php

namespace App\Services\Mapping;

use Illuminate\Support\Facades\File;

class ErpMappingConfig
{
    protected string $storagePath;
    protected string $metaPath;

    public function __construct()
    {
        $this->storagePath = storage_path('app/mappings/erp_customer.json');
        $this->metaPath = storage_path('app/mappings/erp_customer_meta.json');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function customerFieldKeys(): array
    {
        $defaults = config('mapping.erp_customer', []);
        $overrides = $this->loadOverrides();

        return $this->mergeFieldKeys($defaults, $overrides);
    }

    /**
     * @param array<string, array<int, string>> $fields
     */
    public function saveCustomerFieldKeys(array $fields): void
    {
        $dir = dirname($this->storagePath);
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        File::put($this->storagePath, json_encode($fields, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array<string, array{ohip?: string, note?: string}>
     */
    public function customMeta(): array
    {
        if (! File::exists($this->metaPath)) {
            return [];
        }

        $raw = File::get($this->metaPath);
        $json = json_decode($raw, true);
        return is_array($json) ? $json : [];
    }

    /**
     * @param array<string, array{ohip?: string, note?: string}> $meta
     */
    public function saveCustomMeta(array $meta): void
    {
        $dir = dirname($this->metaPath);
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        File::put($this->metaPath, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function loadOverrides(): array
    {
        if (! File::exists($this->storagePath)) {
            return [];
        }

        $raw = File::get($this->storagePath);
        $json = json_decode($raw, true);
        if (! is_array($json)) {
            return [];
        }

        $result = [];
        foreach ($json as $key => $value) {
            if (! is_array($value)) {
                continue;
            }
            $result[$key] = array_values(array_filter(array_map('trim', $value), fn ($v) => $v !== ''));
        }

        return $result;
    }

    /**
     * @param array<string, array<int, string>> $defaults
     * @param array<string, array<int, string>> $overrides
     * @return array<string, array<int, string>>
     */
    protected function mergeFieldKeys(array $defaults, array $overrides): array
    {
        $merged = $defaults;
        foreach ($overrides as $key => $list) {
            if ($list === []) {
                continue;
            }
            $merged[$key] = $list;
        }
        return $merged;
    }
}
