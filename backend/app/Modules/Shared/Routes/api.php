<?php

use App\Modules\Shared\Presentation\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// Rutas del módulo Shared.
// Los controladores viven en Presentation\Controllers y delegan a Application\UseCases.
Route::prefix('api/shared')->middleware('api')->group(function () {
    // Route::get('/puestos', [\App\Modules\Shared\Presentation\Controllers\ExampleController::class, 'index']);
});

// Autenticación mínima (feature 000-login). Prefijo /api/auth por contrato; ver specs/000-login/plan.md.
Route::prefix('api/auth')->middleware('api')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('/me', [AuthController::class, 'me'])->name('auth.me');
    });
});
