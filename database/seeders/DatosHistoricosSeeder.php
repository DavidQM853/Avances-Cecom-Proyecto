<?php

namespace Database\Seeders;

use App\Enums\EstadoPresupuesto;
use App\Models\CategoriaOpex;
use App\Models\CompromisoReserva;
use App\Models\Departamento;
use App\Models\GastoOperativo;
use App\Models\ModeloPredictivo;
use App\Models\PartidaPresupuestal;
use App\Models\PeriodoFiscal;
use App\Models\Presupuesto;
use App\Models\ProyeccionAnual;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DatosHistoricosSeeder extends Seeder
{
    /**
     * Genera datos históricos de presupuestos, partidas, compromisos_reserva,
     * gastos y proyecciones para 2024, 2025 y 2026 cumpliendo todas las
     * restricciones CHECK y vistas de conciliación de cecom_presupuestos_er.
     */
    public function run(): void
    {
        $admin = User::first();
        $departamentos = Departamento::whereNotNull('departamento_padre_id')->get();
        $categorias = CategoriaOpex::whereNull('categoria_padre_id')->get();

        $anios = [2024, 2025, 2026];

        $conceptosPorCategoria = [
            'OPEX-001' => ['Nómina 1ra Quincena', 'Nómina 2da Quincena', 'Bono de Desempeño Trimestral', 'Finiquito Laboral', 'Compensación por Guardias'],
            'OPEX-002' => ['Auditoría Externa', 'Asesoría Fiscal Especializada', 'Consultoría de Seguridad IT', 'Servicios Notariales y Poderes'],
            'OPEX-003' => ['Papelería institucional mensual', 'Tóner y consumibles de impresión', 'Insumos de cafetería y sanitarios'],
            'OPEX-004' => ['Mantenimiento a subestación eléctrica', 'Servicio a equipo de aire acondicionado', 'Reparación de cableado estructurado'],
            'OPEX-005' => ['Renovación Licencias Microsoft 365', 'Servidores Cloud AWS / Azure', 'Enlace dedicado de fibra óptica'],
            'OPEX-006' => ['Capacitación en Ciberseguridad ISO 27001', 'Taller de Liderazgo Gerencial', 'Certificación Cloud Architect'],
            'OPEX-007' => ['Viáticos Comisión Ciudad de México', 'Transportación a centros regionales', 'Hospedaje reunión directiva'],
            'OPEX-008' => ['Renta mensual de oficinas corporativas', 'Arrendamiento de flotilla vehicular', 'Renta de equipo multifuncional'],
            'OPEX-009' => ['Póliza de seguro contra siniestros', 'Fianzas de cumplimiento gubernamental'],
            'OPEX-010' => ['Gastos operativos menores', 'Cuotas de suscripción técnica'],
        ];

        // Modelo predictivo base para alimentar proyecciones_anuales
        $modelo = ModeloPredictivo::firstOrCreate(
            ['version' => 'cecom-opex-prophet-v1.0'],
            [
                'version_datos' => 'dataset-historico-2024-2025',
                'entrenamiento_desde' => '2024-01-01',
                'entrenamiento_hasta' => '2025-06-30',
                'validacion_desde' => '2025-07-01',
                'validacion_hasta' => '2025-12-31',
                'metricas_validacion' => ['mae' => 42150.25, 'mape' => 4.82, 'r2' => 0.94],
                'referencia_artefacto' => 'storage/models/cecom-opex-prophet-v1.0.json',
            ]
        );

        foreach ($anios as $anio) {
            $periodoAnual = PeriodoFiscal::where('anio', $anio)
                ->whereNull('trimestre')
                ->whereNull('mes')
                ->first();

            if (!$periodoAnual) {
                continue;
            }

            foreach ($departamentos as $depto) {
                $baseMonto = match ($depto->codigo) {
                    'AF' => 3500000.00,
                    'TI' => 4800000.00,
                    'RH' => 2200000.00,
                    'OP' => 5200000.00,
                    'CM' => 2800000.00,
                    'JR' => 1200000.00,
                    'PE' => 1500000.00,
                    default => 2000000.00,
                };

                $factorAnio = 1 + (($anio - 2024) * 0.08);
                $montoTotal = round($baseMonto * $factorAnio, 2);

                $estado = match ($anio) {
                    2024 => EstadoPresupuesto::CERRADO,
                    2025 => EstadoPresupuesto::CERRADO,
                    2026 => EstadoPresupuesto::EJECUTANDO,
                };

                $presupuesto = Presupuesto::create([
                    'departamento_id' => $depto->id,
                    'periodo_fiscal_id' => $periodoAnual->id,
                    'monto_total' => $montoTotal,
                    'monto_ejercido' => 0,
                    'monto_comprometido' => 0,
                    'estado' => $estado,
                    'creado_por' => $admin?->id,
                    'aprobado_por' => $admin?->id,
                    'aprobado_en' => Carbon::create($anio, 1, 5),
                    'notas' => "Presupuesto operativo aprobado para {$depto->nombre} ejercicio {$anio}",
                ]);

                $totalCategorias = $categorias->count();
                $montoPorCategoria = round($montoTotal / $totalCategorias, 2);
                $totalEjercidoPresupuesto = 0;
                $totalComprometidoPresupuesto = 0;
                $totalAsignadoPresupuesto = 0;

                $mesMaximo = ($anio === 2026) ? 8 : 12;

                foreach ($categorias as $idx => $cat) {
                    // Ajustar última partida por centavos de redondeo para conciliación exacta con monto_total
                    $montoAsignadoPartida = ($idx === $totalCategorias - 1)
                        ? round($montoTotal - $totalAsignadoPresupuesto, 2)
                        : $montoPorCategoria;
                    $totalAsignadoPresupuesto += $montoAsignadoPartida;

                    $partida = PartidaPresupuestal::create([
                        'presupuesto_id' => $presupuesto->id,
                        'categoria_opex_id' => $cat->id,
                        'monto_asignado' => $montoAsignadoPartida,
                        'monto_ejercido' => 0,
                        'monto_comprometido' => 0,
                    ]);

                    $conceptos = $conceptosPorCategoria[$cat->codigo] ?? ['Gasto operativo general'];
                    $totalEjercidoPartida = 0;
                    $compromisoPartida = 0;

                    // Para 2026 crear una reserva activa (5% de la partida) para cuadrar v_partidas_conciliacion
                    if ($anio === 2026 && $admin) {
                        $compromisoPartida = round($montoAsignadoPartida * 0.05, 2);
                        CompromisoReserva::create([
                            'partida_id' => $partida->id,
                            'creado_por' => $admin->id,
                            'referencia_documento' => "RES-{$anio}-{$depto->codigo}-{$cat->codigo}",
                            'monto_original' => $compromisoPartida,
                            'monto_liberado' => 0,
                            'estado' => 'activa',
                        ]);
                    }

                    // Techo disponible para gastos sin violar ck_partida_montos (ejercido + comprometido <= asignado)
                    $techoGastosPartida = round(($montoAsignadoPartida - $compromisoPartida) * 0.90, 2);

                    for ($mes = 1; $mes <= $mesMaximo; $mes++) {
                        $gastosEnMes = rand(1, 2);
                        for ($g = 1; $g <= $gastosEnMes; $g++) {
                            $dia = rand(1, 28);
                            $fechaGasto = Carbon::create($anio, $mes, $dia)->toDateString();

                            $concepto = $conceptos[array_rand($conceptos)];
                            // En JR 2026 asegurar alta ejecución (>=82%) para pruebas deterministas del rol Aprobador
                            if ($depto->codigo === 'JR' && $anio === 2026) {
                                $montoGasto = round(($montoAsignadoPartida * 0.85) / ($mesMaximo * $gastosEnMes), 2);
                            } else {
                                $maxGasto = max(1000, (int) ($montoAsignadoPartida / 22));
                                $montoGasto = round(rand(1000, $maxGasto), 2);
                            }

                            if (($totalEjercidoPartida + $montoGasto) > $techoGastosPartida) {
                                break;
                            }

                            GastoOperativo::create([
                                'partida_id' => $partida->id,
                                'registrado_por' => $admin?->id,
                                'monto' => $montoGasto,
                                'concepto' => "{$concepto} - {$depto->codigo}",
                                'referencia_documento' => "FAC-{$anio}-" . str_pad((string) rand(1000, 9999), 5, '0', STR_PAD_LEFT),
                                'fecha_gasto' => $fechaGasto,
                                'fecha_registro' => $fechaGasto,
                                'metadata' => [
                                    'proveedor' => 'Proveedor Homologado CECOM',
                                    'moneda' => 'MXN',
                                ],
                            ]);

                            $totalEjercidoPartida = round($totalEjercidoPartida + $montoGasto, 2);
                        }
                    }

                    $partida->update([
                        'monto_ejercido' => $totalEjercidoPartida,
                        'monto_comprometido' => $compromisoPartida,
                    ]);

                    $totalEjercidoPresupuesto = round($totalEjercidoPresupuesto + $totalEjercidoPartida, 2);
                    $totalComprometidoPresupuesto = round($totalComprometidoPresupuesto + $compromisoPartida, 2);
                }

                // Actualizar presupuesto cuadrando con la suma de sus partidas (v_presupuestos_conciliacion)
                $presupuesto->update([
                    'monto_ejercido' => $totalEjercidoPresupuesto,
                    'monto_comprometido' => $totalComprometidoPresupuesto,
                ]);

                // Registrar proyección anual hacia el siguiente ejercicio
                ProyeccionAnual::firstOrCreate(
                    [
                        'modelo_id' => $modelo->id,
                        'departamento_id' => $depto->id,
                        'anio_base' => $anio,
                        'anio_objetivo' => $anio + 1,
                        'fecha_corte_datos' => "{$anio}-12-31",
                    ],
                    [
                        'monto_proyectado' => round($montoTotal * 1.07, 2),
                    ]
                );
            }
        }
    }
}
