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
        Schema::create('presupuestos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('departamento_id')
                  ->constrained('departamentos', 'id', 'fk_pres_depto')
                  ->restrictOnDelete();
            $table->foreignId('periodo_fiscal_id')
                  ->constrained('periodos_fiscales', 'id', 'fk_pres_periodo')
                  ->restrictOnDelete();
            $table->decimal('monto_total', 15, 2)->default(0);
            $table->decimal('monto_ejercido', 15, 2)->default(0);
            $table->decimal('monto_comprometido', 15, 2)->default(0);
            $table->decimal('monto_disponible', 15, 2)
                  ->storedAs('monto_total - monto_ejercido - monto_comprometido')
                  ->comment('Columna calculada: total - ejercido - comprometido');
            $table->string('estado', 20)->default('borrador');
            $table->foreignId('creado_por')
                  ->nullable()
                  ->constrained('users', 'id', 'fk_pres_creador')
                  ->restrictOnDelete();
            $table->foreignId('aprobado_por')
                  ->nullable()
                  ->constrained('users', 'id', 'fk_pres_aprobador')
                  ->restrictOnDelete();
            $table->timestamp('aprobado_en')->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();

            // Un departamento solo puede tener un presupuesto por período fiscal
            $table->unique(['departamento_id', 'periodo_fiscal_id'], 'uq_presupuesto_depto_periodo');
            // Índice compuesto para consultas por departamento, período y estado
            $table->index(['departamento_id', 'periodo_fiscal_id', 'estado'], 'idx_pres_depto_periodo_estado');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            DB::statement("ALTER TABLE presupuestos
                ADD CONSTRAINT ck_pres_montos CHECK (monto_total>=0 AND monto_ejercido>=0 AND monto_comprometido>=0 AND monto_ejercido+monto_comprometido<=monto_total),
                ADD CONSTRAINT ck_pres_estado CHECK (estado IN ('borrador','en_revision','aprobado','rechazado','ejecutando','cerrado'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('presupuestos');
    }
};
