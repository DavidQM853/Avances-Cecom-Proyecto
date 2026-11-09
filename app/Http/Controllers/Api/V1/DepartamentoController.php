<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Departamento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartamentoController extends Controller
{
    /**
     * Listado de departamentos con indicadores de presupuesto.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Departamento::with(['padre:id,nombre,codigo', 'hijos:id,nombre,codigo,departamento_padre_id']);

        if ($request->boolean('solo_activos', true)) {
            $query->activos();
        }

        if ($request->boolean('solo_raiz', false)) {
            $query->raiz();
        }

        $departamentos = $query->orderBy('nombre')->get();

        return response()->json([
            'data' => $departamentos,
        ]);
    }

    /**
     * Detalle de un departamento.
     */
    public function show(Departamento $departamento): JsonResponse
    {
        $departamento->load([
            'padre',
            'hijos',
            'presupuestos' => function ($q) {
                $q->latest()->limit(5)->with('periodoFiscal');
            },
        ]);

        return response()->json([
            'data' => $departamento,
        ]);
    }

    /**
     * Crear nuevo departamento.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'codigo' => 'required|string|max:20|unique:departamentos,codigo',
            'descripcion' => 'nullable|string',
            'departamento_padre_id' => 'nullable|exists:departamentos,id',
            'activo' => 'boolean',
        ]);

        $departamento = Departamento::create($validated);

        return response()->json([
            'message' => 'Departamento creado exitosamente',
            'data' => $departamento,
        ], 201);
    }

    /**
     * Actualizar departamento.
     */
    public function update(Request $request, Departamento $departamento): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => 'sometimes|required|string|max:255',
            'codigo' => "sometimes|required|string|max:20|unique:departamentos,codigo,{$departamento->id}",
            'descripcion' => 'nullable|string',
            'departamento_padre_id' => 'nullable|exists:departamentos,id',
            'activo' => 'boolean',
        ]);

        $departamento->update($validated);

        return response()->json([
            'message' => 'Departamento actualizado exitosamente',
            'data' => $departamento,
        ]);
    }

    /**
     * Eliminar (o desactivar) departamento.
     */
    public function destroy(Departamento $departamento): JsonResponse
    {
        // Desactivación lógica para preservar integridad histórica
        $departamento->update(['activo' => false]);

        return response()->json([
            'message' => 'Departamento desactivado exitosamente',
        ]);
    }

    /**
     * Presupuestos del departamento con filtros de año.
     */
    public function presupuestos(Request $request, Departamento $departamento): JsonResponse
    {
        $query = $departamento->presupuestos()
            ->with(['periodoFiscal', 'partidas.categoriaOpex']);

        if ($request->filled('anio')) {
            $query->whereHas('periodoFiscal', function ($q) use ($request) {
                $q->where('anio', $request->integer('anio'));
            });
        }

        $presupuestos = $query->get()->map(function ($p) {
            return [
                'id' => $p->id,
                'periodo' => $p->periodoFiscal?->nombre,
                'monto_total' => (float) $p->monto_total,
                'monto_ejercido' => (float) $p->monto_ejercido,
                'monto_comprometido' => (float) $p->monto_comprometido,
                'monto_disponible' => (float) $p->monto_disponible,
                'porcentaje_ejecucion' => $p->porcentajeEjecucion(),
                'estado' => $p->estado->value,
                'estado_label' => $p->estado->label(),
                'partidas_count' => $p->partidas->count(),
            ];
        });

        return response()->json([
            'departamento' => [
                'id' => $departamento->id,
                'nombre' => $departamento->nombre,
                'codigo' => $departamento->codigo,
            ],
            'data' => $presupuestos,
        ]);
    }

    /**
     * Gastos del departamento con filtros de fechas.
     */
    public function gastos(Request $request, Departamento $departamento): JsonResponse
    {
        $query = $departamento->presupuestos()
            ->with(['gastos.partida.categoriaOpex'])
            ->get()
            ->pluck('gastos')
            ->flatten();

        if ($request->filled('desde')) {
            $query = $query->where('fecha_gasto', '>=', $request->desde);
        }

        if ($request->filled('hasta')) {
            $query = $query->where('fecha_gasto', '<=', $request->hasta);
        }

        return response()->json([
            'total_gastos' => $query->sum('monto'),
            'cantidad_transacciones' => $query->count(),
            'data' => $query->values(),
        ]);
    }
}
