<?php

use App\Http\Controllers\LeadWebController;
use App\Http\Controllers\RoleWebController;
use App\Http\Controllers\UserWebController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

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
});

require __DIR__.'/settings.php';
