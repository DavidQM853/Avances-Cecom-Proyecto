<?php

namespace Database\Seeders;

use App\Models\PeriodoFiscal;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class PeriodoFiscalSeeder extends Seeder
{
    /**
     * Seed periodos fiscales anuales, trimestrales y mensuales para 2024, 2025 y 2026.
     */
    public function run(): void
    {
        $anios = [2024, 2025, 2026];

        foreach ($anios as $anio) {
            // Periodo Anual completo
            PeriodoFiscal::firstOrCreate(
                ['anio' => $anio, 'trimestre' => null, 'mes' => null],
                [
                    'fecha_inicio' => "{$anio}-01-01",
                    'fecha_fin' => "{$anio}-12-31",
                    'cerrado' => $anio < 2026,
                ]
            );

            // Trimestres (Q1 - Q4)
            for ($trimestre = 1; $trimestre <= 4; $trimestre++) {
                $mesInicio = ($trimestre - 1) * 3 + 1;
                $mesFin = $trimestre * 3;
                $fechaInicio = Carbon::create($anio, $mesInicio, 1)->startOfMonth()->toDateString();
                $fechaFin = Carbon::create($anio, $mesFin, 1)->endOfMonth()->toDateString();

                PeriodoFiscal::firstOrCreate(
                    ['anio' => $anio, 'trimestre' => $trimestre, 'mes' => null],
                    [
                        'fecha_inicio' => $fechaInicio,
                        'fecha_fin' => $fechaFin,
                        'cerrado' => ($anio < 2026) || ($anio === 2026 && $trimestre <= 2),
                    ]
                );
            }

            // Meses (1 - 12)
            for ($mes = 1; $mes <= 12; $mes++) {
                $trimestre = (int) ceil($mes / 3);
                $fechaInicio = Carbon::create($anio, $mes, 1)->startOfMonth()->toDateString();
                $fechaFin = Carbon::create($anio, $mes, 1)->endOfMonth()->toDateString();

                PeriodoFiscal::firstOrCreate(
                    ['anio' => $anio, 'trimestre' => $trimestre, 'mes' => $mes],
                    [
                        'fecha_inicio' => $fechaInicio,
                        'fecha_fin' => $fechaFin,
                        'cerrado' => ($anio < 2026) || ($anio === 2026 && $mes < 9),
                    ]
                );
            }
        }
    }
}
