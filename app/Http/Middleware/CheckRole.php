<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Valida que el usuario tenga al menos uno de los roles requeridos.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        foreach ($roles as $rol) {
            if ($user->tieneRol($rol)) {
                return $next($request);
            }
        }

        return response()->json([
            'message' => 'Acceso no autorizado: no cuentas con el rol requerido (' . implode(', ', $roles) . ')',
        ], 403);
    }
}
