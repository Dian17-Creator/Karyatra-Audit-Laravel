<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\AuditCategoryController;

Route::post('/login', [AuthController::class, 'login']);

// Audit Category Api

Route::prefix('audit/categories')->group(function () {
    Route::get('/', [AuditCategoryController::class, 'index']);
    Route::get('/{id}', [AuditCategoryController::class, 'show']);
    Route::post('/', [AuditCategoryController::class, 'store']);

    Route::post('/{id}/update', [AuditCategoryController::class, 'update']);
    Route::post('/{id}/delete', [AuditCategoryController::class, 'destroy']);
});
