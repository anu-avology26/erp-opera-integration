<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayloadAudit extends Model
{
    protected $table = 'payload_audit';

    protected $fillable = [
        'direction',
        'entity_ref',
        'status',
        'response_code',
        'payload_encrypted',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'response_code' => 'integer',
    ];
}
