<?php

namespace App\Http\Middleware;

use App\Models\GastoOperativo;
use App\Models\PartidaPresupuestal;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CheckOpexPermission
{
    /**
     * Valida si el usuario tiene restricciones dinámicas activas sobre la categoría OPEX solicitada
     * consultando la tabla user_rol_categoria_restriccion (tanto al crear con partida_id como al
     * editar/eliminar un gasto o reserva existente).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        // Si es admin, no tiene restricciones
        if ($user->tieneRol('admin')) {
            return $next($request);
        }

        // Determinar partida_id desde el body o desde el recurso en ruta ({gasto} o {compromiso})
        $partidaId = $request->input('partida_id');

        if (!$partidaId && $request->route('gasto')) {
            $gastoParam = $request->route('gasto');
            $partidaId = $gastoParam instanceof GastoOperativo
                ? $gastoParam->partida_id
                : GastoOperativo::where('id', $gastoParam)->value('partida_id');
        }

        if ($partidaId) {
            $partida = PartidaPresupuestal::with('categoriaOpex')->find($partidaId);
            if ($partida && $partida->categoriaOpex) {
                $codigoCat = $partida->categoriaOpex->codigo;

                foreach ($user->rolesVigentes as $rol) {
                    $userRolId = $rol->pivot->id;
                    $restringido = DB::table('user_rol_categoria_restriccion')
                        ->where('user_rol_id', $userRolId)
                        ->where('categoria_opex_id', $partida->categoria_opex_id)
                        ->exists();

                    if ($restringido) {
                        return response()->json([
                            'message' => "Acceso restringido: El rol dinámico '{$rol->nombre}' bloquea operaciones en la categoría {$partida->categoriaOpex->nombre} ({$codigoCat}) debido a políticas de control presupuestal.",
                        ], 403);
                    }
                }
            }
        }

        return $next($request);
    }
}
