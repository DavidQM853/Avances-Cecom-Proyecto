<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\HistorialAsignacion;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RolController extends Controller
{
    /**
     * Listar roles del sistema (estáticos y dinámicos).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Rol::with('permisos');

        if ($request->has('es_dinamico')) {
            $query->where('es_dinamico', $request->boolean('es_dinamico'));
        }

        $roles = $query->get();

        return response()->json([
            'data' => $roles,
        ]);
    }

    /**
     * Detalle de un rol.
     */
    public function show(Rol $rol): JsonResponse
    {
        $rol->load('permisos');

        return response()->json([
            'data' => $rol,
        ]);
    }

    /**
     * Crear nuevo rol con reglas dinámicas opcionales.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'slug' => 'required|string|max:100|unique:roles,slug',
            'descripcion' => 'nullable|string',
            'es_dinamico' => 'boolean',
            'reglas_asignacion' => 'nullable|array',
            'permisos' => 'nullable|array',
            'permisos.*' => 'exists:permisos,id',
        ]);

        return DB::transaction(function () use ($validated) {
            $rol = Rol::create([
                'nombre' => $validated['nombre'],
                'slug' => $validated['slug'],
                'descripcion' => $validated['descripcion'] ?? null,
                'es_dinamico' => $validated['es_dinamico'] ?? false,
                'reglas_asignacion' => $validated['reglas_asignacion'] ?? null,
            ]);

            if (!empty($validated['permisos'])) {
                $rol->permisos()->sync($validated['permisos']);
            }

            return response()->json([
                'message' => 'Rol creado exitosamente',
                'data' => $rol->load('permisos'),
            ], 201);
        });
    }

    /**
     * Actualizar rol.
     */
    public function update(Request $request, Rol $rol): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => 'sometimes|required|string|max:255',
            'slug' => "sometimes|required|string|max:100|unique:roles,slug,{$rol->id}",
            'descripcion' => 'nullable|string',
            'es_dinamico' => 'boolean',
            'reglas_asignacion' => 'nullable|array',
            'permisos' => 'nullable|array',
            'permisos.*' => 'exists:permisos,id',
        ]);

        return DB::transaction(function () use ($validated, $rol) {
            $rol->update($validated);

            if (isset($validated['permisos'])) {
                $rol->permisos()->sync($validated['permisos']);
            }

            return response()->json([
                'message' => 'Rol actualizado exitosamente',
                'data' => $rol->load('permisos'),
            ]);
        });
    }

    /**
     * Asignar rol a un usuario con restricciones OPEX y vigencia temporal.
     */
    public function asignarUsuario(Request $request, Rol $rol): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'departamento_id' => 'nullable|exists:departamentos,id',
            'restricciones_opex' => 'nullable|array',
            'vigencia_inicio' => 'nullable|date',
            'vigencia_fin' => 'nullable|date|after_or_equal:vigencia_inicio',
            'motivo' => 'nullable|string',
        ]);

        $user = User::findOrFail($validated['user_id']);

        // Registrar en pivote
        $user->roles()->syncWithoutDetaching([
            $rol->id => [
                'departamento_id' => $validated['departamento_id'] ?? null,
                'restricciones_opex' => isset($validated['restricciones_opex']) ? json_encode($validated['restricciones_opex']) : null,
                'vigencia_inicio' => $validated['vigencia_inicio'] ?? null,
                'vigencia_fin' => $validated['vigencia_fin'] ?? null,
                'activo' => true,
            ],
        ]);

        // Registrar auditoría
        HistorialAsignacion::create([
            'user_id' => $user->id,
            'rol_id' => $rol->id,
            'accion' => 'asignar',
            'datos_nuevos' => $validated,
            'motivo' => $validated['motivo'] ?? 'Asignación manual de rol',
            'ejecutado_por' => $request->user()->id,
        ]);

        return response()->json([
            'message' => "Rol '{$rol->nombre}' asignado correctamente al usuario '{$user->name}'",
        ]);
    }

    /**
     * Revocar rol a un usuario.
     */
    public function revocarUsuario(Request $request, Rol $rol, User $user): JsonResponse
    {
        $user->roles()->detach($rol->id);

        HistorialAsignacion::create([
            'user_id' => $user->id,
            'rol_id' => $rol->id,
            'accion' => 'revocar',
            'motivo' => $request->input('motivo', 'Revocación manual'),
            'ejecutado_por' => $request->user()->id,
        ]);

        return response()->json([
            'message' => "Rol '{$rol->nombre}' revocado al usuario '{$user->name}'",
        ]);
    }
}
