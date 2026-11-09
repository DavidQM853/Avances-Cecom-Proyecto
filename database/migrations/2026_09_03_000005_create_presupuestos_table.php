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
        Schema::create('presupuestos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('departamento_id')
                  ->constrained('departamentos')
                  ->cascadeOnDelete();
            $table->foreignId('periodo_fiscal_id')
                  ->constrained('periodos_fiscales')
                  ->cascadeOnDelete();
            $table->decimal('monto_total', 15, 2);
            $table->decimal('monto_ejercido', 15, 2)->default(0);
            $table->decimal('monto_comprometido', 15, 2)->default(0);
            $table->decimal('monto_disponible', 15, 2)
                  ->storedAs('monto_total - monto_ejercido - monto_comprometido')
                  ->comment('Columna calculada: total - ejercido - comprometido');
            $table->string('estado')->default('borrador');
            $table->foreignId('creado_por')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->foreignId('aprobado_por')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamp('aprobado_en')->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();

            // Índice compuesto para consultas de presupuestos por departamento y período
            $table->index(['departamento_id', 'periodo_fiscal_id', 'estado'], 'idx_presupuestos_depto_periodo');
            // Un departamento solo puede tener un presupuesto por período fiscal
            $table->unique(['departamento_id', 'periodo_fiscal_id'], 'idx_presupuesto_unico');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('presupuestos');
    }
};
