<?php

namespace App\Services\Erp;

use App\Models\IntegrationSetting;

class ErpConfig
{
    protected static function getOverride(string $key): ?string
    {
        return IntegrationSetting::getValue('erp_' . $key);
    }

    public static function getString(string $key, ?string $fallback = null): ?string
    {
        $override = self::getOverride($key);
        if (is_string($override) && trim($override) !== '') {
            return $override;
        }

        return $fallback;
    }

    public static function getInt(string $key, int $fallback = 0): int
    {
        $value = self::getString($key, null);
        if ($value === null || $value === '') {
            return $fallback;
        }

        return (int) $value;
    }
}
