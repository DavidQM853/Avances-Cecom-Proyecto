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
        Schema::create('categorias_opex', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('codigo', 20)->unique();
            $table->text('descripcion')->nullable();
            $table->string('tipo_gasto')->nullable()->comment('Valor del enum TipoGastoOperativo');
            $table->foreignId('categoria_padre_id')
                  ->nullable()
                  ->constrained('categorias_opex')
                  ->nullOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index('activo');
            $table->index('tipo_gasto');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categorias_opex');
    }
};
