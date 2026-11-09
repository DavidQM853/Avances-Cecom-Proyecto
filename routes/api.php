<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Dashboard Presupuestos CECOM
|--------------------------------------------------------------------------
|
| Todas las rutas API están versionadas bajo /api/v1/
| Las rutas protegidas requieren autenticación via Sanctum y validan
| control de acceso por rol (CheckRole) y restricciones OPEX (CheckOpexPermission).
|
*/

Route::prefix('v1')->group(function () {

    // ─── Rutas públicas ────────────────────────────────────────

    Route::prefix('auth')->group(function () {
        Route::post('/login', [\App\Http\Controllers\Api\V1\AuthController::class, 'login']);
    });

    // Monitoreo público (Health check y latencia en tiempo real)
    Route::prefix('monitor')->group(function () {
        Route::get('/latencia', [\App\Http\Controllers\Api\V1\DashboardController::class, 'latencia']);
        Route::get('/health', [\App\Http\Controllers\Api\V1\DashboardController::class, 'health']);
    });

    // ─── Rutas protegidas ──────────────────────────────────────

    Route::middleware('auth:sanctum')->group(function () {

        // Auth
        Route::prefix('auth')->group(function () {
            Route::post('/logout', [\App\Http\Controllers\Api\V1\AuthController::class, 'logout']);
            Route::get('/me', [\App\Http\Controllers\Api\V1\AuthController::class, 'me']);
        });

        // Departamentos
        Route::apiResource('departamentos', \App\Http\Controllers\Api\V1\DepartamentoController::class);
        Route::get('departamentos/{departamento}/presupuestos', [\App\Http\Controllers\Api\V1\DepartamentoController::class, 'presupuestos']);
        Route::get('departamentos/{departamento}/gastos', [\App\Http\Controllers\Api\V1\DepartamentoController::class, 'gastos']);

        // Presupuestos
        Route::apiResource('presupuestos', \App\Http\Controllers\Api\V1\PresupuestoController::class);
        Route::patch('presupuestos/{presupuesto}/estado', [\App\Http\Controllers\Api\V1\PresupuestoController::class, 'cambiarEstado'])
            ->middleware('role:admin,aprobador,director');
        Route::get('presupuestos-yoy/comparativa', [\App\Http\Controllers\Api\V1\PresupuestoController::class, 'comparativaYoY']);

        // Gastos Operativos (protegidos con CheckOpexPermission en escritura)
        Route::get('gastos', [\App\Http\Controllers\Api\V1\GastoOperativoController::class, 'index']);
        Route::get('gastos/{gasto}', [\App\Http\Controllers\Api\V1\GastoOperativoController::class, 'show']);
        Route::post('gastos', [\App\Http\Controllers\Api\V1\GastoOperativoController::class, 'store'])
            ->middleware('opex.permission');
        Route::put('gastos/{gasto}', [\App\Http\Controllers\Api\V1\GastoOperativoController::class, 'update'])
            ->middleware('opex.permission');
        Route::patch('gastos/{gasto}', [\App\Http\Controllers\Api\V1\GastoOperativoController::class, 'update'])
            ->middleware('opex.permission');
        Route::delete('gastos/{gasto}', [\App\Http\Controllers\Api\V1\GastoOperativoController::class, 'destroy'])
            ->middleware('opex.permission');

        // Compromisos y Reservas Presupuestales
        Route::get('compromisos', [\App\Http\Controllers\Api\V1\CompromisoReservaController::class, 'index']);
        Route::post('compromisos', [\App\Http\Controllers\Api\V1\CompromisoReservaController::class, 'store'])
            ->middleware('opex.permission');
        Route::patch('compromisos/{compromiso}/liberar', [\App\Http\Controllers\Api\V1\CompromisoReservaController::class, 'liberar']);

        // Agregaciones y Filtros Temporales
        Route::prefix('agregaciones')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\ReporteController::class, 'agregaciones']);
            Route::get('/tendencia', [\App\Http\Controllers\Api\V1\ReporteController::class, 'tendencia']);
            Route::get('/variabilidad', [\App\Http\Controllers\Api\V1\ReporteController::class, 'variabilidadMensual']);
            Route::get('/yoy', [\App\Http\Controllers\Api\V1\PresupuestoController::class, 'comparativaYoY']);
        });

        // Dashboard y KPIs
        Route::prefix('dashboard')->group(function () {
            Route::get('/resumen', [\App\Http\Controllers\Api\V1\DashboardController::class, 'resumen']);
            Route::get('/kpis', [\App\Http\Controllers\Api\V1\DashboardController::class, 'kpis']);
        });

        // Reportes, Conciliación y Proyecciones Predictivas
        Route::prefix('reportes')->group(function () {
            Route::get('/ejecucion', [\App\Http\Controllers\Api\V1\ReporteController::class, 'ejecucion']);
            Route::get('/variabilidad-mensual', [\App\Http\Controllers\Api\V1\ReporteController::class, 'variabilidadMensual']);
            Route::get('/conciliacion', [\App\Http\Controllers\Api\V1\ReporteController::class, 'conciliacion']);
            Route::get('/proyecciones', [\App\Http\Controllers\Api\V1\ReporteController::class, 'proyecciones']);
        });

        // Roles y Permisos (gestión restringida por CheckRole)
        Route::get('roles', [\App\Http\Controllers\Api\V1\RolController::class, 'index']);
        Route::get('roles/{rol}', [\App\Http\Controllers\Api\V1\RolController::class, 'show']);
        Route::post('roles', [\App\Http\Controllers\Api\V1\RolController::class, 'store'])->middleware('role:admin');
        Route::put('roles/{rol}', [\App\Http\Controllers\Api\V1\RolController::class, 'update'])->middleware('role:admin');
        Route::patch('roles/{rol}', [\App\Http\Controllers\Api\V1\RolController::class, 'update'])->middleware('role:admin');
        Route::post('roles/{rol}/asignar-usuario', [\App\Http\Controllers\Api\V1\RolController::class, 'asignarUsuario'])->middleware('role:admin');
        Route::delete('roles/{rol}/revocar-usuario/{user}', [\App\Http\Controllers\Api\V1\RolController::class, 'revocarUsuario'])->middleware('role:admin');
        Route::get('usuarios/{user}/permisos-efectivos', [\App\Http\Controllers\Api\V1\UsuarioController::class, 'permisosEfectivos']);
    });
});
