<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    protected $table = 'sync_logs';

    protected $fillable = [
        'type',
        'total',
        'success',
        'failed',
        'errors',
    ];

    protected $casts = [
        'total' => 'integer',
        'success' => 'integer',
        'failed' => 'integer',
        'errors' => 'array',
    ];
}
