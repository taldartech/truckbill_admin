<?php

namespace App\Models;

use App\Models\Concerns\SoftDeletesOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoCode extends Model
{
    use SoftDeletesOnly;

    protected $fillable = [
        'code',
        'discount_type',
        'discount_value',
        'applicable_products',
        'start_date',
        'end_date',
        'max_uses',
        'uses_count',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'applicable_products' => 'array',
            'start_date' => 'date',
            'end_date' => 'date',
            'discount_value' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function appliesToProduct(string $product): bool
    {
        return in_array($product, $this->applicable_products ?? [], true);
    }
}
