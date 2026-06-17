<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PolicyVersion extends Model
{
    protected $fillable = [
        'version',
        'effective_at',
        'documents',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'effective_at' => 'datetime',
            'documents' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public static function active(): ?self
    {
        return static::query()->where('is_active', true)->latest('effective_at')->first();
    }
}
