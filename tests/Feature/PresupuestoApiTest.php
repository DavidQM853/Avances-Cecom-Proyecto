<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PresupuestoApiTest extends TestCase
{
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
        $user = User::first() ?? User::factory()->create();

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
        $user = User::first() ?? User::factory()->create();

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
     * Prueba el filtrado temporal por agregación mensual.
     */
    public function test_agregaciones_temporales_mensual(): void
    {
        $user = User::first() ?? User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/agregaciones?periodo=mensual&desde=2025-01-01&hasta=2025-12-31');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'filtros',
                'resumen' => ['total_general', 'total_transacciones', 'total_periodos'],
                'serie_temporal',
            ]);
    }

    /**
     * Prueba el análisis de variabilidad intermensual (Objetivo 6).
     */
    public function test_analisis_variabilidad_intermensual(): void
    {
        $user = User::first() ?? User::factory()->create();

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
        $user = User::first() ?? User::factory()->create();

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
}
