<?php

namespace App\Services;

use App\Enums\PeriodoTipo;
use App\Models\AgregacionTemporal;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AgregacionTemporalService
{
    /**
     * Calcula agregaciones dinámicas para cualquier tipo de período con deltas y estadísticas.
     *
     * Si $usarPrecalculado = true y existen registros pre-calculados en agregaciones_temporales
     * para el departamento y rango solicitados, los retorna directamente (O(1) por índice
     * uq_agregacion_contexto / idx_agg_tipo_inicio). En caso contrario, ejecuta la agregación
     * optimizada en SQL con GROUP BY sobre gastos_operativos.
     */
    public function calcular(
        PeriodoTipo $periodo,
        string $desde,
        string $hasta,
        ?int $departamentoId = null,
        ?int $categoriaOpexId = null,
        bool $usarPrecalculado = false
    ): Collection {
        if ($usarPrecalculado && $departamentoId !== null) {
            $precalculados = AgregacionTemporal::query()
                ->where('departamento_id', $departamentoId)
                ->where('categoria_clave', $categoriaOpexId ?? 0)
                ->where('periodo_tipo', $periodo->value)
                ->where('fecha_inicio', '>=', $desde)
                ->where('fecha_fin', '<=', $hasta)
                ->orderBy('fecha_inicio')
                ->get();

            if ($precalculados->isNotEmpty()) {
                return $precalculados->map(fn (AgregacionTemporal $row) => [
                    'etiqueta' => $this->construirEtiquetaDesdeFechas($periodo, $row->fecha_inicio->toDateString()),
                    'periodo_tipo' => $periodo->value,
                    'fecha_inicio' => $row->fecha_inicio->toDateString(),
                    'fecha_fin' => $row->fecha_fin->toDateString(),
                    'monto_total' => round((float) $row->monto_total, 2),
                    'monto_promedio' => round((float) $row->monto_promedio, 2),
                    'cantidad_transacciones' => (int) $row->cantidad_transacciones,
                    'variacion_porcentual' => round((float) ($row->variacion_porcentual ?? 0), 2),
                    'tendencia' => match (true) {
                        ($row->variacion_porcentual ?? 0) > 2.0 => 'alza',
                        ($row->variacion_porcentual ?? 0) < -2.0 => 'baja',
                        default => 'estable',
                    },
                    'desglose_categorias' => $row->desglose ?? [],
                    'fuente' => 'precalculado',
                ]);
            }
        }

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
     * Agregación Diaria optimizada en SQL: agrupa por fecha_gasto + categoría OPEX.
     */
    public function calcularDiario(string $desde, string $hasta, ?int $departamentoId, ?int $categoriaOpexId): Collection
    {
        return $this->ejecutarAgregacionSql(
            PeriodoTipo::DIARIO,
            $desde,
            $hasta,
            $departamentoId,
            $categoriaOpexId,
            fn (string $driver) => 'gastos_operativos.fecha_gasto',
            fn (string $fecha) => [
                'etiqueta' => $fecha,
                'fecha_inicio' => $fecha,
                'fecha_fin' => $fecha,
                'periodo_tipo' => PeriodoTipo::DIARIO->value,
            ]
        );
    }

    /**
     * Agregación Quincenal optimizada en SQL: agrupa 1-15 (Q1) y 16-fin de mes (Q2).
     */
    public function calcularQuincenal(string $desde, string $hasta, ?int $departamentoId, ?int $categoriaOpexId): Collection
    {
        return $this->ejecutarAgregacionSql(
            PeriodoTipo::QUINCENAL,
            $desde,
            $hasta,
            $departamentoId,
            $categoriaOpexId,
            function (string $driver): string {
                if ($driver === 'sqlite') {
                    return "strftime('%Y-%m', gastos_operativos.fecha_gasto) || CASE WHEN CAST(strftime('%d', gastos_operativos.fecha_gasto) AS INTEGER) <= 15 THEN '-Q1' ELSE '-Q2' END";
                }
                return "CONCAT(DATE_FORMAT(gastos_operativos.fecha_gasto, '%Y-%m'), IF(DAY(gastos_operativos.fecha_gasto) <= 15, '-Q1', '-Q2'))";
            },
            function (string $clave): array {
                [$anio, $mes, $q] = explode('-', $clave);
                $fechaBase = Carbon::create((int) $anio, (int) $mes, 1);

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
            }
        );
    }

    /**
     * Agregación Mensual optimizada en SQL: agrupa por mes calendario (YYYY-MM).
     */
    public function calcularMensual(string $desde, string $hasta, ?int $departamentoId, ?int $categoriaOpexId): Collection
    {
        return $this->ejecutarAgregacionSql(
            PeriodoTipo::MENSUAL,
            $desde,
            $hasta,
            $departamentoId,
            $categoriaOpexId,
            function (string $driver): string {
                return $driver === 'sqlite'
                    ? "strftime('%Y-%m', gastos_operativos.fecha_gasto)"
                    : "DATE_FORMAT(gastos_operativos.fecha_gasto, '%Y-%m')";
            },
            function (string $mesAnio): array {
                [$anio, $mes] = explode('-', $mesAnio);
                $carbon = Carbon::create((int) $anio, (int) $mes, 1);
                return [
                    'etiqueta' => $carbon->translatedFormat('F Y'),
                    'fecha_inicio' => $carbon->startOfMonth()->toDateString(),
                    'fecha_fin' => $carbon->endOfMonth()->toDateString(),
                    'periodo_tipo' => PeriodoTipo::MENSUAL->value,
                ];
            }
        );
    }

    /**
     * Agregación Trimestral optimizada en SQL: Q1 (Ene-Mar), Q2 (Abr-Jun), Q3 (Jul-Sep), Q4 (Oct-Dic).
     */
    public function calcularTrimestral(string $desde, string $hasta, ?int $departamentoId, ?int $categoriaOpexId): Collection
    {
        return $this->ejecutarAgregacionSql(
            PeriodoTipo::TRIMESTRAL,
            $desde,
            $hasta,
            $departamentoId,
            $categoriaOpexId,
            function (string $driver): string {
                if ($driver === 'sqlite') {
                    return "strftime('%Y', gastos_operativos.fecha_gasto) || '-Q' || CAST(((CAST(strftime('%m', gastos_operativos.fecha_gasto) AS INTEGER) + 2) / 3) AS TEXT)";
                }
                return "CONCAT(YEAR(gastos_operativos.fecha_gasto), '-Q', QUARTER(gastos_operativos.fecha_gasto))";
            },
            function (string $clave): array {
                [$anio, $qStr] = explode('-', $clave);
                $q = (int) str_replace('Q', '', $qStr);
                $mesInicio = ($q - 1) * 3 + 1;
                $mesFin = $q * 3;

                $inicio = Carbon::create((int) $anio, $mesInicio, 1)->startOfMonth()->toDateString();
                $fin = Carbon::create((int) $anio, $mesFin, 1)->endOfMonth()->toDateString();

                return [
                    'etiqueta' => "{$qStr} {$anio}",
                    'fecha_inicio' => $inicio,
                    'fecha_fin' => $fin,
                    'periodo_tipo' => PeriodoTipo::TRIMESTRAL->value,
                ];
            }
        );
    }

    /**
     * Agregación Semestral optimizada en SQL: S1 (Ene-Jun), S2 (Jul-Dic).
     */
    public function calcularSemestral(string $desde, string $hasta, ?int $departamentoId, ?int $categoriaOpexId): Collection
    {
        return $this->ejecutarAgregacionSql(
            PeriodoTipo::SEMESTRAL,
            $desde,
            $hasta,
            $departamentoId,
            $categoriaOpexId,
            function (string $driver): string {
                if ($driver === 'sqlite') {
                    return "strftime('%Y', gastos_operativos.fecha_gasto) || CASE WHEN CAST(strftime('%m', gastos_operativos.fecha_gasto) AS INTEGER) <= 6 THEN '-S1' ELSE '-S2' END";
                }
                return "CONCAT(YEAR(gastos_operativos.fecha_gasto), IF(MONTH(gastos_operativos.fecha_gasto) <= 6, '-S1', '-S2'))";
            },
            function (string $clave): array {
                [$anio, $sStr] = explode('-', $clave);
                $esS1 = $sStr === 'S1';

                $inicio = Carbon::create((int) $anio, $esS1 ? 1 : 7, 1)->startOfMonth()->toDateString();
                $fin = Carbon::create((int) $anio, $esS1 ? 6 : 12, 1)->endOfMonth()->toDateString();

                return [
                    'etiqueta' => "{$sStr} {$anio}",
                    'fecha_inicio' => $inicio,
                    'fecha_fin' => $fin,
                    'periodo_tipo' => PeriodoTipo::SEMESTRAL->value,
                ];
            }
        );
    }

    /**
     * Agregación Anual optimizada en SQL: agrupa por año calendario.
     */
    public function calcularAnual(string $desde, string $hasta, ?int $departamentoId, ?int $categoriaOpexId): Collection
    {
        return $this->ejecutarAgregacionSql(
            PeriodoTipo::ANUAL,
            $desde,
            $hasta,
            $departamentoId,
            $categoriaOpexId,
            function (string $driver): string {
                return $driver === 'sqlite'
                    ? "strftime('%Y', gastos_operativos.fecha_gasto)"
                    : "CAST(YEAR(gastos_operativos.fecha_gasto) AS CHAR)";
            },
            fn (string $anio) => [
                'etiqueta' => "Año {$anio}",
                'fecha_inicio' => "{$anio}-01-01",
                'fecha_fin' => "{$anio}-12-31",
                'periodo_tipo' => PeriodoTipo::ANUAL->value,
            ]
        );
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
                'gran_total' => 0,
                'promedio_mensual' => 0,
                'desviacion_estandar' => 0,
                'coeficiente_variacion' => 0,
                'clasificacion_variabilidad' => 'Baja (Sin movimientos registrados)',
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

    /**
     * Ejecuta la agregación directamente en el motor SQL usando el índice compuesto
     * idx_gasto_fecha_partida (fecha_gasto, partida_id), agrupando por período y categoría OPEX.
     */
    private function ejecutarAgregacionSql(
        PeriodoTipo $periodo,
        string $desde,
        string $hasta,
        ?int $departamentoId,
        ?int $categoriaOpexId,
        callable $sqlPeriodoBuilder,
        callable $metaBuilder
    ): Collection {
        $driver = DB::connection()->getDriverName();
        $expresionPeriodo = $sqlPeriodoBuilder($driver);

        $query = DB::table('gastos_operativos')
            ->join('partidas_presupuestales', 'gastos_operativos.partida_id', '=', 'partidas_presupuestales.id')
            ->join('presupuestos', 'partidas_presupuestales.presupuesto_id', '=', 'presupuestos.id')
            ->join('categorias_opex', 'partidas_presupuestales.categoria_opex_id', '=', 'categorias_opex.id')
            ->whereBetween('gastos_operativos.fecha_gasto', [$desde, $hasta]);

        if ($departamentoId !== null) {
            $query->where('presupuestos.departamento_id', $departamentoId);
        }

        if ($categoriaOpexId !== null) {
            $query->where('partidas_presupuestales.categoria_opex_id', $categoriaOpexId);
        }

        $filas = $query
            ->selectRaw("{$expresionPeriodo} as clave_periodo")
            ->selectRaw('categorias_opex.codigo as categoria_codigo')
            ->selectRaw('categorias_opex.nombre as categoria_nombre')
            ->selectRaw('SUM(gastos_operativos.monto) as monto_categoria')
            ->selectRaw('COUNT(gastos_operativos.id) as transacciones_categoria')
            ->groupBy(DB::raw($expresionPeriodo), 'categorias_opex.codigo', 'categorias_opex.nombre')
            ->orderBy('clave_periodo')
            ->orderBy('categorias_opex.codigo')
            ->get();

        $agrupadosPorPeriodo = $filas->groupBy('clave_periodo');
        $resultado = collect();
        $montoAnterior = null;

        foreach ($agrupadosPorPeriodo->sortKeys() as $clave => $items) {
            $meta = $metaBuilder((string) $clave);
            $total = (float) $items->sum('monto_categoria');
            $cantidad = (int) $items->sum('transacciones_categoria');
            $promedio = $cantidad > 0 ? round($total / $cantidad, 2) : 0.0;

            $variacionPorcentual = 0.0;
            if ($montoAnterior !== null && $montoAnterior > 0) {
                $variacionPorcentual = round((($total - $montoAnterior) / $montoAnterior) * 100, 2);
            }

            $porCategoria = $items->map(fn ($row) => [
                'codigo' => $row->categoria_codigo,
                'categoria' => $row->categoria_nombre,
                'monto' => round((float) $row->monto_categoria, 2),
                'transacciones' => (int) $row->transacciones_categoria,
            ])->values();

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

    private function construirEtiquetaDesdeFechas(PeriodoTipo $periodo, string $fechaInicio): string
    {
        $c = Carbon::parse($fechaInicio);
        return match ($periodo) {
            PeriodoTipo::DIARIO => $fechaInicio,
            PeriodoTipo::QUINCENAL => ($c->day <= 15 ? '1ra Quincena ' : '2da Quincena ') . $c->translatedFormat('F Y'),
            PeriodoTipo::MENSUAL => $c->translatedFormat('F Y'),
            PeriodoTipo::TRIMESTRAL => 'Q' . (int) ceil($c->month / 3) . ' ' . $c->year,
            PeriodoTipo::SEMESTRAL => ($c->month <= 6 ? 'S1 ' : 'S2 ') . $c->year,
            PeriodoTipo::ANUAL => "Año {$c->year}",
        };
    }
}
