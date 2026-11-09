<?php

namespace Database\Seeders;

use App\Models\Departamento;
use Illuminate\Database\Seeder;

class DepartamentoSeeder extends Seeder
{
    /**
     * Seed departamentos con estructura jerárquica típica de CECOM.
     */
    public function run(): void
    {
        $direccion = Departamento::create([
            'nombre' => 'Dirección General',
            'codigo' => 'DG',
            'descripcion' => 'Dirección General de la organización',
        ]);

        $departamentos = [
            [
                'nombre' => 'Administración y Finanzas',
                'codigo' => 'AF',
                'descripcion' => 'Gestión administrativa y financiera',
                'departamento_padre_id' => $direccion->id,
            ],
            [
                'nombre' => 'Tecnologías de la Información',
                'codigo' => 'TI',
                'descripcion' => 'Infraestructura y desarrollo tecnológico',
                'departamento_padre_id' => $direccion->id,
            ],
            [
                'nombre' => 'Recursos Humanos',
                'codigo' => 'RH',
                'descripcion' => 'Gestión del capital humano',
                'departamento_padre_id' => $direccion->id,
            ],
            [
                'nombre' => 'Operaciones',
                'codigo' => 'OP',
                'descripcion' => 'Operaciones y logística',
                'departamento_padre_id' => $direccion->id,
            ],
            [
                'nombre' => 'Comercialización',
                'codigo' => 'CM',
                'descripcion' => 'Ventas y marketing',
                'departamento_padre_id' => $direccion->id,
            ],
            [
                'nombre' => 'Jurídico',
                'codigo' => 'JR',
                'descripcion' => 'Asuntos legales y normatividad',
                'departamento_padre_id' => $direccion->id,
            ],
            [
                'nombre' => 'Planeación Estratégica',
                'codigo' => 'PE',
                'descripcion' => 'Planeación y desarrollo institucional',
                'departamento_padre_id' => $direccion->id,
            ],
        ];

        foreach ($departamentos as $depto) {
            Departamento::create($depto);
        }
    }
}
