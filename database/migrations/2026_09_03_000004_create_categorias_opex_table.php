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
        Schema::create('categorias_opex', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('codigo', 20)->unique();
            $table->text('descripcion')->nullable();
            $table->string('tipo_gasto', 30)->nullable()->comment('Valor del enum TipoGastoOperativo');
            $table->foreignId('categoria_padre_id')
                  ->nullable()
                  ->constrained('categorias_opex', 'id', 'fk_categoria_padre')
                  ->restrictOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index('tipo_gasto', 'idx_categoria_tipo');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            DB::statement("ALTER TABLE categorias_opex
                ADD CONSTRAINT ck_categoria_tipo CHECK (tipo_gasto IS NULL OR tipo_gasto IN ('nomina','servicios','materiales','mantenimiento','tecnologia','capacitacion','viaticos','arrendamiento','seguros','otros')),
                ADD CONSTRAINT ck_categoria_activo CHECK (activo IN (0,1))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categorias_opex');
    }
};
