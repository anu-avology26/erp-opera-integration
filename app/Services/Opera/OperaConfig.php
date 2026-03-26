<?php

namespace App\Services\Opera;

use App\Models\IntegrationSetting;

class OperaConfig
{
    protected static function getOverride(string $key): ?string
    {
        return IntegrationSetting::getValue('opera_' . $key);
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

    /**
     * Return property IDs (hotel IDs) as array.
     */
    public static function getPropertyIds(array $fallback = []): array
    {
        $override = self::getOverride('property_ids');
        if (is_string($override) && trim($override) !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $override))));
        }
        return $fallback;
    }
}
