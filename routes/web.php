<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConsultaApiController;
use App\Http\Controllers\EstadisticasController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\PagoVerificacionController;
use App\Http\Controllers\ReporteConciliacionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VehiculoController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Redirigir la raíz al panel administrativo
Route::get('/', function () {
    return redirect('/admin/login');
});

// Política de Privacidad (LOPDP Ecuador)
Route::get('/politica-privacidad', function () {
    return Inertia::render('PoliticaPrivacidad');
})->name('politica.privacidad');

// Rutas con rate limiting (60 requests por minuto Laravel + Redis rate limiting)
Route::middleware(['throttle:60,1', 'redis.ratelimit'])->group(function () {
    // Rutas de consulta de vehículos
    Route::get('/consultar', [VehiculoController::class, 'consultar'])->name('vehiculos.consultar');
    Route::post('/consultar', [VehiculoController::class, 'buscar'])->name('vehiculos.buscar');

    // Rutas de pagos
    Route::get('/pago/facturacion', [PagoController::class, 'facturacion'])->name('pago.facturacion');
    Route::post('/pago/procesar', [PagoController::class, 'procesar'])->name('pago.procesar');

    // Verificación de comprobantes (para QR code): pública, sin auth,
    // buscando por un código corto (TRX-XXXXXX, ver rename de
    // token_verificacion) — sin límite era fuerza-bruteable (auditoría
    // anterior). Mismo límite que el resto de rutas públicas de este grupo.
    Route::get('/verificar/{referencia}', [PagoController::class, 'verificar'])->name('pago.verificar');
});

// Rutas de callback y confirmación (sin rate limit estricto)
Route::get('/pago/callback', [PagoController::class, 'callback'])->name('pago.callback');
// {pago:certificado_token}: route model binding por el token aleatorio,
// NO por el id crudo — el id es secuencial (coincide con "comprobante",
// PAG-XXXXXX) y estas rutas devuelven datos_facturacion (PII) sin
// autenticación; usar el id permitía enumerar pagos de otros ciudadanos
// (ver auditoría de seguridad). certificado_token ya existe y es
// aleatorio — se reutiliza en vez de crear un tercer identificador.
Route::get('/pago/confirmacion/{pago:certificado_token}', [PagoController::class, 'confirmacion'])->name('pago.confirmacion');
Route::get('/comprobante/{pago:certificado_token}', [PagoController::class, 'comprobante'])->name('pago.comprobante');

// Certificado de Pago (documento estandarizado, valido para cualquier
// entidad recaudadora). Clave de acceso: certificado_token, no referencia_pago
// (esa la define el banco/cooperativa externo y suele ser secuencial/adivinable).
Route::get('/certificado/{token}', [PagoController::class, 'certificado'])->name('pago.certificado');

// Webhook (sin CSRF, rate limit específico)
Route::middleware(['throttle:200,1'])->group(function () {
    Route::post('/webhook/pago', [WebhookController::class, 'pago'])
        ->name('webhook.pago')
        ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
});

// Rutas de autenticación
Route::get('/admin', function () {
    if (auth()->check()) {
        $user = auth()->user();
        if ($user->hasRole('admin')) {
            return redirect()->route('admin.dashboard');
        } else {
            return redirect()->route('admin.consulta-api.index');
        }
    }
    return redirect()->route('login');
});

Route::get('/admin/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/admin/login', [AuthController::class, 'login']);

