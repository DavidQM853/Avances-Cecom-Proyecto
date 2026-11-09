<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reservas / compromisos presupuestales sobre una partida (CECOM_Base_de_Datos.sql).
 * Debe existir antes de gastos_operativos por la FK compuesta (compromiso_id, partida_id).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('compromisos_reserva', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partida_id')
                  ->constrained('partidas_presupuestales', 'id', 'fk_reserva_partida')
                  ->restrictOnDelete();
            $table->foreignId('creado_por')
                  ->constrained('users', 'id', 'fk_reserva_creador')
                  ->restrictOnDelete();
            $table->string('referencia_documento');
            $table->decimal('monto_original', 15, 2);
            $table->decimal('monto_liberado', 15, 2)->default(0);
            $table->string('estado', 15)->default('activa');
            $table->timestamps();

            // Permite la FK compuesta desde gastos_operativos (garantiza misma partida)
            $table->unique(['id', 'partida_id'], 'uq_reserva_id_partida');
            $table->index(['partida_id', 'estado'], 'idx_reserva_partida_estado');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            DB::statement("ALTER TABLE compromisos_reserva
                ADD CONSTRAINT ck_reserva_montos CHECK (monto_original>=0.01 AND monto_liberado>=0 AND monto_liberado<=monto_original),
                ADD CONSTRAINT ck_reserva_estado CHECK (estado IN ('activa','cerrada','cancelada'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compromisos_reserva');
    }
};

