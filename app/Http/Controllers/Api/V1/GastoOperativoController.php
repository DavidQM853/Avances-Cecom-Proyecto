<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BitacoraEvento;
use App\Models\CompromisoReserva;
use App\Models\GastoOperativo;
use App\Models\PartidaPresupuestal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GastoOperativoController extends Controller
{
    /**
     * Listado paginado de gastos con filtros temporales, departamento y categoría OPEX.
     */
    public function index(Request $request): JsonResponse
    {
        $query = GastoOperativo::with([
            'partida.categoriaOpex:id,nombre,codigo,tipo_gasto',
            'partida.presupuesto.departamento:id,nombre,codigo',
            'registradoPor:id,name,email',
            'compromiso:id,partida_id,referencia_documento,estado',
        ]);

        if ($request->filled('desde')) {
            $query->where('fecha_gasto', '>=', $request->desde);
        }

        if ($request->filled('hasta')) {
            $query->where('fecha_gasto', '<=', $request->hasta);
        }

        if ($request->filled('departamento_id')) {
            $query->delDepartamento($request->integer('departamento_id'));
        }

        if ($request->filled('categoria_opex_id')) {
            $query->deCategoria($request->integer('categoria_opex_id'));
        }

        if ($request->filled('concepto')) {
            $query->where('concepto', 'like', '%' . $request->concepto . '%');
        }

        $gastos = $query->orderByDesc('fecha_gasto')->paginate($request->integer('per_page', 15));

        return response()->json($gastos);
    }

    /**
     * Detalle de un gasto operativo.
     */
    public function show(GastoOperativo $gasto): JsonResponse
    {
        $gasto->load([
            'partida.categoriaOpex',
            'partida.presupuesto.departamento',
            'registradoPor:id,name,email',
            'compromiso',
        ]);

        return response()->json([
            'data' => $gasto,
        ]);
    }

    /**
     * Registrar un nuevo gasto operativo y conciliar fondos de la partida y reserva.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'partida_id' => 'required|exists:partidas_presupuestales,id',
            'compromiso_id' => 'nullable|exists:compromisos_reserva,id',
            'monto' => 'required|numeric|min:0.01',
            'concepto' => 'required|string|max:255',
            'referencia_documento' => 'nullable|string|max:255',
            'fecha_gasto' => 'required|date',
            'metadata' => 'nullable|array',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $partida = PartidaPresupuestal::with('presupuesto')->lockForUpdate()->findOrFail($validated['partida_id']);

            // Validar que el presupuesto permita gastos
            if (!$partida->presupuesto->estado->permiteGastos()) {
                return response()->json([
                    'message' => "El presupuesto está en estado '{$partida->presupuesto->estado->label()}' y no permite registrar gastos.",
                ], 422);
            }

            // Si está vinculado a una reserva (compromiso_id), validar FK compuesta (misma partida)
            if (!empty($validated['compromiso_id'])) {
                $reserva = CompromisoReserva::where('id', $validated['compromiso_id'])
                    ->where('partida_id', $partida->id)
                    ->first();

                if (!$reserva) {
                    return response()->json([
                        'message' => 'El compromiso o reserva seleccionado no pertenece a la partida presupuestal indicada.',
                    ], 422);
                }
            }

            // Validar suficiencia presupuestal
            if (!$partida->tieneFondos((float) $validated['monto'])) {
                return response()->json([
                    'message' => "Fondos insuficientes en la partida. Disponible: $ {$partida->monto_disponible}, Solicitado: $ {$validated['monto']}",
                ], 422);
            }

            $gasto = GastoOperativo::create([
                'partida_id' => $validated['partida_id'],
                'registrado_por' => $request->user()->id,
                'compromiso_id' => $validated['compromiso_id'] ?? null,
                'monto' => $validated['monto'],
                'concepto' => $validated['concepto'],
                'referencia_documento' => $validated['referencia_documento'] ?? null,
                'fecha_gasto' => $validated['fecha_gasto'],
                'fecha_registro' => now()->toDateString(),
                'metadata' => $validated['metadata'] ?? null,
            ]);

            // Actualizar montos ejercidos
            $partida->increment('monto_ejercido', $validated['monto']);
            $partida->presupuesto->increment('monto_ejercido', $validated['monto']);

            // Registrar en bitácora de auditoría
            BitacoraEvento::create([
                'actor_id' => $request->user()->id,
                'entidad_tipo' => 'gastos_operativos',
                'entidad_id' => $gasto->id,
                'accion' => 'crear',
                'motivo' => "Registro de gasto operativo: {$gasto->concepto}",
                'valores_despues' => [
                    'partida_id' => $gasto->partida_id,
                    'monto' => (float) $gasto->monto,
                    'fecha_gasto' => $validated['fecha_gasto'],
                ],
            ]);

            return response()->json([
                'message' => 'Gasto operativo registrado exitosamente',
                'data' => $gasto->load('partida.categoriaOpex'),
            ], 201);
        });
    }

    /**
     * Actualizar gasto y ajustar conciliación presupuestal.
     */
    public function update(Request $request, GastoOperativo $gasto): JsonResponse
    {
        $validated = $request->validate([
            'monto' => 'sometimes|required|numeric|min:0.01',
            'concepto' => 'sometimes|required|string|max:255',
            'referencia_documento' => 'nullable|string|max:255',
            'fecha_gasto' => 'sometimes|required|date',
            'metadata' => 'nullable|array',
        ]);

        return DB::transaction(function () use ($validated, $gasto, $request) {
            $montoAnterior = (float) $gasto->monto;

            if (isset($validated['monto'])) {
                $diferenciaMonto = $validated['monto'] - $montoAnterior;
                $partida = $gasto->partida()->lockForUpdate()->first();

                if ($diferenciaMonto > 0 && !$partida->tieneFondos($diferenciaMonto)) {
                    return response()->json([
                        'message' => 'Fondos insuficientes para el incremento solicitado.',
                    ], 422);
                }

                $partida->increment('monto_ejercido', $diferenciaMonto);
                $partida->presupuesto->increment('monto_ejercido', $diferenciaMonto);
            }

            $gasto->update($validated);

            BitacoraEvento::create([
                'actor_id' => $request->user()?->id,
                'entidad_tipo' => 'gastos_operativos',
                'entidad_id' => $gasto->id,
                'accion' => 'actualizar',
                'motivo' => 'Actualización de gasto operativo y conciliación de saldos',
                'valores_antes' => ['monto' => $montoAnterior],
                'valores_despues' => ['monto' => (float) $gasto->monto],
            ]);

            return response()->json([
                'message' => 'Gasto operativo actualizado exitosamente',
                'data' => $gasto,
            ]);
        });
    }

    /**
     * Eliminar un gasto y reintegrar monto al presupuesto.
     */
    public function destroy(Request $request, GastoOperativo $gasto): JsonResponse
    {
        return DB::transaction(function () use ($gasto, $request) {
            $monto = (float) $gasto->monto;
            $gastoId = $gasto->id;
            $partida = $gasto->partida()->lockForUpdate()->first();

            $gasto->delete();

            $partida->decrement('monto_ejercido', $monto);
            $partida->presupuesto->decrement('monto_ejercido', $monto);

            BitacoraEvento::create([
                'actor_id' => $request->user()?->id,
                'entidad_tipo' => 'gastos_operativos',
                'entidad_id' => $gastoId,
                'accion' => 'eliminar',
                'motivo' => 'Eliminación de gasto operativo y reintegro de saldo a la partida',
                'valores_antes' => ['monto' => $monto, 'partida_id' => $partida->id],
            ]);

            return response()->json([
                'message' => 'Gasto operativo eliminado y saldo reintegrado al presupuesto',
            ]);
        });
    }
}
