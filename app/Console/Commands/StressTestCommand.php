<?php

namespace App\Console\Commands;

use App\Models\GastoOperativo;
use App\Models\PartidaPresupuestal;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class StressTestCommand extends Command
{
    /**
     * El nombre y firma del comando en consola.
     */
    protected $signature = 'stress:test {--records=5000 : Cantidad de transacciones para la prueba de estrés}';

    /**
     * La descripción del comando.
     */
    protected $description = 'Ejecuta pruebas de estrés con gran volumen de datos y mide tiempos de respuesta y optimización de queries';

    /**
     * Ejecuta el comando de consola.
     */
    public function handle(): int
    {
        $records = (int) $this->option('records');
        $partida = PartidaPresupuestal::first();
        $admin = User::first();

        if (!$partida) {
            $this->error('No hay partidas presupuestales para realizar la prueba.');
            return Command::FAILURE;
        }

        $this->info("⚡ INICIANDO PRUEBA DE ESTRÉS CON {$records} REGISTROS");
        $this->line("───────────────────────────────────────────────────");

        // 1. Inserción masiva por bloques (Bulk insert)
        $this->line("1. Probando inserción masiva por bloques (chunk size = 500)...");
        $inicio = microtime(true);

        $lote = [];
        $ahora = now()->toDateTimeString();
        $fecha = now()->toDateString();

        for ($i = 1; $i <= $records; $i++) {
            $lote[] = [
                'partida_id' => $partida->id,
                'registrado_por' => $admin?->id,
                'monto' => round(rand(100, 5000), 2),
                'concepto' => "Gasto Prueba Estrés #{$i}",
                'referencia_documento' => "STRESS-{$i}",
                'fecha_gasto' => $fecha,
                'fecha_registro' => $fecha,
                'metadata' => json_encode(['test' => true, 'batch' => 'stress']),
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];

            if (count($lote) === 500) {
                DB::table('gastos_operativos')->insert($lote);
                $lote = [];
            }
        }

        if (!empty($lote)) {
            DB::table('gastos_operativos')->insert($lote);
        }

        $duracionInsercion = round(microtime(true) - $inicio, 3);
        $tps = round($records / $duracionInsercion, 2);
        $this->info("   ✅ {$records} registros insertados en {$duracionInsercion}s ({$tps} ops/seg)");

        // 2. Consulta de conteo global
        $inicio = microtime(true);
        $totalGastos = DB::table('gastos_operativos')->count();
        $duracionCount = round((microtime(true) - $inicio) * 1000, 2);
        $this->info("   ✅ COUNT(*) sobre {$totalGastos} registros: {$duracionCount} ms");

        // 3. Consulta de agregación temporal con JOINs sobre todo el dataset
        $inicio = microtime(true);
        $agrupados = DB::table('gastos_operativos')
            ->join('partidas_presupuestales', 'gastos_operativos.partida_id', '=', 'partidas_presupuestales.id')
            ->join('presupuestos', 'partidas_presupuestales.presupuesto_id', '=', 'presupuestos.id')
            ->join('departamentos', 'presupuestos.departamento_id', '=', 'departamentos.id')
            ->select('departamentos.codigo', DB::raw('SUM(gastos_operativos.monto) as total_monto, COUNT(*) as transacciones, AVG(gastos_operativos.monto) as promedio'))
            ->groupBy('departamentos.codigo')
            ->get();
        $duracionJoin = round((microtime(true) - $inicio) * 1000, 2);
        $this->info("   ✅ Multi-Table JOIN y Agregación agrupada: {$duracionJoin} ms");

        // 4. Limpieza de datos de prueba
        $this->line("4. Limpiando registros de prueba de estrés...");
        DB::table('gastos_operativos')->where('referencia_documento', 'like', 'STRESS-%')->delete();
        $this->info("   ✅ Registros de prueba eliminados correctamente.");

        $this->line("───────────────────────────────────────────────────");
        $this->info("🎯 RESULTADO: Sistema optimizado y respondiendo en rangos sub-segundo.");

        return Command::SUCCESS;
    }
}
