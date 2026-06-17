<?php

use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\InvitationController;
use App\Http\Controllers\Api\Admin\LeadController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\PublicLeadController;
use App\Http\Controllers\Api\PublicOnboardingController;
use App\Http\Middleware\HandleCors;
use App\Http\Middleware\VerifyWebsiteApiKey;
use Illuminate\Support\Facades\Route;

Route::middleware([HandleCors::class])->group(function (): void {
    Route::prefix('v1/public')->group(function (): void {
        Route::post('leads', [PublicLeadController::class, 'store'])
            ->middleware(['throttle:20,1', VerifyWebsiteApiKey::class]);

        Route::prefix('onboarding/{token}')->group(function (): void {
            Route::get('/', [PublicOnboardingController::class, 'show']);
            Route::post('otp/send', [PublicOnboardingController::class, 'sendOtp'])->middleware('throttle:5,1');
            Route::post('otp/verify', [PublicOnboardingController::class, 'verifyOtp'])->middleware('throttle:10,1');
            Route::post('policy/accept', [PublicOnboardingController::class, 'acceptPolicy'])->middleware('throttle:10,1');
            Route::post('password', [PublicOnboardingController::class, 'setPassword'])->middleware('throttle:10,1');
            Route::post('complete', [PublicOnboardingController::class, 'complete'])->middleware('throttle:10,1');
        });
    });

    Route::prefix('v1/admin')->middleware(['auth:sanctum'])->group(function (): void {
        Route::get('dashboards/crm', [DashboardController::class, 'crm']);

        Route::get('leads', [LeadController::class, 'index']);
        Route::post('leads', [LeadController::class, 'store']);
        Route::get('leads/{lead}', [LeadController::class, 'show']);
        Route::put('leads/{lead}', [LeadController::class, 'update']);
        Route::post('leads/{lead}/notes', [LeadController::class, 'addNote']);
        Route::get('leads/{lead}/activities', [LeadController::class, 'activities']);
        Route::post('leads/{lead}/invite', [InvitationController::class, 'store']);

        Route::get('users', [UserController::class, 'index']);
        Route::post('users', [UserController::class, 'store']);
    });
});
