<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminProfileController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\MappingController;
use App\Http\Controllers\Admin\SyncDashboardController;
use App\Http\Controllers\Admin\OperaCredentialsController;
use App\Http\Controllers\Admin\UploadController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Root: redirect to login or dashboard based on admin auth
Route::get('/', function () {
    return Auth::guard('admin')->check()
        ? redirect()->route('admin.dashboard')
        : redirect()->route('admin.login');
})->name('home');

// Admin auth (guest)
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest.admin')->group(function () {
        Route::get('/', [AdminAuthController::class, 'showLoginForm'])->name('login');
        Route::post('login', [AdminAuthController::class, 'login'])->name('login.post');
        Route::get('forgot-password', [AdminAuthController::class, 'showForgotForm'])->name('password.request');
        Route::post('forgot-password', [AdminAuthController::class, 'sendResetLink'])->name('password.email');
        Route::get('reset-password/{token}', [AdminAuthController::class, 'showResetForm'])->name('password.reset');
        Route::post('reset-password', [AdminAuthController::class, 'resetPassword'])->name('password.update');
    });

    Route::middleware(['auth.admin', 'admin.session.timeout'])->group(function () {
        Route::post('logout', [AdminAuthController::class, 'logout'])->name('logout');
        Route::get('profile', [AdminProfileController::class, 'edit'])->name('profile.edit');
        Route::put('profile', [AdminProfileController::class, 'update'])->name('profile.update');
        Route::get('/dashboard', [SyncDashboardController::class, 'dashboard'])->name('dashboard');
        Route::get('/uploads', [UploadController::class, 'index'])->name('uploads.index');
        Route::post('/uploads', [UploadController::class, 'store'])->name('uploads.store');
        Route::get('/uploads/{index}', [UploadController::class, 'show'])
            ->whereNumber('index')
            ->name('uploads.show');
        Route::post('/uploads/run', [UploadController::class, 'run'])->name('uploads.run');
        Route::get('/mapping', [MappingController::class, 'edit'])->name('mapping.edit');
        Route::post('/mapping', [MappingController::class, 'update'])->name('mapping.update');
        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
        Route::get('/users/{admin}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{admin}', [AdminUserController::class, 'update'])->name('users.update');
        Route::delete('/users/{admin}', [AdminUserController::class, 'destroy'])->name('users.destroy');
        Route::post('/users/{admin}/reset-password', [AdminUserController::class, 'sendReset'])->name('users.reset');
        Route::get('/sync', [SyncDashboardController::class, 'index'])->name('sync.index');
        Route::get('/execution-history', [SyncDashboardController::class, 'executionHistory'])->name('execution-history');
        Route::get('/failed-records', [SyncDashboardController::class, 'failedRecords'])->name('failed-records');
        Route::get('/failed-jobs', [SyncDashboardController::class, 'failedJobs'])->name('failed-jobs');
        Route::get('/payload-audit', [SyncDashboardController::class, 'payloadAudit'])->name('payload-audit');
        Route::get('/payload-audit/{payloadAudit}', [SyncDashboardController::class, 'payloadAuditShow'])->name('payload-audit.show');
        Route::get('/configuration', [SyncDashboardController::class, 'configuration'])->name('configuration');
        Route::post('/configuration/schedule', [SyncDashboardController::class, 'updateSchedule'])->name('configuration.schedule');
        Route::post('/configuration/clear-history', [SyncDashboardController::class, 'clearSyncHistory'])->name('configuration.clear-history');

        Route::get('/opera-credentials', [OperaCredentialsController::class, 'edit'])->name('opera-credentials.edit');
        Route::post('/opera-credentials', [OperaCredentialsController::class, 'update'])->name('opera-credentials.update');
        Route::post('/sync/run', [SyncDashboardController::class, 'runSync'])->name('sync.run');
        Route::post('/sync/run-full', [SyncDashboardController::class, 'runSyncFull'])->name('sync.run-full');
        Route::post('/sync/retry-failed', [SyncDashboardController::class, 'retryFailed'])->name('sync.retry-failed');
        Route::get('/logs', [SyncDashboardController::class, 'logs'])->name('logs');
    });
});
