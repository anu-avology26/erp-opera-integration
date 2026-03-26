<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncState extends Model
{
    protected $table = 'sync_state';

    public $timestamps = true;

    protected $fillable = [
        'sync_type',
        'last_sync_at',
    ];

    protected $casts = [
        'last_sync_at' => 'datetime',
    ];

    public static function getLastSyncAt(string $syncType): ?string
    {
        $state = self::where('sync_type', $syncType)->first();

        return $state?->last_sync_at?->toIso8601String();
    }

    public static function setLastSyncAt(string $syncType, ?\DateTimeInterface $at = null): void
    {
        self::updateOrCreate(
            ['sync_type' => $syncType],
            ['last_sync_at' => $at ?? now()]
        );
    }
}
