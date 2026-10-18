<?php

use App\Domain\Access\Permission;
use App\Http\Controllers\Administration\AuditLogController;
use App\Http\Controllers\Administration\RoleController;
use App\Http\Controllers\Administration\UserController;
use App\Http\Controllers\BorrowerController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Reports\DailyCollectionReportController;
use App\Http\Controllers\Reports\LoanScheduleReportController;
use App\Http\Controllers\Reports\NotesCollectionReportController;
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

    Route::get('loans', [LoanController::class, 'index'])
        ->middleware('permission:'.Permission::LOANS_VIEW)
        ->name('loans.index');

    Route::middleware('permission:'.Permission::LOANS_CREATE)->group(function () {
        Route::get('loans/create', [LoanController::class, 'create'])->name('loans.create');
        // Registered before "loans/{loan}" so it is never read as a loan id.
        Route::get('loans/preview', [LoanController::class, 'preview'])->name('loans.preview');
        Route::post('loans', [LoanController::class, 'store'])->name('loans.store');
        Route::get('loans/{loan}/edit', [LoanController::class, 'edit'])->name('loans.edit');
    });

    Route::get('loans/{loan}', [LoanController::class, 'show'])
        ->middleware('permission:'.Permission::LOANS_VIEW)
        ->name('loans.show');

    Route::get('loans/{loan}/schedule', LoanScheduleReportController::class)
        ->middleware('permission:'.Permission::LOANS_VIEW)
        ->name('loans.schedule');

    Route::middleware('permission:'.Permission::LOANS_CREATE)->group(function () {
        Route::post('loans/{loan}/post', [LoanController::class, 'post'])->name('loans.post');
    });

    Route::middleware('permission:'.Permission::PAYMENTS_VIEW)->group(function () {
        Route::get('collections/daily', [CollectionController::class, 'daily'])->name('collections.daily');
        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
    });

    Route::middleware('permission:'.Permission::PAYMENTS_CREATE)->group(function () {
        Route::post('collections/daily', [CollectionController::class, 'stage'])->name('collections.stage');
        Route::post('collections/daily/post', [CollectionController::class, 'post'])->name('collections.post');
        Route::delete('collections/daily/payments/{payment}', [CollectionController::class, 'destroyPayment'])
            ->name('collections.payments.destroy');
    });

    Route::post('payments/{payment}/reverse', [PaymentController::class, 'reverse'])
        ->middleware('permission:'.Permission::PAYMENTS_REVERSE)
        ->name('payments.reverse');

    Route::get('reports/notes-collection', [NotesCollectionReportController::class, 'index'])
        ->middleware('permission:'.Permission::REPORTS_VIEW)
        ->name('reports.notes-collection');

    Route::get('reports/notes-collection/export', [NotesCollectionReportController::class, 'export'])
        ->middleware('permission:'.Permission::REPORTS_EXPORT)
        ->name('reports.notes-collection.export');

    Route::middleware('permission:'.Permission::REPORTS_POST)->group(function () {
        Route::post('reports/notes-collection/post', [NotesCollectionReportController::class, 'post'])
            ->name('reports.notes-collection.post');

        Route::post('reports/notes-collection/unpost', [NotesCollectionReportController::class, 'unpost'])
            ->name('reports.notes-collection.unpost');
    });

    Route::get('reports/daily-collection', [DailyCollectionReportController::class, 'index'])
        ->middleware('permission:'.Permission::REPORTS_VIEW)
        ->name('reports.daily-collection');

    Route::get('reports/daily-collection/export', [DailyCollectionReportController::class, 'export'])
        ->middleware('permission:'.Permission::REPORTS_EXPORT)
        ->name('reports.daily-collection.export');
});

require __DIR__.'/settings.php';
