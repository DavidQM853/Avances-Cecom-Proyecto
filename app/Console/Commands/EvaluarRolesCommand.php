<?php

namespace App\Console\Commands;

use App\Services\RolDinamicoService;
use Illuminate\Console\Command;

class EvaluarRolesCommand extends Command
{
    /**
     * El nombre y firma del comando en consola.
     */
    protected $signature = 'roles:evaluar {--anio= : Año fiscal para evaluar variables históricas}';

    /**
     * La descripción del comando.
     */
    protected $description = 'Ejecuta el motor de roles dinámicos evaluando variables históricas de OPEX y actualizando permisos';

    /**
     * Ejecuta el comando de consola.
     */
    public function handle(RolDinamicoService $service): int
    {
        $anio = (int) ($this->option('anio') ?? now()->format('Y'));

        $this->info("🤖 Iniciando evaluación de roles dinámicos para el ejercicio fiscal {$anio}...");

        $resultados = $service->evaluarTodos($anio);

        foreach ($resultados as $codigoDepto => $info) {
            $this->line("Departamento: <comment>{$codigoDepto}</comment>");
            if (isset($info['variables'])) {
                $ejec = $info['variables']['porcentaje_ejecucion'];
                $var = $info['variables']['variacion_interanual'];
                $this->line("  Variables: Ejecución={$ejec}%, VarYoY={$var}%");
            }

            if (!empty($info['acciones'])) {
                foreach ($info['acciones'] as $accion) {
                    $this->info("  ⚡ {$accion}");
                }
            } else {
                $this->line("  (Sin cambios en roles)");
            }
        }

        $this->info("✅ Evaluación de roles dinámicos completada.");

        return Command::SUCCESS;
    }
}
