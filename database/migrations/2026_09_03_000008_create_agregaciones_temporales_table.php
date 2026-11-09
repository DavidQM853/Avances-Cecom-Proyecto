<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * categoria_clave = COALESCE(categoria_opex_id, 0) permite que el UNIQUE
     * funcione también para las agregaciones "todas las categorías" (NULL).
     */
    public function up(): void
    {
        Schema::create('agregaciones_temporales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('departamento_id')
                  ->constrained('departamentos', 'id', 'fk_agg_depto')
                  ->restrictOnDelete();
            $table->foreignId('categoria_opex_id')
                  ->nullable()
                  ->constrained('categorias_opex', 'id', 'fk_agg_categoria')
                  ->restrictOnDelete();
            $table->unsignedBigInteger('categoria_clave')->storedAs('COALESCE(categoria_opex_id, 0)');
            $table->string('periodo_tipo', 20)->comment('diario, quincenal, mensual, trimestral, semestral, anual');
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->decimal('monto_total', 15, 2)->default(0);
            $table->decimal('monto_promedio', 15, 2)->default(0);
            $table->decimal('variacion_porcentual', 12, 4)->nullable()
                  ->comment('Variación respecto al período anterior');
            $table->unsignedInteger('cantidad_transacciones')->default(0);
            $table->json('desglose')->nullable()
                  ->comment('Desglose detallado por subcategorías');
            $table->timestamps();

            // Evitar duplicados por contexto completo
            $table->unique(
                ['departamento_id', 'categoria_clave', 'periodo_tipo', 'fecha_inicio', 'fecha_fin'],
                'uq_agregacion_contexto'
            );
            // Índice para consultas por tipo de período
            $table->index(['periodo_tipo', 'fecha_inicio'], 'idx_agg_tipo_inicio');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            DB::statement("ALTER TABLE agregaciones_temporales
                ADD CONSTRAINT ck_agg_tipo CHECK (periodo_tipo IN ('diario','quincenal','mensual','trimestral','semestral','anual')),
                ADD CONSTRAINT ck_agg_fechas CHECK (fecha_inicio<=fecha_fin),
                ADD CONSTRAINT ck_agg_montos CHECK (monto_total>=0 AND monto_promedio>=0)");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agregaciones_temporales');
    }
};
