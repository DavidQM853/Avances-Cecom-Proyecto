<?php

namespace Tests\Unit;

use App\Models\Departamento;
use App\Models\Rol;
use App\Models\User;
use App\Services\AgregacionTemporalService;
use App\Services\RolDinamicoService;
use Tests\TestCase;

class RolDinamicoTest extends TestCase
{
    public function test_motor_roles_evalua_departamento_con_alta_ejecucion(): void
    {
        $service = app(RolDinamicoService::class);

        // Departamento JR (Jurídico) tiene 88.5% de ejecución en el dataset seeded
        $depto = Departamento::where('codigo', 'JR')->first();
        $this->assertNotNull($depto);

        // Crear un usuario en este departamento
        $user = User::factory()->create([
            'departamento_id' => $depto->id,
        ]);

        $rolesDinamicos = Rol::dinamicos()->get();
        $this->assertNotEmpty($rolesDinamicos);

        $resultado = $service->evaluarDepartamento($depto, $rolesDinamicos, 2026);

        $this->assertEquals('JR', $resultado['departamento']);
        $this->assertGreaterThanOrEqual(80.0, $resultado['variables']['porcentaje_ejecucion']);

        // Verificar que se asignó el rol dinámico "Aprobador" por tener >= 80% de ejecución
        $user->refresh();
        $tieneAprobador = $user->roles()->where('slug', 'aprobador')->exists();
        $this->assertTrue($tieneAprobador, 'El usuario debió recibir el rol dinámico de Aprobador');
    }
}
