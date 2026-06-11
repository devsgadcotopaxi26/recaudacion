<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\BancaController;
use App\Http\Controllers\Api\AuthBancaController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Rutas API v1 para entidades bancarias
Route::prefix('v1')->group(function () {

    // ─── Autenticación (pública, sin token) ───────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthBancaController::class, 'register']);
        Route::post('/login',    [AuthBancaController::class, 'login']);
        Route::post('/refresh',  [AuthBancaController::class, 'refresh']);
        Route::post('/logout',   [AuthBancaController::class, 'logout'])->middleware('api.token');
    });

    // ─── Endpoints protegidos con token ───────────────────────────────
    Route::middleware(['api.token', 'throttle:bancos'])->group(function () {

        // Consultar deuda de un vehículo
        Route::post('/consulta-deuda-rodaje-bancos', [BancaController::class, 'consultarDeuda']);

        // Registrar pago realizado por banco
        Route::post('/registrar-pago', [BancaController::class, 'registrarPago']);
    });

    // Endpoint de simulación (solo en desarrollo)
    if (app()->environment('local')) {
        Route::post('/test/simulacion', [BancaController::class, 'simulacion']);
    }
});
