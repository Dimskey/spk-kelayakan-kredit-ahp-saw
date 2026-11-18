<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAssessment extends Model
{
    protected $fillable = [
        'customer_id',
        'ahp_session_id',
        'c1_score',
        'c2_score',
        'c3_score',
        'c4_score',
        'c5_score',
        'evaluation_trace',
        'r1',
        'r2',
        'r3',
        'r4',
        'r5',
        'w1_r1',
        'w2_r2',
        'w3_r3',
        'w4_r4',
        'w5_r5',
        'preference_score',
        'rank',
        'recommended_top',
    ];

    protected function casts(): array
    {
        return [
            'c1_score' => 'integer',
            'c2_score' => 'integer',
            'c3_score' => 'integer',
            'c4_score' => 'integer',
            'c5_score' => 'integer',
            'evaluation_trace' => 'array',
            'r1' => 'float',
            'r2' => 'float',
            'r3' => 'float',
            'r4' => 'float',
            'r5' => 'float',
            'w1_r1' => 'float',
            'w2_r2' => 'float',
            'w3_r3' => 'float',
            'w4_r4' => 'float',
            'w5_r5' => 'float',
            'preference_score' => 'float',
            'rank' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function ahpSession(): BelongsTo
    {
        return $this->belongsTo(AhpSession::class);
    }
}
