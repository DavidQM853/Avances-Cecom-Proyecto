<?php

namespace App\Services;

use App\Enums\PeriodoTipo;
use App\Models\GastoOperativo;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AgregacionTemporalService
{
    /**
     * Calcula agregaciones dinámicas para cualquier tipo de período con deltas y estadísticas.
     */
    public function calcular(
        PeriodoTipo $periodo,
        string $desde,
        string $hasta,
        ?int $departamentoId = null,
        ?int $categoriaOpexId = null
    ): Collection {
        return match ($periodo) {
            PeriodoTipo::DIARIO => $this->calcularDiario($desde, $hasta, $departamentoId, $categoriaOpexId),
            PeriodoTipo::QUINCENAL => $this->calcularQuincenal($desde, $hasta, $departamentoId, $categoriaOpexId),
            PeriodoTipo::MENSUAL => $this->calcularMensual($desde, $hasta, $departamentoId, $categoriaOpexId),
            PeriodoTipo::TRIMESTRAL => $this->calcularTrimestral($desde, $hasta, $departamentoId, $categoriaOpexId),
            PeriodoTipo::SEMESTRAL => $this->calcularSemestral($desde, $hasta, $departamentoId, $categoriaOpexId),
            PeriodoTipo::ANUAL => $this->calcularAnual($desde, $hasta, $departamentoId, $categoriaOpexId),
        };
    }

    /**
     * Agregación Diaria: agrupa por día natural.
     */
    public function calcularDiario(string $desde, string $hasta, ?int $departamentoId, ?int $categoriaOpexId): Collection
    {
        $gastos = $this->queryGastos($desde, $hasta, $departamentoId, $categoriaOpexId)->get();

        $agrupados = $gastos->groupBy(function ($item) {
            return $item->fecha_gasto->toDateString();
        });

        return $this->procesarGrupos($agrupados, function ($fecha, $items) {
            return [
                'etiqueta' => $fecha,
                'fecha_inicio' => $fecha,
                'fecha_fin' => $fecha,
                'periodo_tipo' => PeriodoTipo::DIARIO->value,
            ];
        });
    }

    /**
     * Agregación Quincenal: agrupa 1-15 y 16-fin de mes.
     */
    public function calcularQuincenal(string $desde, string $hasta, ?int $departamentoId, ?int $categoriaOpexId): Collection
    {
        $gastos = $this->queryGastos($desde, $hasta, $departamentoId, $categoriaOpexId)->get();

        $agrupados = $gastos->groupBy(function ($item) {
            $dia = (int) $item->fecha_gasto->format('d');
            $quincena = $dia <= 15 ? 'Q1' : 'Q2';
            return $item->fecha_gasto->format('Y-m') . '-' . $quincena;
        });

        return $this->procesarGrupos($agrupados, function ($clave, $items) {
            [$anio, $mes, $q] = explode('-', $clave);
            $fechaBase = Carbon::create((int)$anio, (int)$mes, 1);

            if ($q === 'Q1') {
                $inicio = $fechaBase->startOfMonth()->toDateString();
                $fin = $fechaBase->copy()->day(15)->toDateString();
                $etiqueta = "1ra Quincena {$fechaBase->translatedFormat('F Y')}";
            } else {
                $inicio = $fechaBase->copy()->day(16)->toDateString();
                $fin = $fechaBase->copy()->endOfMonth()->toDateString();
                $etiqueta = "2da Quincena {$fechaBase->translatedFormat('F Y')}";
            }

            return [
                'etiqueta' => $etiqueta,
                'fecha_inicio' => $inicio,
                'fecha_fin' => $fin,
                'periodo_tipo' => PeriodoTipo::QUINCENAL->value,
            ];
        });
    }

    /**
     * Agregación Mensual: agrupa por mes calendario.
     */
    public function calcularMensual(string $desde, string $hasta, ?int $departamentoId, ?int $categoriaOpexId): Collection
    {
        $gastos = $this->queryGastos($desde, $hasta, $departamentoId, $categoriaOpexId)->get();

        $agrupados = $gastos->groupBy(function ($item) {
            return $item->fecha_gasto->format('Y-m');
        });

        return $this->procesarGrupos($agrupados, function ($mesAnio, $items) {
            $carbon = Carbon::createFromFormat('Y-m', $mesAnio);
            return [
                'etiqueta' => $carbon->translatedFormat('F Y'),
                'fecha_inicio' => $carbon->startOfMonth()->toDateString(),
                'fecha_fin' => $carbon->endOfMonth()->toDateString(),
                'periodo_tipo' => PeriodoTipo::MENSUAL->value,
            ];
        });
    }

    /**
     * Agregación Trimestral: Q1 (Ene-Mar), Q2 (Abr-Jun), Q3 (Jul-Sep), Q4 (Oct-Dic).
     */
    public function calcularTrimestral(string $desde, string $hasta, ?int $departamentoId, ?int $categoriaOpexId): Collection
    {
        $gastos = $this->queryGastos($desde, $hasta, $departamentoId, $categoriaOpexId)->get();

        $agrupados = $gastos->groupBy(function ($item) {
            $trimestre = (int) ceil(((int)$item->fecha_gasto->format('m')) / 3);
            return $item->fecha_gasto->format('Y') . "-Q{$trimestre}";
        });

        return $this->procesarGrupos($agrupados, function ($clave, $items) {
            [$anio, $qStr] = explode('-', $clave);
            $q = (int) str_replace('Q', '', $qStr);
            $mesInicio = ($q - 1) * 3 + 1;
            $mesFin = $q * 3;

            $inicio = Carbon::create((int)$anio, $mesInicio, 1)->startOfMonth()->toDateString();
            $fin = Carbon::create((int)$anio, $mesFin, 1)->endOfMonth()->toDateString();

            return [
                'etiqueta' => "{$qStr} {$anio}",
                'fecha_inicio' => $inicio,
                'fecha_fin' => $fin,
                'periodo_tipo' => PeriodoTipo::TRIMESTRAL->value,
            ];
        });
    }

    /**
     * Agregación Semestral: S1 (Ene-Jun), S2 (Jul-Dic).
     */
    public function calcularSemestral(string $desde, string $hasta, ?int $departamentoId, ?int $categoriaOpexId): Collection
    {
        $gastos = $this->queryGastos($desde, $hasta, $departamentoId, $categoriaOpexId)->get();

        $agrupados = $gastos->groupBy(function ($item) {
            $mes = (int)$item->fecha_gasto->format('m');
            $semestre = $mes <= 6 ? 'S1' : 'S2';
            return $item->fecha_gasto->format('Y') . "-{$semestre}";
        });

        return $this->procesarGrupos($agrupados, function ($clave, $items) {
            [$anio, $sStr] = explode('-', $clave);
            $esS1 = $sStr === 'S1';

            $inicio = Carbon::create((int)$anio, $esS1 ? 1 : 7, 1)->startOfMonth()->toDateString();
            $fin = Carbon::create((int)$anio, $esS1 ? 6 : 12, 1)->endOfMonth()->toDateString();

            return [
                'etiqueta' => "{$sStr} {$anio}",
                'fecha_inicio' => $inicio,
                'fecha_fin' => $fin,
                'periodo_tipo' => PeriodoTipo::SEMESTRAL->value,
            ];
        });
    }

    /**
     * Agregación Anual: agrupa por año calendario.
     */
    public function calcularAnual(string $desde, string $hasta, ?int $departamentoId, ?int $categoriaOpexId): Collection
    {
        $gastos = $this->queryGastos($desde, $hasta, $departamentoId, $categoriaOpexId)->get();

        $agrupados = $gastos->groupBy(function ($item) {
            return $item->fecha_gasto->format('Y');
        });

        return $this->procesarGrupos($agrupados, function ($anio, $items) {
            return [
                'etiqueta' => "Año {$anio}",
                'fecha_inicio' => "{$anio}-01-01",
                'fecha_fin' => "{$anio}-12-31",
                'periodo_tipo' => PeriodoTipo::ANUAL->value,
            ];
        });
    }

    /**
     * Análisis de variabilidad intermensual (Objetivo 6 del anteproyecto).
     *
     * Calcula media, varianza, desviación estándar y coeficiente de variación (CV)
     * mensual por departamento o global.
     */
    public function calcularVariabilidadMensual(int $anio, ?int $departamentoId = null): array
    {
        $desde = "{$anio}-01-01";
        $hasta = "{$anio}-12-31";

        $mensuales = $this->calcularMensual($desde, $hasta, $departamentoId, null);

        $montos = $mensuales->pluck('monto_total')->toArray();
        $n = count($montos);

        if ($n === 0) {
            return [
                'anio' => $anio,
                'meses_registrados' => 0,
                'promedio_mensual' => 0,
                'desviacion_estandar' => 0,
                'coeficiente_variacion' => 0,
                'serie_mensual' => [],
            ];
        }

        $media = array_sum($montos) / $n;

        $sumaCuadrados = 0;
        foreach ($montos as $m) {
            $sumaCuadrados += pow($m - $media, 2);
        }

        $desviacionEstandar = ($n > 1) ? sqrt($sumaCuadrados / ($n - 1)) : 0;
        $coeficienteVariacion = $media > 0 ? round(($desviacionEstandar / $media) * 100, 2) : 0;

        return [
            'anio' => $anio,
            'meses_registrados' => $n,
            'gran_total' => round(array_sum($montos), 2),
            'promedio_mensual' => round($media, 2),
            'desviacion_estandar' => round($desviacionEstandar, 2),
            'coeficiente_variacion' => $coeficienteVariacion,
            'clasificacion_variabilidad' => match (true) {
                $coeficienteVariacion < 10 => 'Baja (Gasto predecible y homogéneo)',
                $coeficienteVariacion <= 25 => 'Moderada (Fluctuaciones normales de operación)',
                default => 'Alta (Gasto volátil — requiere atención)',
            },
            'serie_mensual' => $mensuales,
        ];
    }

    // ─── Helpers de consulta y procesamiento ───────────────

    private function queryGastos(string $desde, string $hasta, ?int $departamentoId, ?int $categoriaOpexId)
    {
        $query = GastoOperativo::with([
            'partida.categoriaOpex:id,nombre,codigo,tipo_gasto',
            'partida.presupuesto.departamento:id,nombre,codigo',
        ])
        ->whereBetween('fecha_gasto', [$desde, $hasta]);

        if ($departamentoId) {
            $query->delDepartamento($departamentoId);
        }

        if ($categoriaOpexId) {
            $query->deCategoria($categoriaOpexId);
        }

        return $query->orderBy('fecha_gasto');
    }

    private function procesarGrupos(Collection $agrupados, callable $metaBuilder): Collection
    {
        $resultado = collect();
        $montoAnterior = null;

        foreach ($agrupados->sortKeys() as $clave => $items) {
            $meta = $metaBuilder($clave, $items);
            $total = (float) $items->sum('monto');
            $cantidad = $items->count();
            $promedio = $cantidad > 0 ? round($total / $cantidad, 2) : 0.0;

            // Variación respecto al período inmediatamente anterior
            $variacionPorcentual = 0.0;
            if ($montoAnterior !== null && $montoAnterior > 0) {
                $variacionPorcentual = round((($total - $montoAnterior) / $montoAnterior) * 100, 2);
            }

            // Desglose por categoría OPEX
            $porCategoria = $items->groupBy(fn($g) => $g->partida?->categoriaOpex?->codigo ?? 'OTROS')
                ->map(function ($catItems, $cod) {
                    return [
                        'codigo' => $cod,
                        'categoria' => $catItems->first()->partida?->categoriaOpex?->nombre ?? 'Otros',
                        'monto' => round((float)$catItems->sum('monto'), 2),
                        'transacciones' => $catItems->count(),
                    ];
                })->values();

            $resultado->push([
                'etiqueta' => $meta['etiqueta'],
                'periodo_tipo' => $meta['periodo_tipo'],
                'fecha_inicio' => $meta['fecha_inicio'],
                'fecha_fin' => $meta['fecha_fin'],
                'monto_total' => round($total, 2),
                'monto_promedio' => $promedio,
                'cantidad_transacciones' => $cantidad,
                'variacion_porcentual' => $variacionPorcentual,
                'tendencia' => match (true) {
                    $variacionPorcentual > 2.0 => 'alza',
                    $variacionPorcentual < -2.0 => 'baja',
                    default => 'estable',
                },
                'desglose_categorias' => $porCategoria,
            ]);

            $montoAnterior = $total;
        }

        return $resultado;
    }
}
