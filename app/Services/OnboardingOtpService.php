<?php

namespace App\Services;

use App\Models\EmailOtp;
use App\Notifications\OnboardingOtpNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class OnboardingOtpService
{
    public function send(string $email): void
    {
        $otp = (string) random_int(100000, 999999);
        $ttl = (int) config('onboarding.otp_ttl_minutes', 15);

        EmailOtp::query()
            ->where('email', $email)
            ->whereNull('verified_at')
            ->update(['expires_at' => now()]);

        EmailOtp::create([
            'email' => $email,
            'otp_hash' => $this->hashOtp($email, $otp),
            'expires_at' => now()->addMinutes($ttl),
        ]);

        Notification::route('mail', $email)->notify(new OnboardingOtpNotification($otp, $ttl));
    }

    public function verify(string $email, string $otp): void
    {
        $record = EmailOtp::query()
            ->where('email', $email)
            ->whereNull('verified_at')
            ->orderByDesc('id')
            ->first();

        if ($record === null || now()->greaterThan($record->expires_at)) {
            throw ValidationException::withMessages(['otp' => 'OTP expired or invalid.']);
        }

        $maxAttempts = (int) config('onboarding.otp_max_attempts', 5);
        if ($record->attempts >= $maxAttempts) {
            throw ValidationException::withMessages(['otp' => 'Maximum OTP attempts exceeded.']);
        }

        if (! hash_equals($record->otp_hash, $this->hashOtp($email, $otp))) {
            $record->increment('attempts');
            throw ValidationException::withMessages(['otp' => 'Invalid OTP.']);
        }

        $record->verified_at = now();
        $record->save();
    }

    public function isEmailVerified(string $email): bool
    {
        return EmailOtp::query()
            ->where('email', $email)
            ->whereNotNull('verified_at')
            ->where('expires_at', '>=', now())
            ->exists();
    }

    public function consumeVerified(string $email): void
    {
        EmailOtp::query()
            ->where('email', $email)
            ->whereNotNull('verified_at')
            ->update(['expires_at' => now()]);
    }

    private function hashOtp(string $email, string $otp): string
    {
        return hash_hmac('sha256', $email.'|'.$otp, (string) config('app.key'));
    }
}
