<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AhpSession extends Model
{
    protected $fillable = [
        'name',
        'matrix_size',
        'lambda_max',
        'ci',
        'ri',
        'cr',
        'is_consistent',
        'raw_matrix_snapshot',
        'normalized_matrix_snapshot',
        'weights_snapshot',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'matrix_size' => 'integer',
            'lambda_max' => 'float',
            'ci' => 'float',
            'ri' => 'float',
            'cr' => 'float',
            'is_consistent' => 'boolean',
            'raw_matrix_snapshot' => 'array',
            'normalized_matrix_snapshot' => 'array',
            'weights_snapshot' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function comparisons(): HasMany
    {
        return $this->hasMany(AhpComparison::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(CustomerAssessment::class);
    }
}
