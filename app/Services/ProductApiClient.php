<?php

namespace App\Services;

use App\Enums\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ProductApiClient
{
    public function get(string $path, Product $product, array $query = []): array
    {
        return $this->request('GET', $path, $product, [], $query);
    }

    public function patch(string $path, Product $product, array $payload = []): array
    {
        return $this->request('PATCH', $path, $product, $payload);
    }

    public function post(string $path, Product $product, array $payload = []): array
    {
        return $this->request('POST', $path, $product, $payload);
    }

    private function request(string $method, string $path, Product $product, array $payload = [], array $query = []): array
    {
        $url = rtrim($product->apiBaseUrl(), '/').'/'.ltrim($path, '/');
        $timestamp = (string) time();
        $body = in_array($method, ['GET', 'HEAD'], true) ? '' : json_encode($payload);
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $product->signingSecret());

        $request = Http::timeout(30)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-Timestamp' => $timestamp,
                'X-Signature' => $signature,
            ]);

        if ($body !== '') {
            $request = $request->withBody($body, 'application/json');
        }

        $response = match ($method) {
            'GET' => $request->get($url, $query),
            'PATCH' => $request->patch($url, $payload),
            'POST' => $request->post($url, $payload),
            default => throw new \InvalidArgumentException("Unsupported method: {$method}"),
        };

        if (! $response->successful()) {
            throw new \RuntimeException('Product API error: '.$response->status().' '.Str::limit($response->body(), 500));
        }

        return $response->json() ?? [];
    }
}
