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
        Schema::create('gastos_operativos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partida_id')
                  ->constrained('partidas_presupuestales', 'id', 'fk_gasto_partida')
                  ->restrictOnDelete();
            $table->foreignId('registrado_por')
                  ->nullable()
                  ->constrained('users', 'id', 'fk_gasto_autor')
                  ->restrictOnDelete();
            $table->unsignedBigInteger('compromiso_id')->nullable()
                  ->comment('Reserva consumida por el gasto (opcional)');
            $table->decimal('monto', 15, 2);
            $table->string('concepto');
            $table->string('referencia_documento')->nullable()
                  ->comment('Número de factura, orden de compra, etc.');
            $table->date('fecha_gasto')->comment('Fecha real del gasto');
            $table->date('fecha_registro')->comment('Fecha de registro en el sistema');
            $table->json('metadata')->nullable()
                  ->comment('Datos adicionales en formato JSON');
            $table->timestamps();

            // FK compuesta: la reserva debe pertenecer a la MISMA partida del gasto
            $table->foreign(['compromiso_id', 'partida_id'], 'fk_gasto_reserva_partida')
                  ->references(['id', 'partida_id'])
                  ->on('compromisos_reserva')
                  ->restrictOnDelete();

            // Índice temporal compuesto para filtrado por fecha y partida
            $table->index(['fecha_gasto', 'partida_id'], 'idx_gasto_fecha_partida');
            // Índice para consultas por fecha de registro
            $table->index(['fecha_registro', 'partida_id'], 'idx_gasto_registro');
            // Índice para búsquedas por referencia
            $table->index('referencia_documento', 'idx_gasto_referencia');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            DB::statement('ALTER TABLE gastos_operativos ADD CONSTRAINT ck_gasto_monto CHECK (monto>=0.01)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gastos_operativos');
    }
};
