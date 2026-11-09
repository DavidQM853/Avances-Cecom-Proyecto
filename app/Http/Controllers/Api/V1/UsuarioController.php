<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class UsuarioController extends Controller
{
    /**
     * Calcula los permisos efectivos de un usuario consolidando roles vigentes y restricciones OPEX.
     */
    public function permisosEfectivos(User $user): JsonResponse
    {
        $rolesVigentes = $user->rolesVigentes()->with('permisos')->get();

        $permisosConsolidados = $rolesVigentes->flatMap->permisos->map(function ($p) {
            return [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'slug' => $p->slug,
                'modulo' => $p->modulo,
            ];
        })->unique('slug')->values();

        // Consolidar restricciones OPEX de los roles
        $restriccionesOpex = [];
        foreach ($rolesVigentes as $rol) {
            $raw = $rol->pivot->restricciones_opex;
            if ($raw) {
                $dec = is_array($raw) ? $raw : json_decode($raw, true);
                if (is_array($dec)) {
                    $restriccionesOpex = array_merge($restriccionesOpex, $dec);
                }
            }
        }

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
            ],
            'roles_activos' => $rolesVigentes->map(fn($r) => [
                'id' => $r->id,
                'nombre' => $r->nombre,
                'slug' => $r->slug,
                'es_dinamico' => $r->es_dinamico,
                'vigencia_fin' => $r->pivot->vigencia_fin,
            ]),
            'permisos_efectivos' => $permisosConsolidados,
            'restricciones_opex' => array_values(array_unique($restriccionesOpex)),
        ]);
    }
}
