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
        Schema::create('periodos_fiscales', function (Blueprint $table) {
            $table->id();
            $table->year('anio');
            $table->unsignedTinyInteger('trimestre')->nullable()->comment('1-4');
            $table->unsignedTinyInteger('mes')->nullable()->comment('1-12');
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->boolean('cerrado')->default(false);
            $table->timestamps();

            $table->unique(['anio', 'trimestre', 'mes'], 'idx_periodo_unico');
            $table->index(['fecha_inicio', 'fecha_fin']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('periodos_fiscales');
    }
};
