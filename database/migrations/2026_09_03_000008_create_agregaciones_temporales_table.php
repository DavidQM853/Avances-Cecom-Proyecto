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
        Schema::create('agregaciones_temporales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('departamento_id')
                  ->constrained('departamentos')
                  ->cascadeOnDelete();
            $table->foreignId('categoria_opex_id')
                  ->nullable()
                  ->constrained('categorias_opex')
                  ->nullOnDelete();
            $table->string('periodo_tipo', 20)->comment('diario, quincenal, mensual, trimestral, semestral, anual');
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->decimal('monto_total', 15, 2)->default(0);
            $table->decimal('monto_promedio', 15, 2)->default(0);
            $table->decimal('variacion_porcentual', 8, 4)->nullable()
                  ->comment('Variación respecto al período anterior');
            $table->unsignedInteger('cantidad_transacciones')->default(0);
            $table->json('desglose')->nullable()
                  ->comment('Desglose detallado por subcategorías');
            $table->timestamps();

            // Índice principal para consultas de agregación
            $table->index(
                ['departamento_id', 'categoria_opex_id', 'periodo_tipo', 'fecha_inicio', 'fecha_fin'],
                'idx_agregaciones_lookup'
            );
            // Índice para consultas por tipo de período
            $table->index(['periodo_tipo', 'fecha_inicio'], 'idx_agregaciones_periodo');
            // Evitar duplicados
            $table->unique(
                ['departamento_id', 'categoria_opex_id', 'periodo_tipo', 'fecha_inicio'],
                'idx_agregacion_unica'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agregaciones_temporales');
    }
};
