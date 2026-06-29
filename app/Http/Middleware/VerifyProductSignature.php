<?php

namespace App\Http\Middleware;

use App\Enums\Product;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyProductSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $productValue = $request->route('product');

        try {
            $product = $productValue instanceof Product
                ? $productValue
                : Product::from((string) $productValue);
        } catch (\ValueError) {
            return response()->json(['message' => 'Unknown product.'], 404);
        }

        $secret = $product->signingSecret();

        if ($secret === '') {
            return response()->json(['message' => 'Product signing is not configured.'], 503);
        }

        $timestamp = $request->header('X-Timestamp');
        $signature = $request->header('X-Signature');

        if ($timestamp === null || $signature === null) {
            return response()->json(['message' => 'Missing signature headers.'], 401);
        }

        if (! ctype_digit((string) $timestamp)) {
            return response()->json(['message' => 'Invalid timestamp.'], 401);
        }

        if (abs(time() - (int) $timestamp) > 300) {
            return response()->json(['message' => 'Request timestamp expired.'], 401);
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        if (! hash_equals($expected, (string) $signature)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $request->attributes->set('verified_product', $product);

        return $next($request);
    }
}
