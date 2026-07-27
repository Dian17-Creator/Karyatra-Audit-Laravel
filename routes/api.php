<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\AuditCategoryController;
use App\Http\Controllers\AuditQuestionController;

Route::post('/login', [AuthController::class, 'login']);

// Audit Category Api
Route::prefix('audit/categories')->group(function () {
    Route::get('/', [AuditCategoryController::class, 'index']);
    Route::get('/{id}', [AuditCategoryController::class, 'show']);
    Route::post('/', [AuditCategoryController::class, 'store']);

    Route::post('/{id}/update', [AuditCategoryController::class, 'update']);
    Route::post('/{id}/delete', [AuditCategoryController::class, 'destroy']);
});

//Audir Question Api
Route::get('/audit/categories/{categoryId}/questions', [AuditQuestionController::class, 'index']);
Route::post('/audit/questions', [AuditQuestionController::class, 'store']);
Route::post('/audit/questions/{id}', [AuditQuestionController::class, 'update']);
Route::post('/audit/questions/{id}/delete', [AuditQuestionController::class, 'destroy']);
Route::post('/audit/questions/reorder', [AuditQuestionController::class, 'reorder']);
