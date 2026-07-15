<?php

use App\Http\Controllers\LeadWebController;
use App\Http\Controllers\PartnerOnboardingWebController;
use App\Http\Controllers\RoleWebController;
use App\Http\Controllers\SubscriptionWebController;
use App\Http\Controllers\UserWebController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::get('partner-onboarding/{token}', [PartnerOnboardingWebController::class, 'show'])
    ->name('partner-onboarding.show');
Route::post('partner-onboarding/{token}', [PartnerOnboardingWebController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('partner-onboarding.store');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [LeadWebController::class, 'dashboard'])->name('dashboard');

    Route::get('leads', [LeadWebController::class, 'index'])->name('leads.index');
    Route::get('leads/create', [LeadWebController::class, 'create'])->name('leads.create');
    Route::post('leads', [LeadWebController::class, 'store'])->name('leads.store');
    Route::get('leads/{lead}', [LeadWebController::class, 'show'])->name('leads.show');
    Route::patch('leads/{lead}', [LeadWebController::class, 'update'])->name('leads.update');
    Route::delete('leads/{lead}', [LeadWebController::class, 'destroy'])->name('leads.destroy');
    Route::post('leads/{lead}/notes', [LeadWebController::class, 'storeNote'])->name('leads.notes.store');
    Route::post('leads/{lead}/invite', [LeadWebController::class, 'invite'])->name('leads.invite');

    Route::get('users', [UserWebController::class, 'index'])->name('users.index');
    Route::get('users/create', [UserWebController::class, 'create'])->name('users.create');
    Route::post('users', [UserWebController::class, 'store'])->name('users.store');
    Route::get('users/{user}/edit', [UserWebController::class, 'edit'])->name('users.edit');
    Route::patch('users/{user}', [UserWebController::class, 'update'])->name('users.update');
    Route::delete('users/{user}', [UserWebController::class, 'destroy'])->name('users.destroy');

    Route::get('roles', [RoleWebController::class, 'index'])->name('roles.index');
    Route::patch('roles/{role}', [RoleWebController::class, 'update'])->name('roles.update');

    Route::get('pricing-calculator', [SubscriptionWebController::class, 'calculator'])
        ->name('pricing-calculator');
    Route::post('pricing-calculator/quote', [SubscriptionWebController::class, 'calculatorQuote'])
        ->name('pricing-calculator.quote');

    Route::prefix('subscriptions')->name('subscriptions.')->group(function () {
        Route::get('pricing', [SubscriptionWebController::class, 'pricing'])->name('pricing');
        Route::patch('pricing/{product}', [SubscriptionWebController::class, 'updatePricing'])->name('pricing.update');
        Route::get('settings', [SubscriptionWebController::class, 'settings'])->name('settings');
        Route::patch('settings', [SubscriptionWebController::class, 'updateSettings'])->name('settings.update');
        Route::get('promo-codes', [SubscriptionWebController::class, 'promoCodes'])->name('promo-codes');
        Route::get('promo-codes/create', [SubscriptionWebController::class, 'createPromoCode'])->name('promo-codes.create');
        Route::post('promo-codes', [SubscriptionWebController::class, 'storePromoCode'])->name('promo-codes.store');
        Route::get('promo-codes/{promoCode}/edit', [SubscriptionWebController::class, 'editPromoCode'])->name('promo-codes.edit');
        Route::patch('promo-codes/{promoCode}', [SubscriptionWebController::class, 'updatePromoCode'])->name('promo-codes.update');
        Route::get('companies', [SubscriptionWebController::class, 'companies'])->name('companies');
        Route::get('companies/{product}/{id}', [SubscriptionWebController::class, 'companyShow'])->name('companies.show');
        Route::patch('companies/{product}/{id}', [SubscriptionWebController::class, 'companyUpdate'])->name('companies.update');
        Route::post('companies/{product}/{id}/quote', [SubscriptionWebController::class, 'companyQuote'])->name('companies.quote');
        Route::post('companies/{product}/{id}/offline-payment', [SubscriptionWebController::class, 'companyOfflinePayment'])->name('companies.offline-payment');
    });
});

require __DIR__.'/settings.php';
