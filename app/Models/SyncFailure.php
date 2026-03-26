<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncFailure extends Model
{
    protected $table = 'sync_failures';

    protected $fillable = [
        'sync_log_id',
        'erp_number',
        'opera_account_number',
        'error_message',
        'response_code',
        'retried_at',
    ];

    protected $casts = [
        'retried_at' => 'datetime',
        'response_code' => 'integer',
    ];

    public function syncLog(): BelongsTo
    {
        return $this->belongsTo(SyncLog::class, 'sync_log_id');
    }
}
