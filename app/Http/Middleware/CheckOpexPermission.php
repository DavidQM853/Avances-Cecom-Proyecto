<?php

namespace App\Http\Middleware;

use App\Models\PartidaPresupuestal;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckOpexPermission
{
    /**
     * Valida si el usuario tiene restricciones dinámicas activas sobre la categoría OPEX solicitada.
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

        // Obtener la categoría OPEX involucrada (si viene partida_id)
        $partidaId = $request->input('partida_id');
        if ($partidaId) {
            $partida = PartidaPresupuestal::with('categoriaOpex')->find($partidaId);
            if ($partida && $partida->categoriaOpex) {
                $codigoCat = $partida->categoriaOpex->codigo;

                // Revisar restricciones en la tabla user_rol_categoria_restriccion
                foreach ($user->rolesVigentes as $rol) {
                    $userRolId = $rol->pivot->id;
                    $restringido = \Illuminate\Support\Facades\DB::table('user_rol_categoria_restriccion')
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
