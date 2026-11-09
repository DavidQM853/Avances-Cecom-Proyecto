<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alineada con CECOM_Base_de_Datos.sql (cecom_presupuestos_er).
 * Todas las FK usan ON DELETE RESTRICT, igual que el script oficial.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('departamentos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('codigo', 20)->unique();
            $table->text('descripcion')->nullable();
            $table->foreignId('departamento_padre_id')
                  ->nullable()
                  ->constrained('departamentos', 'id', 'fk_depto_padre')
                  ->restrictOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index('activo', 'idx_depto_activo');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            DB::statement('ALTER TABLE departamentos ADD CONSTRAINT ck_depto_activo CHECK (activo IN (0,1))');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('departamentos');
    }
};
