<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionSetting extends Model
{
    protected $fillable = [
        'settings',
        'effective_from',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'effective_from' => 'date',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
