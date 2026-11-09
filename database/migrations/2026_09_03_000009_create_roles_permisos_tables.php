<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Tabla de roles
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('slug')->unique();
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
            $table->string('slug')->unique();
            $table->string('modulo', 50)->comment('Módulo al que pertenece: presupuestos, gastos, reportes, etc.');
            $table->text('descripcion')->nullable();
            $table->timestamps();

            $table->index('modulo');
        });

        // Pivot: rol <-> permiso
        Schema::create('rol_permiso', function (Blueprint $table) {
            $table->foreignId('rol_id')
                  ->constrained('roles')
                  ->cascadeOnDelete();
            $table->foreignId('permiso_id')
                  ->constrained('permisos')
                  ->cascadeOnDelete();

            $table->primary(['rol_id', 'permiso_id']);
        });

        // Pivot: user <-> rol (con restricciones OPEX y vigencia)
        Schema::create('user_rol', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();
            $table->foreignId('rol_id')
                  ->constrained('roles')
                  ->cascadeOnDelete();
            $table->foreignId('departamento_id')
                  ->nullable()
                  ->constrained('departamentos')
                  ->nullOnDelete()
                  ->comment('Scope del rol a un departamento específico');
            $table->json('restricciones_opex')->nullable()
                  ->comment('Restricciones sobre categorías OPEX: IDs de categorías permitidas');
            $table->date('vigencia_inicio')->nullable();
            $table->date('vigencia_fin')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            // Índice para consultas de permisos efectivos del usuario
            $table->index(['user_id', 'vigencia_inicio', 'vigencia_fin'], 'idx_user_rol_vigencia');
            // Índice para consultas por departamento
            $table->index(['departamento_id', 'rol_id'], 'idx_user_rol_depto');
        });

        // Historial de asignaciones de roles
        Schema::create('historial_asignaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();
            $table->foreignId('rol_id')
                  ->constrained('roles')
                  ->cascadeOnDelete();
            $table->string('accion', 20)->comment('asignar, revocar, modificar');
            $table->json('datos_anteriores')->nullable();
            $table->json('datos_nuevos')->nullable();
            $table->string('motivo')->nullable();
            $table->foreignId('ejecutado_por')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'created_at'], 'idx_historial_usuario');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historial_asignaciones');
        Schema::dropIfExists('user_rol');
        Schema::dropIfExists('rol_permiso');
        Schema::dropIfExists('permisos');
        Schema::dropIfExists('roles');
    }
};
