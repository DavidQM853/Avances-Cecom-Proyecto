<?php

namespace Tests\Feature;

use App\Enums\EstadoPresupuesto;
use App\Models\CategoriaOpex;
use App\Models\Departamento;
use App\Models\PartidaPresupuestal;
use App\Models\PeriodoFiscal;
use App\Models\Presupuesto;
use App\Models\Rol;
use App\Models\User;
use App\Services\RolDinamicoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ControlAccesoMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_role_bloquea_usuario_sin_rol_requerido(): void
    {
        $this->seed();

        $analistaRol = Rol::where('slug', 'analista')->first();
        $depto = Departamento::where('codigo', 'AF')->first();

        $user = User::factory()->create(['departamento_id' => $depto->id]);
        $user->roles()->attach($analistaRol->id, [
            'departamento_id' => $depto->id,
            'alcance_global' => false,
            'activo' => true,
        ]);

        // Un analista no puede crear roles (requiere role:admin)
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/roles', [
                'nombre' => 'Rol No Autorizado',
                'slug' => 'no-autorizado',
            ]);

        $response->assertStatus(403);
    }

    public function test_check_opex_permission_bloquea_categoria_restringida(): void
    {
        $this->seed();

        $depto = Departamento::where('codigo', 'TI')->first();
        $catRestringida = CategoriaOpex::where('codigo', 'OPEX-001')->first();
        $rolControlador = Rol::where('slug', 'controlador')->first();

        $user = User::factory()->create(['departamento_id' => $depto->id]);

        app(RolDinamicoService::class)->asignarRol(
            $user,
            $rolControlador,
            $depto->id,
            ['OPEX-001'],
            'Restricción de prueba sobre OPEX-001'
        );

        $presupuesto = Presupuesto::where('departamento_id', $depto->id)
            ->where('estado', EstadoPresupuesto::EJECUTANDO)
            ->first();

        $partidaBloqueada = PartidaPresupuestal::where('presupuesto_id', $presupuesto->id)
            ->where('categoria_opex_id', $catRestringida->id)
            ->first();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/gastos', [
                'partida_id' => $partidaBloqueada->id,
                'monto' => 500.00,
                'concepto' => 'Intento de gasto en categoría restringida',
                'fecha_gasto' => '2026-08-15',
            ]);

        $response->assertStatus(403)
            ->assertJsonStructure(['message']);
    }
}
