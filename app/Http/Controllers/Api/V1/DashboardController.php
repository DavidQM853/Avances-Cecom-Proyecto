<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CategoriaOpex;
use App\Models\Departamento;
use App\Models\GastoOperativo;
use App\Models\Presupuesto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Resumen ejecutivo del dashboard general.
     */
    public function resumen(Request $request): JsonResponse
    {
        $anio = $request->integer('anio', (int) now()->format('Y'));

        $presupuestos = Presupuesto::whereHas('periodoFiscal', fn($q) => $q->where('anio', $anio)->whereNull('trimestre')->whereNull('mes'))
            ->with('departamento:id,nombre,codigo')
            ->get();

        $totalPresupuesto = (float) $presupuestos->sum('monto_total');
        $totalEjercido = (float) $presupuestos->sum('monto_ejercido');
        $totalComprometido = (float) $presupuestos->sum('monto_comprometido');
        $totalDisponible = (float) $presupuestos->sum('monto_disponible');

        $porcentajeGlobal = $totalPresupuesto > 0
            ? round(($totalEjercido / $totalPresupuesto) * 100, 2)
            : 0.0;

        $topDepartamentosGasto = $presupuestos->sortByDesc('monto_ejercido')->take(5)->values()->map(function ($p) {
            return [
                'departamento' => $p->departamento?->nombre,
                'codigo' => $p->departamento?->codigo,
                'presupuesto' => (float) $p->monto_total,
                'ejercido' => (float) $p->monto_ejercido,
                'porcentaje_ejecucion' => $p->porcentajeEjecucion(),
            ];
        });

        return response()->json([
            'ejercicio_fiscal' => $anio,
            'kpis_financieros' => [
                'presupuesto_total' => $totalPresupuesto,
                'monto_ejercido' => $totalEjercido,
                'monto_comprometido' => $totalComprometido,
                'monto_disponible' => $totalDisponible,
                'porcentaje_ejecucion' => $porcentajeGlobal,
                'estado_salud' => match (true) {
                    $porcentajeGlobal > 85 => 'alerta',
                    $porcentajeGlobal > 60 => 'precaucion',
                    default => 'optimo',
                },
            ],
            'top_departamentos_gasto' => $topDepartamentosGasto,
            'total_departamentos' => Departamento::whereNotNull('departamento_padre_id')->count(),
        ]);
    }

    /**
     * KPIs operativos y de gestión presupuestal.
     */
    public function kpis(Request $request): JsonResponse
    {
        $anio = $request->integer('anio', (int) now()->format('Y'));

        $totalGastos = GastoOperativo::whereYear('fecha_gasto', $anio)->count();
        $gastoPromedio = GastoOperativo::whereYear('fecha_gasto', $anio)->avg('monto') ?? 0;

        // Top categoría con mayor gasto
        $topCategoria = DB::table('gastos_operativos')
            ->join('partidas_presupuestales', 'gastos_operativos.partida_id', '=', 'partidas_presupuestales.id')
            ->join('categorias_opex', 'partidas_presupuestales.categoria_opex_id', '=', 'categorias_opex.id')
            ->whereYear('gastos_operativos.fecha_gasto', $anio)
            ->select('categorias_opex.nombre', 'categorias_opex.codigo', DB::raw('SUM(gastos_operativos.monto) as total_monto'))
            ->groupBy('categorias_opex.id', 'categorias_opex.nombre', 'categorias_opex.codigo')
            ->orderByDesc('total_monto')
            ->first();

        return response()->json([
            'ejercicio_fiscal' => $anio,
            'total_transacciones_gasto' => $totalGastos,
            'ticket_promedio_gasto' => round((float)$gastoPromedio, 2),
            'categoria_mayor_impacto' => $topCategoria ? [
                'nombre' => $topCategoria->nombre,
                'codigo' => $topCategoria->codigo,
                'monto_total' => round((float)$topCategoria->total_monto, 2),
            ] : null,
        ]);
    }

    /**
     * Monitoreo de latencia de base de datos en tiempo real (Objetivo 5 del anteproyecto).
     *
     * Mide la latencia en milisegundos de diferentes tipos de consultas a la BD:
     * - Ping básico (SELECT 1)
     * - Conteo de gran volumen (COUNT en gastos_operativos)
     * - Agregación compleja con JOINs múltiples
     * - Inserción y reversión de prueba
     */
    public function latencia(): JsonResponse
    {
        $metricas = [];

        // 1. Ping / Latencia de conexión básica
        $inicio = microtime(true);
        DB::select('SELECT 1');
        $metricas['ping_ms'] = round((microtime(true) - $inicio) * 1000, 2);

        // 2. Conteo de tabla de gran volumen
        $inicio = microtime(true);
        DB::table('gastos_operativos')->count();
        $metricas['count_gastos_ms'] = round((microtime(true) - $inicio) * 1000, 2);

        // 3. Consulta de agregación temporal compleja con múltiples JOINs
        $inicio = microtime(true);
        DB::table('gastos_operativos')
            ->join('partidas_presupuestales', 'gastos_operativos.partida_id', '=', 'partidas_presupuestales.id')
            ->join('presupuestos', 'partidas_presupuestales.presupuesto_id', '=', 'presupuestos.id')
            ->join('departamentos', 'presupuestos.departamento_id', '=', 'departamentos.id')
            ->select('departamentos.codigo', DB::raw('SUM(gastos_operativos.monto) as total, COUNT(*) as qty'))
            ->groupBy('departamentos.codigo')
            ->get();
        $metricas['join_agregacion_ms'] = round((microtime(true) - $inicio) * 1000, 2);

        $latenciaPromedio = round(array_sum($metricas) / count($metricas), 2);

        return response()->json([
            'timestamp' => now()->toIso8601String(),
            'database_driver' => DB::connection()->getDriverName(),
            'latencia_promedio_ms' => $latenciaPromedio,
            'metricas_detalle' => $metricas,
            'estado' => match (true) {
                $latenciaPromedio < 50 => 'excelente',
                $latenciaPromedio < 150 => 'bueno',
                $latenciaPromedio < 300 => 'moderado',
                default => 'degradado',
            },
        ]);
    }

    /**
     * Health check general del sistema.
     */
    public function health(): JsonResponse
    {
        $dbOk = true;
        try {
            DB::select('SELECT 1');
        } catch (\Throwable $e) {
            $dbOk = false;
        }

        return response()->json([
            'status' => $dbOk ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'database' => [
                'status' => $dbOk ? 'connected' : 'disconnected',
                'driver' => DB::connection()->getDriverName(),
            ],
        ]);
    }
}
