<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AhpComparison extends Model
{
    protected $fillable = [
        'ahp_session_id',
        'criterion_row_id',
        'criterion_col_id',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'float',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AhpSession::class, 'ahp_session_id');
    }

    public function rowCriterion(): BelongsTo
    {
        return $this->belongsTo(Criterion::class, 'criterion_row_id');
    }

    public function colCriterion(): BelongsTo
    {
        return $this->belongsTo(Criterion::class, 'criterion_col_id');
    }
}
