<?php

namespace Tests\Feature;

use App\Enums\EstadoPresupuesto;
use App\Models\CategoriaOpex;
use App\Models\Departamento;
use App\Models\PartidaPresupuestal;
use App\Models\Presupuesto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PresupuestoApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Prueba el endpoint de login y obtención de token Sanctum.
     */
    public function test_login_retorna_token_y_permisos(): void
    {
        $user = User::factory()->create([
            'email' => 'test_user@cecom.gob.mx',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test_user@cecom.gob.mx',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'token',
                'token_type',
                'user' => ['id', 'name', 'email', 'roles', 'permisos'],
            ]);
    }

    /**
     * Prueba el listado de departamentos.
     */
    public function test_listado_departamentos(): void
    {
        $user = User::first();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/departamentos');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    /**
     * Prueba la comparativa YoY de presupuestos.
     */
    public function test_comparativa_yoy(): void
    {
        $user = User::first();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/presupuestos-yoy/comparativa?anio_base=2024&anio_comparar=2025');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'metadatos' => [
                    'anio_base',
                    'anio_comparar',
                    'gran_total_base',
                    'gran_total_comparar',
                    'delta_total',
                    'variacion_total_porcentual',
                    'tendencia_global',
                ],
                'departamentos',
            ]);
    }

    /**
     * Prueba los 6 periodos de agregación temporal (diario, quincenal, mensual, trimestral, semestral, anual).
     */
    public function test_agregaciones_temporales_todos_los_periodos(): void
    {
        $user = User::first();
        $periodos = ['diario', 'quincenal', 'mensual', 'trimestral', 'semestral', 'anual'];

        foreach ($periodos as $periodo) {
            $response = $this->actingAs($user, 'sanctum')
                ->getJson("/api/v1/agregaciones?periodo={$periodo}&desde=2025-01-01&hasta=2025-12-31");

            $response->assertStatus(200)
                ->assertJsonPath('filtros.periodo', $periodo)
                ->assertJsonStructure([
                    'filtros',
                    'resumen' => ['total_general', 'total_transacciones', 'total_periodos'],
                    'serie_temporal',
                ]);
        }
    }

    /**
     * Prueba el análisis de variabilidad intermensual (Objetivo 6).
     */
    public function test_analisis_variabilidad_intermensual(): void
    {
        $user = User::first();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/reportes/variabilidad-mensual?anio=2025');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'analisis' => [
                    'anio',
                    'gran_total',
                    'promedio_mensual',
                    'desviacion_estandar',
                    'coeficiente_variacion',
                    'clasificacion_variabilidad',
                    'serie_mensual',
                ],
            ]);
    }

    /**
     * Prueba el monitoreo de latencia en tiempo real (Objetivo 5).
     */
    public function test_monitoreo_latencia_en_tiempo_real(): void
    {
        $user = User::first();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/monitor/latencia');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'timestamp',
                'database_driver',
                'latencia_promedio_ms',
                'metricas_detalle' => [
                    'ping_ms',
                    'count_gastos_ms',
                    'join_agregacion_ms',
                ],
                'estado',
            ]);
    }

    /**
     * Prueba las vistas de conciliación y proyecciones anuales de cecom_presupuestos_er.
     */
    public function test_vistas_conciliacion_y_proyecciones_anuales(): void
    {
        $user = User::first();

        $conciliacion = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/reportes/conciliacion');

        $conciliacion->assertStatus(200)
            ->assertJsonPath('resumen.presupuestos_descuadrados', 0)
            ->assertJsonPath('resumen.partidas_descuadradas', 0)
            ->assertJsonPath('resumen.reservas_descuadradas', 0);

        $proyecciones = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/reportes/proyecciones?anio_objetivo=2027');

        $proyecciones->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    /**
     * Prueba creación y liberación de compromisos_reserva y registro en bitacora_eventos.
     */
    public function test_flujo_compromiso_reserva_y_gasto_vinculado(): void
    {
        $admin = User::first();
        $presupuesto = Presupuesto::where('estado', EstadoPresupuesto::EJECUTANDO)->first();
        $partida = PartidaPresupuestal::where('presupuesto_id', $presupuesto->id)->first();

        $reservaRes = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/compromisos', [
                'partida_id' => $partida->id,
                'referencia_documento' => 'OC-2026-TEST-01',
                'monto_original' => 1000.00,
            ]);

        $reservaRes->assertStatus(201);
        $compromisoId = $reservaRes->json('data.id');

        $gastoRes = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/gastos', [
                'partida_id' => $partida->id,
                'compromiso_id' => $compromisoId,
                'monto' => 600.00,
                'concepto' => 'Gasto devengado contra reserva OC-2026-TEST-01',
                'fecha_gasto' => '2026-08-20',
            ]);

        $gastoRes->assertStatus(201);

        $liberarRes = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/compromisos/{$compromisoId}/liberar", [
                'monto_liberar' => 400.00,
                'motivo' => 'Liberación del remanente de la orden de compra',
            ]);

        $liberarRes->assertStatus(200)
            ->assertJsonPath('data.estado', 'cerrada');
    }
}
