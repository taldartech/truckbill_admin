<?php

namespace App\Models;

use App\Models\Concerns\SoftDeletesOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $legal_name
 * @property string $mobile
 * @property string|null $working_location
 * @property string|null $current_company_name
 * @property string|null $pan_number
 * @property string|null $aadhar_number
 * @property string|null $bank_name
 * @property string|null $bank_account_number
 * @property string|null $ifsc_code
 * @property Carbon|null $onboarding_completed_at
 */
class SalesPartnerProfile extends Model
{
    use SoftDeletesOnly;

    protected $fillable = [
        'user_id',
        'legal_name',
        'mobile',
        'working_location',
        'current_company_name',
        'pan_number',
        'aadhar_number',
        'bank_name',
        'bank_account_number',
        'ifsc_code',
        'onboarding_completed_at',
    ];

    protected function casts(): array
    {
        return [
            'onboarding_completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
