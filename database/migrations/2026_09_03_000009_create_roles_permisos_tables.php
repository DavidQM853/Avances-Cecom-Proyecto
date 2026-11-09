<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $esMysql = in_array(DB::getDriverName(), ['mysql', 'mariadb']);

        // Tabla de roles
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('slug', 100)->unique();
            $table->text('descripcion')->nullable();
            $table->boolean('es_dinamico')->default(false)
                  ->comment('Si true, el rol se asigna/revoca automáticamente por reglas');
            $table->json('reglas_asignacion')->nullable()
                  ->comment('Reglas JSON para asignación dinámica del rol');
            $table->timestamps();
        });

        // Tabla de permisos
        Schema::create('permisos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('slug', 100)->unique();
            $table->string('modulo', 50)->comment('Módulo al que pertenece: presupuestos, gastos, reportes, etc.');
            $table->text('descripcion')->nullable();
            $table->timestamps();

            $table->index('modulo', 'idx_permiso_modulo');
        });

        // Pivot: rol <-> permiso
        Schema::create('rol_permiso', function (Blueprint $table) {
            $table->foreignId('rol_id')
                  ->constrained('roles', 'id', 'fk_rp_rol')
                  ->restrictOnDelete();
            $table->foreignId('permiso_id')
                  ->constrained('permisos', 'id', 'fk_rp_permiso')
                  ->restrictOnDelete();

            $table->primary(['rol_id', 'permiso_id']);
        });

        // Asignación user <-> rol con alcance (departamento o global) y vigencia
        Schema::create('user_rol', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->constrained('users', 'id', 'fk_ur_user')
                  ->restrictOnDelete();
            $table->foreignId('rol_id')
                  ->constrained('roles', 'id', 'fk_ur_rol')
                  ->restrictOnDelete();
            $table->foreignId('departamento_id')
                  ->nullable()
                  ->constrained('departamentos', 'id', 'fk_ur_depto')
                  ->restrictOnDelete()
                  ->comment('Scope del rol a un departamento específico');
            $table->boolean('alcance_global')->default(false)
                  ->comment('Explicito; requiere autorizacion del servicio');
            $table->date('vigencia_inicio')->nullable();
            $table->date('vigencia_fin')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            // Índice para consultas de roles vigentes del usuario
            $table->index(['user_id', 'activo', 'vigencia_inicio', 'vigencia_fin'], 'idx_ur_vigencia');
            // Índice para consultas por departamento
            $table->index(['departamento_id', 'rol_id'], 'idx_ur_depto_rol');
        });

        // Restricciones explícitas por cuenta OPEX para una asignación de rol
        // (reemplaza la antigua columna JSON user_rol.restricciones_opex)
        Schema::create('user_rol_categoria_restriccion', function (Blueprint $table) {
            $table->foreignId('user_rol_id')
                  ->constrained('user_rol', 'id', 'fk_restriccion_asignacion')
                  ->restrictOnDelete();
            $table->foreignId('categoria_opex_id')
                  ->constrained('categorias_opex', 'id', 'fk_restriccion_categoria')
                  ->restrictOnDelete();

            $table->primary(['user_rol_id', 'categoria_opex_id']);
        });

        // Historial de asignaciones de roles
        Schema::create('historial_asignaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->constrained('users', 'id', 'fk_hist_user')
                  ->restrictOnDelete();
            $table->foreignId('rol_id')
                  ->constrained('roles', 'id', 'fk_hist_rol')
                  ->restrictOnDelete();
            $table->string('accion', 20)->comment('asignar, revocar, modificar');
            $table->json('datos_anteriores')->nullable();
            $table->json('datos_nuevos')->nullable();
            $table->string('motivo', 500);
            $table->foreignId('ejecutado_por')
                  ->nullable()
                  ->constrained('users', 'id', 'fk_hist_actor')
                  ->restrictOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'created_at'], 'idx_hist_user_fecha');
        });

        if ($esMysql) {
            DB::statement('ALTER TABLE roles ADD CONSTRAINT ck_rol_dinamico CHECK (es_dinamico IN (0,1))');
            DB::statement('ALTER TABLE user_rol
                ADD CONSTRAINT ck_ur_vigencia CHECK (vigencia_inicio IS NULL OR vigencia_fin IS NULL OR vigencia_fin >= vigencia_inicio),
                ADD CONSTRAINT ck_ur_alcance CHECK ((alcance_global=1 AND departamento_id IS NULL) OR (alcance_global=0 AND departamento_id IS NOT NULL)),
                ADD CONSTRAINT ck_ur_activo CHECK (activo IN (0,1))');
            DB::statement("ALTER TABLE historial_asignaciones
                ADD CONSTRAINT ck_hist_accion CHECK (accion IN ('asignar','revocar','modificar'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historial_asignaciones');
        Schema::dropIfExists('user_rol_categoria_restriccion');
        Schema::dropIfExists('user_rol');
        Schema::dropIfExists('rol_permiso');
        Schema::dropIfExists('permisos');
        Schema::dropIfExists('roles');
    }
};
