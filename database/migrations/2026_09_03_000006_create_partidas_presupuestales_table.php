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
        Schema::create('partidas_presupuestales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presupuesto_id')
                  ->constrained('presupuestos')
                  ->cascadeOnDelete();
            $table->foreignId('categoria_opex_id')
                  ->constrained('categorias_opex')
                  ->cascadeOnDelete();
            $table->decimal('monto_asignado', 15, 2);
            $table->decimal('monto_ejercido', 15, 2)->default(0);
            $table->decimal('monto_comprometido', 15, 2)->default(0);
            $table->decimal('monto_disponible', 15, 2)
                  ->storedAs('monto_asignado - monto_ejercido - monto_comprometido');
            $table->timestamps();

            // Índice para consultas de partidas por presupuesto y categoría
            $table->index(['presupuesto_id', 'categoria_opex_id'], 'idx_partidas_presupuesto_cat');
            // Una categoría OPEX solo aparece una vez por presupuesto
            $table->unique(['presupuesto_id', 'categoria_opex_id'], 'idx_partida_unica');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partidas_presupuestales');
    }
};
