<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GstSetting extends Model
{
    protected $fillable = [
        'rate_percent',
        'effective_from',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'rate_percent' => 'decimal:2',
            'effective_from' => 'date',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
