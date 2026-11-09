<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BitacoraEvento;
use App\Models\CompromisoReserva;
use App\Models\PartidaPresupuestal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompromisoReservaController extends Controller
{
    /**
     * Listado de compromisos y reservas presupuestales.
     */
    public function index(Request $request): JsonResponse
    {
        $query = CompromisoReserva::with([
            'partida.categoriaOpex:id,nombre,codigo',
            'partida.presupuesto.departamento:id,nombre,codigo',
            'creador:id,name,email',
        ]);

        if ($request->filled('partida_id')) {
            $query->where('partida_id', $request->integer('partida_id'));
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        return response()->json($query->latest()->paginate($request->integer('per_page', 15)));
    }

    /**
     * Crear una reserva presupuestal y comprometer saldo en la partida y el presupuesto.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'partida_id' => 'required|exists:partidas_presupuestales,id',
            'referencia_documento' => 'required|string|max:255',
            'monto_original' => 'required|numeric|min:0.01',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $partida = PartidaPresupuestal::with('presupuesto')->lockForUpdate()->findOrFail($validated['partida_id']);

            if (!$partida->tieneFondos((float) $validated['monto_original'])) {
                return response()->json([
                    'message' => "Fondos disponibles insuficientes para comprometer. Disponible: $ {$partida->monto_disponible}",
                ], 422);
            }

            $reserva = CompromisoReserva::create([
                'partida_id' => $partida->id,
                'creado_por' => $request->user()->id,
                'referencia_documento' => $validated['referencia_documento'],
                'monto_original' => $validated['monto_original'],
                'monto_liberado' => 0,
                'estado' => 'activa',
            ]);

            $partida->increment('monto_comprometido', $validated['monto_original']);
            $partida->presupuesto->increment('monto_comprometido', $validated['monto_original']);

            BitacoraEvento::create([
                'actor_id' => $request->user()->id,
                'entidad_tipo' => 'compromisos_reserva',
                'entidad_id' => $reserva->id,
                'accion' => 'crear',
                'motivo' => "Creación de compromiso/reserva: {$reserva->referencia_documento}",
                'valores_despues' => [
                    'partida_id' => $partida->id,
                    'monto_original' => (float) $reserva->monto_original,
                ],
            ]);

            return response()->json([
                'message' => 'Compromiso presupuestal registrado exitosamente',
                'data' => $reserva,
            ], 201);
        });
    }

    /**
     * Liberar total o parcialmente una reserva presupuestal.
     */
    public function liberar(Request $request, CompromisoReserva $compromiso): JsonResponse
    {
        $validated = $request->validate([
            'monto_liberar' => 'required|numeric|min:0.01',
            'motivo' => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($validated, $compromiso, $request) {
            $pendiente = $compromiso->saldoPendiente();
            $montoLiberar = (float) $validated['monto_liberar'];

            if ($montoLiberar > $pendiente) {
                return response()->json([
                    'message' => "El monto a liberar ($ {$montoLiberar}) excede el saldo pendiente de la reserva ($ {$pendiente}).",
                ], 422);
            }

            $nuevoLiberado = round((float) $compromiso->monto_liberado + $montoLiberar, 2);
            $nuevoEstado = ($pendiente - $montoLiberar) <= 0.001 ? 'cerrada' : 'activa';

            $compromiso->update([
                'monto_liberado' => $nuevoLiberado,
                'estado' => $nuevoEstado,
            ]);

            $partida = $compromiso->partida()->lockForUpdate()->first();
            $partida->decrement('monto_comprometido', $montoLiberar);
            $partida->presupuesto->decrement('monto_comprometido', $montoLiberar);

            BitacoraEvento::create([
                'actor_id' => $request->user()->id,
                'entidad_tipo' => 'compromisos_reserva',
                'entidad_id' => $compromiso->id,
                'accion' => 'liberar',
                'motivo' => $validated['motivo'] ?? 'Liberación de saldo comprometido',
                'valores_despues' => [
                    'monto_liberado' => $nuevoLiberado,
                    'estado' => $nuevoEstado,
                ],
            ]);

            return response()->json([
                'message' => 'Saldo liberado correctamente',
                'data' => $compromiso->fresh(),
            ]);
        });
    }
}
