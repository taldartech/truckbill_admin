<?php

namespace App\Models;

use App\Models\Concerns\SoftDeletesOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $token_hash
 * @property Carbon $expires_at
 * @property Carbon|null $completed_at
 * @property int|null $created_by
 */
class PartnerOnboardingInvitation extends Model
{
    use SoftDeletesOnly;

    protected $fillable = [
        'user_id',
        'token_hash',
        'expires_at',
        'completed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return $this->completed_at === null && $this->expires_at->isFuture();
    }
}
