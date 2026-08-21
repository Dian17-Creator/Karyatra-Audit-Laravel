<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\AuditCategoryController;
use App\Http\Controllers\AuditDepartmentController;
use App\Http\Controllers\AuditQuestionController;
use App\Http\Controllers\AuditReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\StockCategoryController;
use App\Http\Controllers\StockDepartmentController;
use App\Http\Controllers\StockReportController;

Route::post('/login', [AuthController::class, 'login']);

//dashboard controller
Route::get('/dashboard/summary', [DashboardController::class, 'summary']);

// Audit Category Api
Route::prefix('audit/categories')->group(function () {
    Route::get('/', [AuditCategoryController::class, 'index']);
    Route::get('/{id}', [AuditCategoryController::class, 'show']);
    Route::post('/', [AuditCategoryController::class, 'store']);

    Route::post('/{id}/update', [AuditCategoryController::class, 'update']);
    Route::post('/{id}/delete', [AuditCategoryController::class, 'destroy']);
});

//Audit Question Api
Route::get('/audit/categories/{categoryId}/questions', [AuditQuestionController::class, 'index']);
Route::post('/audit/questions', [AuditQuestionController::class, 'store']);
Route::post('/audit/questions/reorder', [AuditQuestionController::class, 'reorder']);
Route::post('/audit/questions/{id}', [AuditQuestionController::class, 'update']);
Route::post('/audit/questions/{id}/delete', [AuditQuestionController::class, 'destroy']);

// Audit Department Api
Route::get('/audit/departments', [AuditDepartmentController::class, 'index']);
Route::get('/audit/departments/{id}/mapping', [AuditDepartmentController::class, 'mapping']);
Route::post('/audit/departments/mapping', [AuditDepartmentController::class, 'storeMapping']);

// Audit Reports / Execution API
Route::prefix('audits')->group(function () {
    Route::get('/', [AuditReportController::class, 'index']);
    Route::get('/detail', [AuditReportController::class, 'show']);
    Route::get('/{id}/export-pdf', [AuditReportController::class, 'exportPdf']);
    Route::post('/create', [AuditReportController::class, 'store']);
    Route::post('/update', [AuditReportController::class, 'updateAnswers']);
    Route::post('/upload-photo', [AuditReportController::class, 'uploadPhoto']);
    Route::post('/update-photo', [AuditReportController::class, 'updatePhoto']);
    Route::post('/delete-photo', [AuditReportController::class, 'deletePhoto']);
    Route::post('/submit', [AuditReportController::class, 'submit']);
    Route::post('/delete', [AuditReportController::class, 'destroy']);
    Route::post('/send-email', [AuditReportController::class, 'sendEmail']);
});

//API STOCK OPNAME

// Stock Category & Item Api
Route::prefix('stock')->group(function () {

    // Category
    Route::get('/categories', [StockCategoryController::class, 'index']);
    Route::post('/categories', [StockCategoryController::class, 'storeCategory']);
    Route::put('/categories/{id?}', [StockCategoryController::class, 'updateCategory']);
    Route::delete('/categories/{id?}', [StockCategoryController::class, 'destroyCategory']);

    // Items
    Route::get('/categories/{categoryId}/items', [StockCategoryController::class, 'getItems']);
    Route::post('/items', [StockCategoryController::class, 'storeItem']);
    Route::delete('/items/{id?}', [StockCategoryController::class, 'destroyItem']);
    Route::post('/items/reorder', [StockCategoryController::class, 'reorderItems']);
});

// STOCK DEPARTMENT
Route::prefix('stock/departments')->group(function () {
    Route::get('/', [StockDepartmentController::class, 'index']);
    Route::get('/{id}/mapping', [StockDepartmentController::class, 'mapping']);
    Route::post('/mapping', [StockDepartmentController::class, 'storeMapping']);
});

// STOCK REPORTS / EXECUTION
Route::prefix('stock/opname')->group(function () {
    Route::get('/', [StockReportController::class, 'index']);
    Route::post('/create', [StockReportController::class, 'store']);
    Route::get('/detail/{id}', [StockReportController::class, 'show']);
    Route::get('/{id}/export-pdf', [StockReportController::class, 'exportPdf']);
    Route::post('/update', [StockReportController::class, 'updateAnswers']);
    Route::post('/upload-photo', [StockReportController::class, 'uploadPhoto']);
    Route::post('/update-photo', [StockReportController::class, 'updatePhoto']);
    Route::post('/delete-photo', [StockReportController::class, 'deletePhoto']);
    Route::post('/submit', [StockReportController::class, 'submit']);
    Route::post('/send-email', [StockReportController::class, 'sendEmail']);
});
