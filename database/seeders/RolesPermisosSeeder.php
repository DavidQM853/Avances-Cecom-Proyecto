<?php

namespace Database\Seeders;

use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Database\Seeder;

class RolesPermisosSeeder extends Seeder
{
    /**
     * Seed roles base y permisos del sistema.
     */
    public function run(): void
    {
        // ── Permisos por módulo ──────────────────────────────

        $permisosPorModulo = [
            'presupuestos' => [
                ['nombre' => 'Ver Presupuestos', 'slug' => 'presupuestos.ver'],
                ['nombre' => 'Crear Presupuestos', 'slug' => 'presupuestos.crear'],
                ['nombre' => 'Editar Presupuestos', 'slug' => 'presupuestos.editar'],
                ['nombre' => 'Eliminar Presupuestos', 'slug' => 'presupuestos.eliminar'],
                ['nombre' => 'Aprobar Presupuestos', 'slug' => 'presupuestos.aprobar'],
            ],
            'gastos' => [
                ['nombre' => 'Ver Gastos', 'slug' => 'gastos.ver'],
                ['nombre' => 'Crear Gastos', 'slug' => 'gastos.crear'],
                ['nombre' => 'Editar Gastos', 'slug' => 'gastos.editar'],
                ['nombre' => 'Eliminar Gastos', 'slug' => 'gastos.eliminar'],
            ],
            'reportes' => [
                ['nombre' => 'Ver Reportes', 'slug' => 'reportes.ver'],
                ['nombre' => 'Exportar Reportes', 'slug' => 'reportes.exportar'],
                ['nombre' => 'Ver Dashboard', 'slug' => 'reportes.dashboard'],
            ],
            'usuarios' => [
                ['nombre' => 'Ver Usuarios', 'slug' => 'usuarios.ver'],
                ['nombre' => 'Crear Usuarios', 'slug' => 'usuarios.crear'],
                ['nombre' => 'Editar Usuarios', 'slug' => 'usuarios.editar'],
                ['nombre' => 'Eliminar Usuarios', 'slug' => 'usuarios.eliminar'],
            ],
            'roles' => [
                ['nombre' => 'Ver Roles', 'slug' => 'roles.ver'],
                ['nombre' => 'Gestionar Roles', 'slug' => 'roles.gestionar'],
                ['nombre' => 'Asignar Roles', 'slug' => 'roles.asignar'],
            ],
            'departamentos' => [
                ['nombre' => 'Ver Departamentos', 'slug' => 'departamentos.ver'],
                ['nombre' => 'Gestionar Departamentos', 'slug' => 'departamentos.gestionar'],
            ],
            'monitoreo' => [
                ['nombre' => 'Ver Monitoreo', 'slug' => 'monitoreo.ver'],
            ],
        ];

        $todosLosPermisos = [];

        foreach ($permisosPorModulo as $modulo => $permisos) {
            foreach ($permisos as $permisoData) {
                $permiso = Permiso::create([
                    'nombre' => $permisoData['nombre'],
                    'slug' => $permisoData['slug'],
                    'modulo' => $modulo,
                ]);
                $todosLosPermisos[$permisoData['slug']] = $permiso->id;
            }
        }

        // ── Roles estáticos ─────────────────────────────────

        $admin = Rol::create([
            'nombre' => 'Administrador',
            'slug' => 'admin',
            'descripcion' => 'Acceso total al sistema',
            'es_dinamico' => false,
        ]);
        $admin->permisos()->attach(array_values($todosLosPermisos));

        $director = Rol::create([
            'nombre' => 'Director Departamental',
            'slug' => 'director',
            'descripcion' => 'Gestiona presupuestos y gastos de su departamento',
            'es_dinamico' => false,
        ]);
        $director->permisos()->attach([
            $todosLosPermisos['presupuestos.ver'],
            $todosLosPermisos['presupuestos.crear'],
            $todosLosPermisos['presupuestos.editar'],
            $todosLosPermisos['gastos.ver'],
            $todosLosPermisos['gastos.crear'],
            $todosLosPermisos['gastos.editar'],
            $todosLosPermisos['reportes.ver'],
            $todosLosPermisos['reportes.dashboard'],
            $todosLosPermisos['departamentos.ver'],
        ]);

        $analista = Rol::create([
            'nombre' => 'Analista',
            'slug' => 'analista',
            'descripcion' => 'Solo lectura con acceso a reportes y exportación',
            'es_dinamico' => false,
        ]);
        $analista->permisos()->attach([
            $todosLosPermisos['presupuestos.ver'],
            $todosLosPermisos['gastos.ver'],
            $todosLosPermisos['reportes.ver'],
            $todosLosPermisos['reportes.exportar'],
            $todosLosPermisos['reportes.dashboard'],
            $todosLosPermisos['departamentos.ver'],
        ]);

        // ── Roles dinámicos ─────────────────────────────────

        Rol::create([
            'nombre' => 'Aprobador',
            'slug' => 'aprobador',
            'descripcion' => 'Se activa cuando un departamento requiere aprobación por umbral de ejecución',
            'es_dinamico' => true,
            'reglas_asignacion' => [
                'condiciones' => [
                    [
                        'variable' => 'porcentaje_ejecucion',
                        'operador' => '>=',
                        'valor' => 80,
                        'periodo' => 'trimestral',
                    ],
                ],
                'accion' => 'activar',
                'permisos_afectados' => ['presupuestos.aprobar'],
            ],
        ]);

        Rol::create([
            'nombre' => 'Auditor OPEX',
            'slug' => 'auditor-opex',
            'descripcion' => 'Se asigna cuando la variabilidad intermensual excede el umbral',
            'es_dinamico' => true,
            'reglas_asignacion' => [
                'condiciones' => [
                    [
                        'variable' => 'variacion_interanual',
                        'operador' => '>',
                        'valor' => 15,
                        'periodo' => 'anual',
                    ],
                ],
                'accion' => 'activar',
                'permisos_afectados' => ['reportes.ver', 'reportes.exportar'],
            ],
        ]);

        Rol::create([
            'nombre' => 'Controlador',
            'slug' => 'controlador',
            'descripcion' => 'Restricciones de gasto basadas en historial de ejecución excesiva',
            'es_dinamico' => true,
            'reglas_asignacion' => [
                'condiciones' => [
                    [
                        'variable' => 'porcentaje_ejecucion',
                        'operador' => '>',
                        'valor' => 95,
                        'periodo' => 'mensual',
                    ],
                    [
                        'variable' => 'meses_consecutivos_excedido',
                        'operador' => '>=',
                        'valor' => 3,
                        'periodo' => 'mensual',
                    ],
                ],
                'accion' => 'restringir',
                'permisos_afectados' => ['gastos.crear', 'presupuestos.aprobar'],
                'categorias_opex' => ['OPEX-001', 'OPEX-003'],
            ],
        ]);
    }
}
