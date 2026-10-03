<?php

use App\Domain\Access\Permission;
use App\Http\Controllers\Settings\DeductionTypeController;
use App\Http\Controllers\Settings\LoanProductController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\SystemSettingsController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');

    Route::middleware('permission:'.Permission::SETTINGS_MANAGE)->group(function () {
        Route::get('settings/system', [SystemSettingsController::class, 'edit'])->name('system-settings.edit');
        Route::put('settings/system', [SystemSettingsController::class, 'update'])->name('system-settings.update');

        Route::get('settings/loan-products', [LoanProductController::class, 'index'])->name('loan-products.index');
        Route::post('settings/loan-products', [LoanProductController::class, 'store'])->name('loan-products.store');
        Route::patch('settings/loan-products/{loanProduct}', [LoanProductController::class, 'update'])->name('loan-products.update');

        Route::get('settings/deduction-types', [DeductionTypeController::class, 'index'])->name('deduction-types.index');
        Route::post('settings/deduction-types', [DeductionTypeController::class, 'store'])->name('deduction-types.store');
        Route::patch('settings/deduction-types/{deductionType}', [DeductionTypeController::class, 'update'])->name('deduction-types.update');
    });
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
