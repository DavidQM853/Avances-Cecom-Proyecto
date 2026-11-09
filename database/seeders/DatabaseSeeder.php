<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Estructura organizacional
        $this->call(DepartamentoSeeder::class);

        // 2. Categorías de gasto OPEX
        $this->call(CategoriaOpexSeeder::class);

        // 3. Periodos fiscales (2024, 2025, 2026)
        $this->call(PeriodoFiscalSeeder::class);

        // 4. Roles y permisos del sistema
        $this->call(RolesPermisosSeeder::class);

        // 5. Usuario administrador por defecto
        $admin = User::factory()->create([
            'name' => 'Administrador CECOM',
            'email' => 'admin@cecom.gob.mx',
            'departamento_id' => 1, // Dirección General
        ]);

        // Asignar rol de administrador con alcance_global=true y departamento_id=null (cumple ck_ur_alcance)
        $admin->roles()->attach(1, [
            'departamento_id' => null,
            'alcance_global' => true,
            'activo' => true,
        ]);

        // 6. Generar datos históricos (presupuestos, partidas, reservas, gastos 2024-2026)
        $this->call(DatosHistoricosSeeder::class);
    }
}
