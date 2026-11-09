<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EstadoPresupuesto;
use App\Http\Controllers\Controller;
use App\Models\Departamento;
use App\Models\PeriodoFiscal;
use App\Models\Presupuesto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PresupuestoController extends Controller
{
    /**
     * Listado paginado de presupuestos con filtros por año, departamento y estado.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Presupuesto::with([
            'departamento:id,nombre,codigo',
            'periodoFiscal',
            'partidas.categoriaOpex:id,nombre,codigo,tipo_gasto',
        ]);

        if ($request->filled('departamento_id')) {
            $query->where('departamento_id', $request->integer('departamento_id'));
        }

        if ($request->filled('anio')) {
            $query->whereHas('periodoFiscal', function ($q) use ($request) {
                $q->where('anio', $request->integer('anio'));
            });
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        $presupuestos = $query->latest()->paginate($request->integer('per_page', 15));

        // Formatear respuesta enriquecida
        $presupuestos->getCollection()->transform(function ($p) {
            return [
                'id' => $p->id,
                'departamento' => $p->departamento,
                'periodo' => [
                    'id' => $p->periodoFiscal?->id,
                    'nombre' => $p->periodoFiscal?->nombre,
                    'anio' => $p->periodoFiscal?->anio,
                ],
                'monto_total' => (float) $p->monto_total,
                'monto_ejercido' => (float) $p->monto_ejercido,
                'monto_comprometido' => (float) $p->monto_comprometido,
                'monto_disponible' => (float) $p->monto_disponible,
                'porcentaje_ejecucion' => $p->porcentajeEjecucion(),
                'estado' => $p->estado->value,
                'estado_label' => $p->estado->label(),
                'partidas_count' => $p->partidas->count(),
                'created_at' => $p->created_at->toIso8601String(),
            ];
        });

        return response()->json($presupuestos);
    }

    /**
     * Detalle de un presupuesto con desglose completo de partidas.
     */
    public function show(Presupuesto $presupuesto): JsonResponse
    {
        $presupuesto->load([
            'departamento',
            'periodoFiscal',
            'creador:id,name,email',
            'aprobador:id,name,email',
            'partidas.categoriaOpex',
        ]);

        $partidasDetalladas = $presupuesto->partidas->map(function ($partida) {
            return [
                'id' => $partida->id,
                'categoria' => [
                    'id' => $partida->categoriaOpex?->id,
                    'nombre' => $partida->categoriaOpex?->nombre,
                    'codigo' => $partida->categoriaOpex?->codigo,
                    'tipo_gasto' => $partida->categoriaOpex?->tipo_gasto?->value,
                ],
                'monto_asignado' => (float) $partida->monto_asignado,
                'monto_ejercido' => (float) $partida->monto_ejercido,
                'monto_comprometido' => (float) $partida->monto_comprometido,
                'monto_disponible' => (float) $partida->monto_disponible,
                'porcentaje_ejecucion' => $partida->porcentajeEjecucion(),
            ];
        });

        return response()->json([
            'data' => [
                'id' => $presupuesto->id,
                'departamento' => $presupuesto->departamento,
                'periodo' => $presupuesto->periodoFiscal,
                'monto_total' => (float) $presupuesto->monto_total,
                'monto_ejercido' => (float) $presupuesto->monto_ejercido,
                'monto_comprometido' => (float) $presupuesto->monto_comprometido,
                'monto_disponible' => (float) $presupuesto->monto_disponible,
                'porcentaje_ejecucion' => $presupuesto->porcentajeEjecucion(),
                'estado' => $presupuesto->estado->value,
                'estado_label' => $presupuesto->estado->label(),
                'creado_por' => $presupuesto->creador,
                'aprobado_por' => $presupuesto->aprobador,
                'aprobado_en' => $presupuesto->aprobado_en?->toIso8601String(),
                'notas' => $presupuesto->notas,
                'partidas' => $partidasDetalladas,
            ],
        ]);
    }

    /**
     * Crear un nuevo presupuesto con sus partidas OPEX.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'departamento_id' => 'required|exists:departamentos,id',
            'periodo_fiscal_id' => 'required|exists:periodos_fiscales,id',
            'notas' => 'nullable|string',
            'partidas' => 'required|array|min:1',
            'partidas.*.categoria_opex_id' => 'required|exists:categorias_opex,id',
            'partidas.*.monto_asignado' => 'required|numeric|min:0.01',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $montoTotal = collect($validated['partidas'])->sum('monto_asignado');

            $presupuesto = Presupuesto::create([
                'departamento_id' => $validated['departamento_id'],
                'periodo_fiscal_id' => $validated['periodo_fiscal_id'],
                'monto_total' => $montoTotal,
                'monto_ejercido' => 0,
                'monto_comprometido' => 0,
                'estado' => EstadoPresupuesto::BORRADOR,
                'creado_por' => $request->user()->id,
                'notas' => $validated['notas'] ?? null,
            ]);

            foreach ($validated['partidas'] as $partidaData) {
                $presupuesto->partidas()->create([
                    'categoria_opex_id' => $partidaData['categoria_opex_id'],
                    'monto_asignado' => $partidaData['monto_asignado'],
                    'monto_ejercido' => 0,
                    'monto_comprometido' => 0,
                ]);
            }

            return response()->json([
                'message' => 'Presupuesto creado exitosamente en estado borrador',
                'data' => $presupuesto->load('partidas.categoriaOpex'),
            ], 201);
        });
    }

    /**
     * Cambiar estado del presupuesto (aprobar, rechazar, etc.).
     */
    public function cambiarEstado(Request $request, Presupuesto $presupuesto): JsonResponse
    {
        $validated = $request->validate([
            'estado' => 'required|string|in:aprobado,rechazado,en_revision,ejecutando,cerrado',
            'motivo' => 'nullable|string',
        ]);

        $nuevoEstado = EstadoPresupuesto::from($validated['estado']);

        $actualizaciones = ['estado' => $nuevoEstado];

        if ($nuevoEstado === EstadoPresupuesto::APROBADO) {
            $actualizaciones['aprobado_por'] = $request->user()->id;
            $actualizaciones['aprobado_en'] = now();
        }

        if (!empty($validated['motivo'])) {
            $actualizaciones['notas'] = trim(($presupuesto->notas ?? '') . "\n[" . now()->toDateString() . "] Cambio a {$nuevoEstado->label()}: " . $validated['motivo']);
        }

        $presupuesto->update($actualizaciones);

        return response()->json([
            'message' => "Presupuesto actualizado al estado {$nuevoEstado->label()}",
            'data' => [
                'id' => $presupuesto->id,
                'estado' => $presupuesto->estado->value,
                'estado_label' => $presupuesto->estado->label(),
                'aprobado_por' => $presupuesto->aprobador?->name,
                'aprobado_en' => $presupuesto->aprobado_en?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Comparativa Year-over-Year (YoY) de presupuestos interdepartamentales.
     *
     * Permite comparar presupuestos de un año base contra otro año (ej. 2025 vs 2026)
     * calculando deltas absolutos, variaciones porcentuales y tendencias por departamento.
     */
    public function comparativaYoY(Request $request): JsonResponse
    {
        $request->validate([
            'anio_base' => 'required|integer',
            'anio_comparar' => 'required|integer|different:anio_base',
            'departamento_id' => 'nullable|exists:departamentos,id',
        ]);

        $anioBase = $request->integer('anio_base');
        $anioComparar = $request->integer('anio_comparar');
        $departamentoId = $request->departamento_id;

        // Departamentos a evaluar
        $deptosQuery = Departamento::whereNotNull('departamento_padre_id');
        if ($departamentoId) {
            $deptosQuery->where('id', $departamentoId);
        }
        $departamentos = $deptosQuery->get();

        $comparativa = [];
        $granTotalBase = 0;
        $granTotalComparar = 0;

        foreach ($departamentos as $depto) {
            $presupuestoBase = Presupuesto::where('departamento_id', $depto->id)
                ->whereHas('periodoFiscal', fn($q) => $q->where('anio', $anioBase)->whereNull('trimestre')->whereNull('mes'))
                ->with('partidas.categoriaOpex')
                ->first();

            $presupuestoComparar = Presupuesto::where('departamento_id', $depto->id)
                ->whereHas('periodoFiscal', fn($q) => $q->where('anio', $anioComparar)->whereNull('trimestre')->whereNull('mes'))
                ->with('partidas.categoriaOpex')
                ->first();

            $montoBase = (float) ($presupuestoBase?->monto_total ?? 0);
            $montoComparar = (float) ($presupuestoComparar?->monto_total ?? 0);
            $ejercidoBase = (float) ($presupuestoBase?->monto_ejercido ?? 0);
            $ejercidoComparar = (float) ($presupuestoComparar?->monto_ejercido ?? 0);

            $delta = $montoComparar - $montoBase;
            $variacionPorcentual = $montoBase > 0
                ? round(($delta / $montoBase) * 100, 2)
                : 0.0;

            $tendencia = match (true) {
                $variacionPorcentual > 2.0 => 'incremento',
                $variacionPorcentual < -2.0 => 'decremento',
                default => 'estable',
            };

            // Desglose por categoría OPEX comparativo
            $desgloseOpex = [];
            $categoriasMap = [];

            if ($presupuestoBase) {
                foreach ($presupuestoBase->partidas as $p) {
                    $cod = $p->categoriaOpex?->codigo ?? 'DESCONOCIDO';
                    $categoriasMap[$cod] = [
                        'categoria' => $p->categoriaOpex?->nombre,
                        'codigo' => $cod,
                        'monto_base' => (float) $p->monto_asignado,
                        'monto_comparar' => 0.0,
                    ];
                }
            }

            if ($presupuestoComparar) {
                foreach ($presupuestoComparar->partidas as $p) {
                    $cod = $p->categoriaOpex?->codigo ?? 'DESCONOCIDO';
                    if (!isset($categoriasMap[$cod])) {
                        $categoriasMap[$cod] = [
                            'categoria' => $p->categoriaOpex?->nombre,
                            'codigo' => $cod,
                            'monto_base' => 0.0,
                        ];
                    }
                    $categoriasMap[$cod]['monto_comparar'] = (float) $p->monto_asignado;
                }
            }

            foreach ($categoriasMap as $cod => $item) {
                $deltaCat = $item['monto_comparar'] - $item['monto_base'];
                $varCat = $item['monto_base'] > 0
                    ? round(($deltaCat / $item['monto_base']) * 100, 2)
                    : 0.0;

                $desgloseOpex[] = [
                    'codigo' => $cod,
                    'categoria' => $item['categoria'],
                    'monto_base' => $item['monto_base'],
                    'monto_comparar' => $item['monto_comparar'],
                    'delta' => $deltaCat,
                    'variacion_porcentual' => $varCat,
                ];
            }

            $comparativa[] = [
                'departamento' => [
                    'id' => $depto->id,
                    'nombre' => $depto->nombre,
                    'codigo' => $depto->codigo,
                ],
                'monto_base' => $montoBase,
                'monto_comparar' => $montoComparar,
                'ejercido_base' => $ejercidoBase,
                'ejercido_comparar' => $ejercidoComparar,
                'delta_absoluto' => $delta,
                'variacion_porcentual' => $variacionPorcentual,
                'tendencia' => $tendencia,
                'desglose_opex' => $desgloseOpex,
            ];

            $granTotalBase += $montoBase;
            $granTotalComparar += $montoComparar;
        }

        $deltaTotal = $granTotalComparar - $granTotalBase;
        $variacionTotal = $granTotalBase > 0
            ? round(($deltaTotal / $granTotalBase) * 100, 2)
            : 0.0;

        return response()->json([
            'metadatos' => [
                'anio_base' => $anioBase,
                'anio_comparar' => $anioComparar,
                'gran_total_base' => $granTotalBase,
                'gran_total_comparar' => $granTotalComparar,
                'delta_total' => $deltaTotal,
                'variacion_total_porcentual' => $variacionTotal,
                'tendencia_global' => $variacionTotal > 0 ? 'incremento' : 'decremento',
            ],
            'departamentos' => $comparativa,
        ]);
    }
}
