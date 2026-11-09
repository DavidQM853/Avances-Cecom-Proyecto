<?php

namespace App\Services;

use App\Models\Departamento;
use App\Models\HistorialAsignacion;
use App\Models\Presupuesto;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Support\Collection;

class RolDinamicoService
{
    public function __construct(
        protected AgregacionTemporalService $agregacionService
    ) {}

    /**
     * Evalúa todas las reglas de roles dinámicos para todos los departamentos.
     */
    public function evaluarTodos(int $anio): array
    {
        $departamentos = Departamento::whereNotNull('departamento_padre_id')->get();
        $rolesDinamicos = Rol::dinamicos()->get();

        $resultados = [];

        foreach ($departamentos as $depto) {
            $resultados[$depto->codigo] = $this->evaluarDepartamento($depto, $rolesDinamicos, $anio);
        }

        return $resultados;
    }

    /**
     * Evalúa las reglas dinámicas para un departamento específico.
     */
    public function evaluarDepartamento(Departamento $departamento, Collection $rolesDinamicos, int $anio): array
    {
        $presupuesto = Presupuesto::where('departamento_id', $departamento->id)
            ->whereHas('periodoFiscal', fn($q) => $q->where('anio', $anio)->whereNull('trimestre')->whereNull('mes'))
            ->first();

        if (!$presupuesto) {
            return ['status' => 'sin_presupuesto'];
        }

        $porcentajeEjecucion = $presupuesto->porcentajeEjecucion();
        $analisisVariabilidad = $this->agregacionService->calcularVariabilidadMensual($anio, $departamento->id);
        $coeficienteVariacion = $analisisVariabilidad['coeficiente_variacion'] ?? 0;

        // Comparativa YoY para variación anual
        $presupuestoAnterior = Presupuesto::where('departamento_id', $departamento->id)
            ->whereHas('periodoFiscal', fn($q) => $q->where('anio', $anio - 1)->whereNull('trimestre')->whereNull('mes'))
            ->first();

        $variacionInteranual = 0.0;
        if ($presupuestoAnterior && $presupuestoAnterior->monto_total > 0) {
            $variacionInteranual = round(
                (($presupuesto->monto_total - $presupuestoAnterior->monto_total) / $presupuestoAnterior->monto_total) * 100,
                2
            );
        }

        $variablesHistoricas = [
            'porcentaje_ejecucion' => $porcentajeEjecucion,
            'variacion_interanual' => $variacionInteranual,
            'coeficiente_variacion' => $coeficienteVariacion,
        ];

        $accionesTomadas = [];

        // Usuarios directivos o analistas del departamento
        $usuariosDepto = User::where('departamento_id', $departamento->id)->get();

        foreach ($rolesDinamicos as $rol) {
            $reglas = $rol->reglas_asignacion;
            if (empty($reglas['condiciones'])) {
                continue;
            }

            $cumpleCondiciones = $this->verificarCondiciones($reglas['condiciones'], $variablesHistoricas);

            foreach ($usuariosDepto as $usuario) {
                $tieneRolActualmente = $usuario->roles()
                    ->where('roles.id', $rol->id)
                    ->wherePivot('activo', true)
                    ->exists();

                if ($cumpleCondiciones && !$tieneRolActualmente) {
                    // Asignar rol dinámicamente
                    $restricciones = $reglas['categorias_opex'] ?? null;
                    $motivo = "Asignación automática por motor de reglas (Ejecución: {$porcentajeEjecucion}%, Variación: {$variacionInteranual}%)";

                    $this->asignarRol($usuario, $rol, $departamento->id, $restricciones, $motivo);
                    $accionesTomadas[] = "Asignado rol {$rol->nombre} a {$usuario->name} ({$motivo})";
                } elseif (!$cumpleCondiciones && $tieneRolActualmente && $rol->es_dinamico) {
                    // Revocar rol dinámicamente si ya no cumple
                    $motivo = "Revocación automática: variables históricas normalizadas (Ejecución: {$porcentajeEjecucion}%)";
                    $this->revocarRol($usuario, $rol, $motivo);
                    $accionesTomadas[] = "Revocado rol {$rol->nombre} a {$usuario->name} ({$motivo})";
                }
            }
        }

        return [
            'departamento' => $departamento->codigo,
            'variables' => $variablesHistoricas,
            'acciones' => $accionesTomadas,
        ];
    }

    /**
     * Valida si un conjunto de condiciones lógicas se cumple.
     */
    private function verificarCondiciones(array $condiciones, array $variables): bool
    {
        foreach ($condiciones as $condicion) {
            $var = $condicion['variable'];
            $op = $condicion['operador'];
            $val = $condicion['valor'];

            $valorReal = $variables[$var] ?? 0;

            $resultado = match ($op) {
                '>=' => $valorReal >= $val,
                '<=' => $valorReal <= $val,
                '>' => $valorReal > $val,
                '<' => $valorReal < $val,
                '==' => $valorReal == $val,
                '!=' => $valorReal != $val,
                default => false,
            };

            if (!$resultado) {
                return false;
            }
        }

        return true;
    }

    private function asignarRol(User $user, Rol $rol, int $departamentoId, ?array $restriccionesOpex, string $motivo): void
    {
        $user->roles()->syncWithoutDetaching([
            $rol->id => [
                'departamento_id' => $departamentoId,
                'alcance_global' => false,
                'vigencia_inicio' => now()->toDateString(),
                'vigencia_fin' => now()->addMonths(3)->toDateString(),
                'activo' => true,
            ],
        ]);

        HistorialAsignacion::create([
            'user_id' => $user->id,
            'rol_id' => $rol->id,
            'accion' => 'asignar',
            'datos_nuevos' => [
                'departamento_id' => $departamentoId,
                'restricciones_opex' => $restriccionesOpex,
                'vigencia_meses' => 3,
            ],
            'motivo' => $motivo,
        ]);
    }

    private function revocarRol(User $user, Rol $rol, string $motivo): void
    {
        $user->roles()->updateExistingPivot($rol->id, ['activo' => false]);

        HistorialAsignacion::create([
            'user_id' => $user->id,
            'rol_id' => $rol->id,
            'accion' => 'revocar',
            'motivo' => $motivo,
        ]);
    }
}
