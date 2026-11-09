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
        Schema::create('gastos_operativos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partida_id')
                  ->constrained('partidas_presupuestales')
                  ->cascadeOnDelete();
            $table->foreignId('registrado_por')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->decimal('monto', 15, 2);
            $table->string('concepto');
            $table->string('referencia_documento')->nullable()
                  ->comment('Número de factura, orden de compra, etc.');
            $table->date('fecha_gasto')->comment('Fecha real del gasto');
            $table->date('fecha_registro')->comment('Fecha de registro en el sistema');
            $table->json('metadata')->nullable()
                  ->comment('Datos adicionales en formato JSON');
            $table->timestamps();

            // Índice temporal compuesto para filtrado por fecha y partida
            $table->index(['fecha_gasto', 'partida_id'], 'idx_gastos_fecha_partida');
            // Índice para consultas por fecha de registro
            $table->index(['fecha_registro', 'partida_id'], 'idx_gastos_fecha_registro');
            // Índice para búsquedas por referencia
            $table->index('referencia_documento', 'idx_gastos_referencia');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gastos_operativos');
    }
};
