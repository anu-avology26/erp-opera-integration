<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationSetting extends Model
{
    protected $table = 'integration_settings';

    protected $fillable = [
        'key',
        'value',
    ];

    public static function getValue(string $key): ?string
    {
        return self::where('key', $key)->value('value');
    }

    public static function setValue(string $key, ?string $value): void
    {
        if ($value === null || trim($value) === '') {
            self::where('key', $key)->delete();
            return;
        }

        self::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }
}
