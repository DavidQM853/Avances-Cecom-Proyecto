<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Rol;
use App\Models\User;
use App\Services\RolDinamicoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RolController extends Controller
{
    public function __construct(
        protected RolDinamicoService $rolDinamicoService
    ) {}

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
     * Asignar rol a un usuario con restricciones OPEX (tabla user_rol_categoria_restriccion)
     * y vigencia temporal, respetando la restricción ck_ur_alcance de cecom_presupuestos_er.
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

        $this->rolDinamicoService->asignarRol(
            $user,
            $rol,
            $validated['departamento_id'] ?? null,
            $validated['restricciones_opex'] ?? null,
            $validated['motivo'] ?? 'Asignación manual de rol',
            $request->user()?->id
        );

        return response()->json([
            'message' => "Rol '{$rol->nombre}' asignado correctamente al usuario '{$user->name}'",
        ]);
    }

    /**
     * Revocar rol a un usuario.
     */
    public function revocarUsuario(Request $request, Rol $rol, User $user): JsonResponse
    {
        $this->rolDinamicoService->revocarRol(
            $user,
            $rol,
            $request->input('motivo', 'Revocación manual'),
            $request->user()?->id
        );

        return response()->json([
            'message' => "Rol '{$rol->nombre}' revocado al usuario '{$user->name}'",
        ]);
    }
}
