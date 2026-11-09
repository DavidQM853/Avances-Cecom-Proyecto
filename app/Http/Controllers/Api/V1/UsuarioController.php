<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class UsuarioController extends Controller
{
    /**
     * Calcula los permisos efectivos de un usuario consolidando roles vigentes
     * y restricciones OPEX desde la tabla user_rol_categoria_restriccion.
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

        // Consolidar restricciones OPEX desde user_rol_categoria_restriccion
        $userRolIds = $rolesVigentes->pluck('pivot.id')->filter()->values();
        $restriccionesOpex = [];

        if ($userRolIds->isNotEmpty()) {
            $restriccionesOpex = DB::table('user_rol_categoria_restriccion')
                ->join('categorias_opex', 'user_rol_categoria_restriccion.categoria_opex_id', '=', 'categorias_opex.id')
                ->whereIn('user_rol_categoria_restriccion.user_rol_id', $userRolIds)
                ->pluck('categorias_opex.codigo')
                ->unique()
                ->values()
                ->all();
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
                'alcance_global' => (bool) $r->pivot->alcance_global,
                'departamento_id' => $r->pivot->departamento_id,
                'vigencia_fin' => $r->pivot->vigencia_fin,
            ]),
            'permisos_efectivos' => $permisosConsolidados,
            'restricciones_opex' => $restriccionesOpex,
        ]);
    }
}
