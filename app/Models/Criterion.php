<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Criterion extends Model
{
    protected $table = 'criteria';

    protected $fillable = [
        'code',
        'name',
        'type',
        'description',
        'source_columns',
        'weight',
        'rubric',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'source_columns' => 'array',
            'rubric' => 'array',
            'weight' => 'float',
            'sort_order' => 'integer',
        ];
    }

    public function rowComparisons(): HasMany
    {
        return $this->hasMany(AhpComparison::class, 'criterion_row_id');
    }

    public function colComparisons(): HasMany
    {
        return $this->hasMany(AhpComparison::class, 'criterion_col_id');
    }
}
