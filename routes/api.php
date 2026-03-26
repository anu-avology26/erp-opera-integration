<?php

use App\Http\Controllers\Api\SyncStatusController;
use Illuminate\Support\Facades\Route;

Route::middleware(['admin.key'])->prefix('v1')->group(function () {
    Route::get('/sync/status', [SyncStatusController::class, 'status']);
    Route::post('/sync/run', [SyncStatusController::class, 'run']);
});
