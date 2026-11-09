<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Obtiene los códigos de categorías OPEX restringidas para una asignación user_rol.
     */
    private function obtenerRestriccionesRol(?int $userRolId): array
    {
        if (!$userRolId) {
            return [];
        }

        return DB::table('user_rol_categoria_restriccion')
            ->join('categorias_opex', 'user_rol_categoria_restriccion.categoria_opex_id', '=', 'categorias_opex.id')
            ->where('user_rol_categoria_restriccion.user_rol_id', $userRolId)
            ->pluck('categorias_opex.codigo')
            ->values()
            ->all();
    }

    /**
     * Iniciar sesión y emitir token Sanctum.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        if (!$user->activo) {
            return response()->json([
                'message' => 'Tu cuenta se encuentra inactiva. Contacta al administrador.',
            ], 403);
        }

        $deviceName = $request->device_name ?? 'Dashboard CECOM Web';
        $token = $user->createToken($deviceName)->plainTextToken;

        // Cargar roles y permisos efectivos
        $roles = $user->rolesVigentes()->with('permisos')->get();
        $permisos = $roles->flatMap->permisos->pluck('slug')->unique()->values();

        return response()->json([
            'message' => 'Autenticación exitosa',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'departamento' => $user->departamento ? [
                    'id' => $user->departamento->id,
                    'nombre' => $user->departamento->nombre,
                    'codigo' => $user->departamento->codigo,
                ] : null,
                'roles' => $roles->map(fn($r) => [
                    'id' => $r->id,
                    'nombre' => $r->nombre,
                    'slug' => $r->slug,
                    'es_dinamico' => $r->es_dinamico,
                    'alcance_global' => (bool) $r->pivot->alcance_global,
                    'departamento_id' => $r->pivot->departamento_id,
                    'restricciones_opex' => $this->obtenerRestriccionesRol($r->pivot->id),
                ]),
                'permisos' => $permisos,
            ],
        ]);
    }

    /**
     * Cerrar sesión y revocar el token actual.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sesión cerrada correctamente',
        ]);
    }

    /**
     * Obtener el perfil y permisos del usuario autenticado.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $roles = $user->rolesVigentes()->with('permisos')->get();
        $permisos = $roles->flatMap->permisos->pluck('slug')->unique()->values();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'departamento' => $user->departamento ? [
                    'id' => $user->departamento->id,
                    'nombre' => $user->departamento->nombre,
                    'codigo' => $user->departamento->codigo,
                ] : null,
                'roles' => $roles->map(fn($r) => [
                    'id' => $r->id,
                    'nombre' => $r->nombre,
                    'slug' => $r->slug,
                    'es_dinamico' => $r->es_dinamico,
                    'alcance_global' => (bool) $r->pivot->alcance_global,
                    'departamento_id' => $r->pivot->departamento_id,
                    'restricciones_opex' => $this->obtenerRestriccionesRol($r->pivot->id),
                ]),
                'permisos' => $permisos,
            ],
        ]);
    }
}
