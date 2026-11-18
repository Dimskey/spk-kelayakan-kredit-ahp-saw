<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Customer extends Model
{
    protected $fillable = [
        'customer_code',
        'customer_name',
        'type',
        'company',
        'business_entity',
        'business_entity_name',
        'npwp',
        'nib',
        'sppkp',
        'sppkp_date',
        'img_sppkp',
        'img_npwp',
        'pic',
        'nik',
        'img_ktp',
        'customer_type',
        'delivery_address',
        'billing_address',
        'delivery_street',
        'billing_street',
        'delivery_phone',
        'billing_phone',
        'email',
        'delivery_email',
        'billing_email',
        'current_payment_term',
        'status',
        'raw_payload',
        'import_batch_id',
    ];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
        ];
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(CustomerAssessment::class);
    }

    public function activeAssessment(): HasOne
    {
        return $this->hasOne(CustomerAssessment::class)
            ->whereHas('ahpSession', fn ($query) => $query->where('is_active', true));
    }
}
