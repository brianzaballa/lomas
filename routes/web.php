<?php

use App\Domain\Access\Permission;
use App\Http\Controllers\Administration\AuditLogController;
use App\Http\Controllers\Administration\RoleController;
use App\Http\Controllers\Administration\UserController;
use App\Http\Controllers\BorrowerController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // The literal "create" path is registered before "{borrower}" so it is never
    // read as a borrower identifier.
    Route::get('borrowers', [BorrowerController::class, 'index'])
        ->middleware('permission:'.Permission::BORROWERS_VIEW)
        ->name('borrowers.index');

    Route::middleware('permission:'.Permission::BORROWERS_CREATE)->group(function () {
        Route::get('borrowers/create', [BorrowerController::class, 'create'])->name('borrowers.create');
        Route::post('borrowers', [BorrowerController::class, 'store'])->name('borrowers.store');
    });

    Route::get('borrowers/{borrower}', [BorrowerController::class, 'show'])
        ->middleware('permission:'.Permission::BORROWERS_VIEW)
        ->name('borrowers.show');

    Route::middleware('permission:'.Permission::BORROWERS_UPDATE)->group(function () {
        Route::get('borrowers/{borrower}/edit', [BorrowerController::class, 'edit'])->name('borrowers.edit');
        Route::patch('borrowers/{borrower}', [BorrowerController::class, 'update'])->name('borrowers.update');
    });

    Route::middleware('permission:'.Permission::USERS_MANAGE)->group(function () {
        Route::get('administration/users', [UserController::class, 'index'])->name('users.index');
        Route::get('administration/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('administration/users', [UserController::class, 'store'])->name('users.store');
        Route::get('administration/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::patch('administration/users/{user}', [UserController::class, 'update'])->name('users.update');
    });

    Route::middleware('permission:'.Permission::ROLES_MANAGE)->group(function () {
        Route::get('administration/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('administration/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::patch('administration/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('administration/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    });

    Route::get('administration/audit-logs', [AuditLogController::class, 'index'])
        ->middleware('permission:'.Permission::AUDIT_VIEW)
        ->name('audit-logs.index');
});

require __DIR__.'/settings.php';
