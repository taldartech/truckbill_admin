<?php

namespace App\Services;

use App\Enums\Product;
use App\Jobs\RetryProvisioningJob;
use App\Models\OnboardingInvitation;
use App\Models\OnboardingSession;
use App\Models\ProvisioningLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProvisioningOrchestrator
{
    public function provision(OnboardingInvitation $invitation, string $password, OnboardingSession $session): array
    {
        $product = Product::from($invitation->product->value ?? (string) $invitation->product);
        $idempotencyKey = 'provision-'.$invitation->id;

        $log = ProvisioningLog::firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'onboarding_invitation_id' => $invitation->id,
                'product' => $product->value,
                'status' => 'pending',
            ]
        );

        if ($log->status === 'success') {
            return (array) ($log->response_payload ?? ['status' => 'already_provisioned']);
        }

        $payload = [
            'idempotency_key' => $idempotencyKey,
            'name' => $invitation->lead?->name ?? $invitation->company_name ?? 'User',
            'email' => $invitation->email,
            'mobile' => $invitation->mobile,
            'password' => $password,
            'email_verified_at' => $session->email_verified_at?->toIso8601String(),
            'policy_accepted_at' => $session->policy_accepted_at?->toIso8601String(),
            'role' => 'super_admin',
        ];

        $log->update([
            'request_payload' => $payload,
            'attempts' => $log->attempts + 1,
        ]);

        try {
            $response = $this->callProductApi($product, $payload);
            $log->update([
                'status' => 'success',
                'response_payload' => $response,
            ]);

            return $response;
        } catch (\Throwable $exception) {
            $log->update([
                'status' => 'failed',
                'error_message' => $this->formatErrorMessage($exception->getMessage()),
            ]);

            RetryProvisioningJob::dispatch($log->id)->delay(now()->addMinutes(5));

            throw ValidationException::withMessages([
                'provisioning' => 'User provisioning failed. Our team has been notified.',
            ]);
        }
    }

    public function retry(ProvisioningLog $log): array
    {
        $invitation = $log->invitation()->with('session')->firstOrFail();
        $session = $invitation->session;

        if ($session === null || $session->password_hash === null) {
            throw new \RuntimeException('Cannot retry provisioning without session password.');
        }

        $product = Product::from($log->product);
        $payload = (array) $log->request_payload;

        $response = $this->callProductApi($product, $payload);
        $log->update([
            'status' => 'success',
            'response_payload' => $response,
            'error_message' => null,
        ]);

        return $response;
    }

    private function callProductApi(Product $product, array $payload): array
    {
        $url = rtrim($product->apiBaseUrl(), '/').'/api/onboarding/create-user';
        $timestamp = (string) time();
        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $product->signingSecret());

        $response = Http::timeout(30)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-Timestamp' => $timestamp,
                'X-Signature' => $signature,
                'Idempotency-Key' => $payload['idempotency_key'] ?? Str::uuid()->toString(),
            ])
            ->withBody($body, 'application/json')
            ->post($url);

        if (! $response->successful()) {
            throw new \RuntimeException('Product API error: '.$response->status().' '.$this->summarizeResponseBody($response->body()));
        }

        return $response->json() ?? ['status' => 'ok'];
    }

    private function summarizeResponseBody(string $body): string
    {
        $trimmed = ltrim($body);

        if (str_starts_with($trimmed, '<!DOCTYPE') || str_starts_with($trimmed, '<html')) {
            return 'HTML error page returned by product API';
        }

        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            if (isset($decoded['message']) && is_string($decoded['message'])) {
                return $decoded['message'];
            }

            if (isset($decoded['errors']) && is_array($decoded['errors'])) {
                return collect($decoded['errors'])
                    ->flatten()
                    ->filter(fn ($message) => is_string($message))
                    ->implode(' ');
            }
        }

        return Str::limit($trimmed, 500);
    }

    private function formatErrorMessage(string $message): string
    {
        return Str::limit($message, 65000);
    }
}
