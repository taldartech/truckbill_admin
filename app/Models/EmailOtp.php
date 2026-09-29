<?php

namespace App\Models;

use App\Models\Concerns\SoftDeletesOnly;
use Illuminate\Database\Eloquent\Model;

class EmailOtp extends Model
{
    use SoftDeletesOnly;

    protected $fillable = [
        'email',
        'otp_hash',
        'expires_at',
        'verified_at',
        'attempts',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }
}
