<?php

namespace App\Console\Commands;

use App\Enums\PeriodoTipo;
use App\Models\AgregacionTemporal;
use App\Models\Departamento;
use App\Services\AgregacionTemporalService;
use Illuminate\Console\Command;

class GenerarAgregacionesCommand extends Command
{
    /**
     * El nombre y firma del comando en consola.
     */
    protected $signature = 'agregaciones:generar 
                            {--anio= : Año específico para procesar (por defecto año actual)}
                            {--periodo= : Tipo de período específico (mensual, trimestral, etc.)}';

    /**
     * La descripción del comando.
     */
    protected $description = 'Pre-calcula y almacena en la tabla agregaciones_temporales para optimización de consultas de alto volumen';

    /**
     * Ejecuta el comando de consola.
     */
    public function handle(AgregacionTemporalService $service): int
    {
        $anio = (int) ($this->option('anio') ?? now()->format('Y'));
        $desde = "{$anio}-01-01";
        $hasta = "{$anio}-12-31";

        $periodos = $this->option('periodo')
            ? [PeriodoTipo::from($this->option('periodo'))]
            : [
                PeriodoTipo::MENSUAL,
                PeriodoTipo::TRIMESTRAL,
                PeriodoTipo::SEMESTRAL,
                PeriodoTipo::ANUAL,
            ];

        $departamentos = Departamento::whereNotNull('departamento_padre_id')->get();

        $this->info("🚀 Iniciando pre-cálculo de agregaciones para el ejercicio fiscal {$anio}...");

        $totalRegistros = 0;

        foreach ($periodos as $periodo) {
            $this->line("Procesando período: <comment>{$periodo->label()}</comment>");

            foreach ($departamentos as $depto) {
                $calculos = $service->calcular($periodo, $desde, $hasta, $depto->id);

                foreach ($calculos as $c) {
                    AgregacionTemporal::updateOrCreate(
                        [
                            'departamento_id' => $depto->id,
                            'periodo_tipo' => $periodo,
                            'fecha_inicio' => $c['fecha_inicio'],
                        ],
                        [
                            'fecha_fin' => $c['fecha_fin'],
                            'monto_total' => $c['monto_total'],
                            'monto_promedio' => $c['monto_promedio'],
                            'variacion_porcentual' => $c['variacion_porcentual'],
                            'cantidad_transacciones' => $c['cantidad_transacciones'],
                            'desglose' => $c['desglose_categorias'],
                        ]
                    );

                    $totalRegistros++;
                }
            }
        }

        $this->info("✅ Se pre-calcularon y guardaron exitosamente {$totalRegistros} agregaciones temporales.");

        return Command::SUCCESS;
    }
}
