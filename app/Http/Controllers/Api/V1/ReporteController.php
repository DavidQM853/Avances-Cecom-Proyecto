<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PeriodoTipo;
use App\Http\Controllers\Controller;
use App\Models\Departamento;
use App\Models\Presupuesto;
use App\Models\ProyeccionAnual;
use App\Services\AgregacionTemporalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReporteController extends Controller
{
    public function __construct(
        protected AgregacionTemporalService $agregacionService
    ) {}

    /**
     * API central de agregación y filtrado temporal (Objetivo 2).
     */
    public function agregaciones(Request $request): JsonResponse
    {
        $request->validate([
            'periodo' => 'required|string|in:diario,quincenal,mensual,trimestral,semestral,anual',
            'desde' => 'required|date',
            'hasta' => 'required|date|after_or_equal:desde',
            'departamento_id' => 'nullable|exists:departamentos,id',
            'categoria_opex_id' => 'nullable|exists:categorias_opex,id',
            'usar_precalculado' => 'nullable|boolean',
        ]);

        $periodo = PeriodoTipo::from($request->periodo);
        $departamentoId = $request->filled('departamento_id') ? $request->integer('departamento_id') : null;
        $categoriaOpexId = $request->filled('categoria_opex_id') ? $request->integer('categoria_opex_id') : null;
        $usarPrecalculado = $request->boolean('usar_precalculado', false);

        $datos = $this->agregacionService->calcular(
            $periodo,
            $request->desde,
            $request->hasta,
            $departamentoId,
            $categoriaOpexId,
            $usarPrecalculado
        );

        return response()->json([
            'filtros' => [
                'periodo' => $periodo->value,
                'periodo_label' => $periodo->label(),
                'desde' => $request->desde,
                'hasta' => $request->hasta,
                'departamento_id' => $departamentoId,
                'categoria_opex_id' => $categoriaOpexId,
            ],
            'resumen' => [
                'total_general' => round((float) $datos->sum('monto_total'), 2),
                'total_transacciones' => $datos->sum('cantidad_transacciones'),
                'total_periodos' => $datos->count(),
                'promedio_por_periodo' => $datos->count() > 0
                    ? round((float) $datos->sum('monto_total') / $datos->count(), 2)
                    : 0.0,
            ],
            'serie_temporal' => $datos,
        ]);
    }

    /**
     * Reporte de tendencia temporal de los últimos N meses.
     */
    public function tendencia(Request $request): JsonResponse
    {
        $meses = $request->integer('meses', 12);
        $hasta = now()->endOfMonth()->toDateString();
        $desde = now()->subMonths($meses)->startOfMonth()->toDateString();

        $departamentoId = $request->filled('departamento_id') ? $request->integer('departamento_id') : null;

        $datos = $this->agregacionService->calcular(
            PeriodoTipo::MENSUAL,
            $desde,
            $hasta,
            $departamentoId
        );

        return response()->json([
            'meses' => $meses,
            'desde' => $desde,
            'hasta' => $hasta,
            'datos' => $datos,
        ]);
    }

    /**
     * Variabilidad intermensual (Objetivo 6 del anteproyecto).
     */
    public function variabilidadMensual(Request $request): JsonResponse
    {
        $request->validate([
            'anio' => 'nullable|integer',
            'departamento_id' => 'nullable|exists:departamentos,id',
        ]);

        $anio = $request->integer('anio', (int) now()->format('Y'));
        $departamentoId = $request->filled('departamento_id') ? $request->integer('departamento_id') : null;

        $analisis = $this->agregacionService->calcularVariabilidadMensual($anio, $departamentoId);

        $deptoInfo = null;
        if ($departamentoId) {
            $depto = Departamento::find($departamentoId);
            $deptoInfo = [
                'id' => $depto->id,
                'nombre' => $depto->nombre,
                'codigo' => $depto->codigo,
            ];
        }

        return response()->json([
            'departamento' => $deptoInfo,
            'analisis' => $analisis,
        ]);
    }

    /**
     * Reporte general de ejecución presupuestal por departamento.
     */
    public function ejecucion(Request $request): JsonResponse
    {
        $anio = $request->integer('anio', (int) now()->format('Y'));

        $presupuestos = Presupuesto::whereHas('periodoFiscal', fn($q) => $q->where('anio', $anio)->whereNull('trimestre')->whereNull('mes'))
            ->with(['departamento:id,nombre,codigo', 'partidas.categoriaOpex'])
            ->get();

        $reporte = $presupuestos->map(function ($p) {
            return [
                'departamento' => $p->departamento,
                'monto_total' => (float) $p->monto_total,
                'monto_ejercido' => (float) $p->monto_ejercido,
                'monto_comprometido' => (float) $p->monto_comprometido,
                'monto_disponible' => (float) $p->monto_disponible,
                'porcentaje_ejecucion' => $p->porcentajeEjecucion(),
                'estado' => $p->estado->value,
                'estado_label' => $p->estado->label(),
                'semaforo' => match (true) {
                    $p->porcentajeEjecucion() >= 90 => 'rojo',
                    $p->porcentajeEjecucion() >= 75 => 'amarillo',
                    default => 'verde',
                },
            ];
        });

        return response()->json([
            'anio' => $anio,
            'totales' => [
                'presupuesto_total' => (float) $presupuestos->sum('monto_total'),
                'total_ejercido' => (float) $presupuestos->sum('monto_ejercido'),
                'total_comprometido' => (float) $presupuestos->sum('monto_comprometido'),
                'total_disponible' => (float) $presupuestos->sum('monto_disponible'),
                'porcentaje_global_ejecucion' => $presupuestos->sum('monto_total') > 0
                    ? round(($presupuestos->sum('monto_ejercido') / $presupuestos->sum('monto_total')) * 100, 2)
                    : 0.0,
            ],
            'departamentos' => $reporte,
        ]);
    }

    /**
     * Consulta de las 3 vistas de conciliación de cecom_presupuestos_er:
     * v_presupuestos_conciliacion, v_partidas_conciliacion y v_reservas_conciliacion.
     */
    public function conciliacion(): JsonResponse
    {
        $presupuestos = DB::table('v_presupuestos_conciliacion')->get();
        $partidas = DB::table('v_partidas_conciliacion')->get();
        $reservas = DB::table('v_reservas_conciliacion')->get();

        return response()->json([
            'resumen' => [
                'presupuestos_descuadrados' => $presupuestos->where('requiere_revision', 1)->count(),
                'partidas_descuadradas' => $partidas->where('requiere_revision', 1)->count(),
                'reservas_descuadradas' => $reservas->where('requiere_revision', 1)->count(),
            ],
            'presupuestos' => $presupuestos,
            'partidas' => $partidas,
            'reservas' => $reservas,
        ]);
    }

    /**
     * Consulta de proyecciones presupuestales anuales y métricas del modelo predictivo.
     */
    public function proyecciones(Request $request): JsonResponse
    {
        $query = ProyeccionAnual::with([
            'modelo:id,version,version_datos,metricas_validacion',
            'departamento:id,nombre,codigo',
        ]);

        if ($request->filled('anio_objetivo')) {
            $query->where('anio_objetivo', $request->integer('anio_objetivo'));
        }

        if ($request->filled('departamento_id')) {
            $query->where('departamento_id', $request->integer('departamento_id'));
        }

        return response()->json([
            'data' => $query->orderBy('anio_objetivo')->get(),
        ]);
    }
}
