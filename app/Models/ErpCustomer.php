<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class ErpCustomer extends Model
{
    protected $table = 'erp_customers';

    protected $fillable = [
        'erp_number',
        'status',
        'name',
        'account_type',
        'active',
        'blocked',
        'ar_number',
        'address_1',
        'address_2',
        'vat_registration_no',
        'phone',
        'email',
        'country',
        'post_code',
        'payment_terms_code',
        'property',
        'catalog_code',
        'system_modified_at',
        'payload',
        'has_credit',
        'credit_limit',
        'opera_profile_id',
        'opera_account_number',
        'last_modified_at',
    ];

    protected $casts = [
        'active' => 'boolean',
        'blocked' => 'boolean',
        'has_credit' => 'boolean',
        'credit_limit' => 'decimal:2',
        'system_modified_at' => 'datetime',
        'last_modified_at' => 'datetime',
    ];

    /**
     * Get the decrypted payload (array).
     */
    public function getPayloadAttribute(string $value): array
    {
        if (empty($value)) {
            return [];
        }

        try {
            $decrypted = Crypt::decryptString($value);

            return json_decode($decrypted, true) ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Set the payload (array) as encrypted JSON.
     */
    public function setPayloadAttribute(array|string $value): void
    {
        $this->attributes['payload'] = Crypt::encryptString(
            is_string($value) ? $value : json_encode($value)
        );
    }
}
