<?php

namespace Tests\Unit;

use App\Models\CategoriaOpex;
use App\Models\Departamento;
use App\Models\PeriodoFiscal;
use App\Models\Presupuesto;
use App\Models\Rol;
use App\Models\User;
use App\Services\RolDinamicoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RolDinamicoTest extends TestCase
{
    use RefreshDatabase;

    public function test_motor_roles_evalua_departamento_con_alta_ejecucion(): void
    {
        $this->seed();

        $service = app(RolDinamicoService::class);

        $depto = Departamento::where('codigo', 'JR')->first();
        $this->assertNotNull($depto);

        $user = User::factory()->create([
            'departamento_id' => $depto->id,
        ]);

        $rolesDinamicos = Rol::dinamicos()->get();
        $this->assertNotEmpty($rolesDinamicos);

        $resultado = $service->evaluarDepartamento($depto, $rolesDinamicos, 2026);

        $this->assertEquals('JR', $resultado['departamento']);
        $this->assertGreaterThanOrEqual(80.0, $resultado['variables']['porcentaje_ejecucion']);

        $user->refresh();
        $tieneAprobador = $user->roles()->where('slug', 'aprobador')->wherePivot('activo', true)->exists();
        $this->assertTrue($tieneAprobador, 'El usuario debió recibir el rol dinámico de Aprobador');
    }

    public function test_asignacion_rol_controlador_registra_restricciones_en_tabla_pivote(): void
    {
        $this->seed();

        $service = app(RolDinamicoService::class);
        $depto = Departamento::where('codigo', 'TI')->first();
        $rolControlador = Rol::where('slug', 'controlador')->first();
        $user = User::factory()->create(['departamento_id' => $depto->id]);

        $service->asignarRol(
            $user,
            $rolControlador,
            $depto->id,
            ['OPEX-001', 'OPEX-003'],
            'Prueba de restricción OPEX en tabla user_rol_categoria_restriccion'
        );

        $pivotRow = DB::table('user_rol')
            ->where('user_id', $user->id)
            ->where('rol_id', $rolControlador->id)
            ->first();

        $this->assertNotNull($pivotRow);
        $this->assertEquals(0, (int) $pivotRow->alcance_global);
        $this->assertEquals($depto->id, (int) $pivotRow->departamento_id);

        $catCount = DB::table('user_rol_categoria_restriccion')
            ->where('user_rol_id', $pivotRow->id)
            ->count();

        $this->assertEquals(2, $catCount, 'Deben haberse registrado 2 categorías restringidas en user_rol_categoria_restriccion');
    }
}