// Rutas protegidas del dashboard (requiere autenticación)
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    // Rutas solo para admin
    Route::middleware('role:admin')->group(function () {
        Route::get('/dashboard', [EstadisticasController::class, 'index'])->name('dashboard');
        Route::post('/dashboard/reset', [EstadisticasController::class, 'reset'])->name('dashboard.reset');

        // Métricas del SRI
        Route::get('/api/sri/metrics', [\App\Http\Controllers\SriMonitoringController::class, 'metrics'])->name('sri.metrics');

        // Log Viewer (Custom)
        Route::get('/logs', [App\Http\Controllers\LogViewerController::class, 'index'])->name('logs');
        Route::get('/logs/download', [App\Http\Controllers\LogViewerController::class, 'download'])->name('logs.download');
        Route::post('/logs/clear', [App\Http\Controllers\LogViewerController::class, 'clear'])->name('logs.clear');

        // Reporte de Conciliación (pagos registrados, filtros y totales)
        // SOLO admin: información financiera/administrativa agregada de
        // TODAS las entidades — no forma parte del panel de ventanilla
        // (verificacionpagos), a diferencia de verificar-pago/consulta-api.
        Route::get('/reporte-conciliacion', [ReporteConciliacionController::class, 'index'])->name('reporte-conciliacion.index');
        Route::post('/reporte-conciliacion', [ReporteConciliacionController::class, 'consultar'])->name('reporte-conciliacion.consultar');
    });

    // Rutas para admin y verificacionpagos (ambos usan la misma URL, el
    // mismo layout y el mismo componente — solo cambia lo que el rol puede
    // visitar, resuelto por este middleware)
    Route::middleware('role:admin|verificacionpagos')->group(function () {
        // Consulta de deuda vehicular por placa
        Route::get('/consulta-api', [ConsultaApiController::class, 'index'])->name('consulta-api.index');
        Route::post('/consulta-api', [ConsultaApiController::class, 'consultar'])->name('consulta-api.consultar');

        // Verificación de pagos (por comprobante o QR, en una sola pantalla)
        Route::get('/verificar-pago', [PagoVerificacionController::class, 'index'])->name('verificar.index');
        // El POST hace la búsqueda real — sin límite, un admin/sesión
        // comprometida podía fuerza-brutear referencia_externa o el id
        // secuencial (ver PagoVerificacionController::buscarTransaccion,
        // auditoría del rename de token_verificacion). Requiere sesión
        // autenticada igual (grupo 'auth' arriba), pero eso no reemplaza
        // un límite de intentos.
        Route::post('/verificar-pago', [PagoVerificacionController::class, 'verificar'])
            ->middleware('throttle:60,1')
            ->name('verificar.buscar');
    });

    // Rutas solo para admin - Gestión de usuarios
    Route::middleware('role:admin')->group(function () {
        Route::get('/usuarios', [UserController::class, 'index'])->name('usuarios.index');
        Route::post('/usuarios', [UserController::class, 'store'])->name('usuarios.store');
        Route::put('/usuarios/{user}', [UserController::class, 'update'])->name('usuarios.update');
        Route::delete('/usuarios/{user}', [UserController::class, 'destroy'])->name('usuarios.destroy');
    });

    // Logout disponible para todos los autenticados
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

// Rutas de prueba de Redis (solo en desarrollo)
if (app()->environment('local')) {
    Route::get('/test/simulacion', function () {
        return Inertia::render('Consulta/Simulacion');
    })->name('test.simulacion');

    Route::prefix('redis-test')->name('redis.test.')->group(function () {
        Route::get('/', [\App\Http\Controllers\RedisTestController::class, 'index'])->name('index');
        Route::get('/ping', [\App\Http\Controllers\RedisTestController::class, 'ping'])->name('ping');
        Route::get('/cache', [\App\Http\Controllers\RedisTestController::class, 'testCache'])->name('cache');
        Route::get('/strings', [\App\Http\Controllers\RedisTestController::class, 'testStrings'])->name('strings');
        Route::get('/counters', [\App\Http\Controllers\RedisTestController::class, 'testCounters'])->name('counters');
        Route::get('/lists', [\App\Http\Controllers\RedisTestController::class, 'testLists'])->name('lists');
        Route::get('/sets', [\App\Http\Controllers\RedisTestController::class, 'testSets'])->name('sets');
        Route::get('/hashes', [\App\Http\Controllers\RedisTestController::class, 'testHashes'])->name('hashes');
        Route::get('/ttl', [\App\Http\Controllers\RedisTestController::class, 'testTTL'])->name('ttl');
        Route::get('/info', [\App\Http\Controllers\RedisTestController::class, 'info'])->name('info');
        Route::get('/clear', [\App\Http\Controllers\RedisTestController::class, 'clear'])->name('clear');
    });
}
