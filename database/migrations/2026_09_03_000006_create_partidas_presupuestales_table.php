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
        Schema::create('partidas_presupuestales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presupuesto_id')
                  ->constrained('presupuestos', 'id', 'fk_partida_pres')
                  ->restrictOnDelete();
            $table->foreignId('categoria_opex_id')
                  ->constrained('categorias_opex', 'id', 'fk_partida_categoria')
                  ->restrictOnDelete();
            $table->decimal('monto_asignado', 15, 2);
            $table->decimal('monto_ejercido', 15, 2)->default(0);
            $table->decimal('monto_comprometido', 15, 2)->default(0);
            $table->decimal('monto_disponible', 15, 2)
                  ->storedAs('monto_asignado - monto_ejercido - monto_comprometido');
            $table->timestamps();

            // Una categoría OPEX solo aparece una vez por presupuesto
            $table->unique(['presupuesto_id', 'categoria_opex_id'], 'uq_partida_pres_categoria');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            DB::statement('ALTER TABLE partidas_presupuestales
                ADD CONSTRAINT ck_partida_montos CHECK (monto_asignado>=0.01 AND monto_ejercido>=0 AND monto_comprometido>=0 AND monto_ejercido+monto_comprometido<=monto_asignado)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partidas_presupuestales');
    }
};
