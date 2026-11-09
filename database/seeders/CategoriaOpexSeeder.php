<?php

namespace Database\Seeders;

use App\Models\CategoriaOpex;
use Illuminate\Database\Seeder;

class CategoriaOpexSeeder extends Seeder
{
    /**
     * Seed categorías OPEX con estructura jerárquica.
     */
    public function run(): void
    {
        $categorias = [
            [
                'nombre' => 'Nómina y Salarios',
                'codigo' => 'OPEX-001',
                'tipo_gasto' => 'nomina',
                'descripcion' => 'Sueldos, salarios, prestaciones y compensaciones al personal',
                'subcategorias' => [
                    ['nombre' => 'Sueldos Base', 'codigo' => 'OPEX-001-01', 'tipo_gasto' => 'nomina'],
                    ['nombre' => 'Prestaciones', 'codigo' => 'OPEX-001-02', 'tipo_gasto' => 'nomina'],
                    ['nombre' => 'Bonos y Compensaciones', 'codigo' => 'OPEX-001-03', 'tipo_gasto' => 'nomina'],
                ],
            ],
            [
                'nombre' => 'Servicios Profesionales',
                'codigo' => 'OPEX-002',
                'tipo_gasto' => 'servicios',
                'descripcion' => 'Consultoría, asesoría legal, contable y especializada',
                'subcategorias' => [
                    ['nombre' => 'Consultoría Externa', 'codigo' => 'OPEX-002-01', 'tipo_gasto' => 'servicios'],
                    ['nombre' => 'Servicios Contables', 'codigo' => 'OPEX-002-02', 'tipo_gasto' => 'servicios'],
                    ['nombre' => 'Servicios Legales', 'codigo' => 'OPEX-002-03', 'tipo_gasto' => 'servicios'],
                ],
            ],
            [
                'nombre' => 'Materiales y Suministros',
                'codigo' => 'OPEX-003',
                'tipo_gasto' => 'materiales',
                'descripcion' => 'Papelería, insumos de oficina, materiales de operación',
                'subcategorias' => [
                    ['nombre' => 'Papelería y Oficina', 'codigo' => 'OPEX-003-01', 'tipo_gasto' => 'materiales'],
                    ['nombre' => 'Insumos de Limpieza', 'codigo' => 'OPEX-003-02', 'tipo_gasto' => 'materiales'],
                ],
            ],
            [
                'nombre' => 'Mantenimiento y Reparación',
                'codigo' => 'OPEX-004',
                'tipo_gasto' => 'mantenimiento',
                'descripcion' => 'Mantenimiento preventivo y correctivo de instalaciones y equipos',
            ],
            [
                'nombre' => 'Tecnología e Infraestructura',
                'codigo' => 'OPEX-005',
                'tipo_gasto' => 'tecnologia',
                'descripcion' => 'Software, hardware, licencias, hosting y telecomunicaciones',
                'subcategorias' => [
                    ['nombre' => 'Licencias de Software', 'codigo' => 'OPEX-005-01', 'tipo_gasto' => 'tecnologia'],
                    ['nombre' => 'Hosting y Cloud', 'codigo' => 'OPEX-005-02', 'tipo_gasto' => 'tecnologia'],
                    ['nombre' => 'Telecomunicaciones', 'codigo' => 'OPEX-005-03', 'tipo_gasto' => 'tecnologia'],
                ],
            ],
            [
                'nombre' => 'Capacitación y Desarrollo',
                'codigo' => 'OPEX-006',
                'tipo_gasto' => 'capacitacion',
                'descripcion' => 'Cursos, certificaciones y desarrollo profesional',
            ],
            [
                'nombre' => 'Viáticos y Transportación',
                'codigo' => 'OPEX-007',
                'tipo_gasto' => 'viaticos',
                'descripcion' => 'Gastos de viaje, hospedaje, alimentación y transporte',
            ],
            [
                'nombre' => 'Arrendamientos',
                'codigo' => 'OPEX-008',
                'tipo_gasto' => 'arrendamiento',
                'descripcion' => 'Renta de inmuebles, equipo y vehículos',
            ],
            [
                'nombre' => 'Seguros y Fianzas',
                'codigo' => 'OPEX-009',
                'tipo_gasto' => 'seguros',
                'descripcion' => 'Pólizas de seguro y fianzas institucionales',
            ],
            [
                'nombre' => 'Otros Gastos Operativos',
                'codigo' => 'OPEX-010',
                'tipo_gasto' => 'otros',
                'descripcion' => 'Gastos operativos no clasificados en las categorías anteriores',
            ],
        ];

        foreach ($categorias as $catData) {
            $subcategorias = $catData['subcategorias'] ?? [];
            unset($catData['subcategorias']);

            $categoria = CategoriaOpex::create($catData);

            foreach ($subcategorias as $subData) {
                $subData['categoria_padre_id'] = $categoria->id;
                CategoriaOpex::create($subData);
            }
        }
    }
}
