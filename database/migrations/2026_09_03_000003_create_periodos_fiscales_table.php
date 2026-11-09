<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * clave_periodo es una columna generada (STORED) que identifica de forma
     * única el periodo: 2026-A (anual), 2026-T3 (trimestre), 2026-M08 (mes).
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        // SQLite (usado por PHPUnit) no tiene CONCAT/LPAD; se usa su sintaxis equivalente.
        $expresionClave = $driver === 'sqlite'
            ? "anio || CASE WHEN mes IS NOT NULL THEN '-M' || printf('%02d', mes) "
              . "WHEN trimestre IS NOT NULL THEN '-T' || trimestre ELSE '-A' END"
            : "CONCAT(anio, CASE WHEN mes IS NOT NULL THEN CONCAT('-M', LPAD(mes, 2, '0')) "
              . "WHEN trimestre IS NOT NULL THEN CONCAT('-T', trimestre) ELSE '-A' END)";

        Schema::create('periodos_fiscales', function (Blueprint $table) use ($expresionClave) {
            $table->id();
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('trimestre')->nullable()->comment('1-4');
            $table->unsignedTinyInteger('mes')->nullable()->comment('1-12');
            $table->string('clave_periodo', 12)->storedAs($expresionClave);
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->boolean('cerrado')->default(false);
            $table->timestamps();

            $table->unique('clave_periodo', 'uq_periodo_clave');
            $table->index(['fecha_inicio', 'fecha_fin'], 'idx_periodo_fechas');
        });

        if (in_array($driver, ['mysql', 'mariadb'])) {
            DB::statement('ALTER TABLE periodos_fiscales
                ADD CONSTRAINT ck_periodo_anio CHECK (anio BETWEEN 1900 AND 9999),
                ADD CONSTRAINT ck_periodo_trimestre CHECK (trimestre IS NULL OR trimestre BETWEEN 1 AND 4),
                ADD CONSTRAINT ck_periodo_mes CHECK (mes IS NULL OR mes BETWEEN 1 AND 12),
                ADD CONSTRAINT ck_periodo_coherencia CHECK (mes IS NULL OR trimestre IS NULL OR trimestre = CEILING(mes/3)),
                ADD CONSTRAINT ck_periodo_fechas CHECK (fecha_inicio <= fecha_fin AND YEAR(fecha_inicio)=anio AND YEAR(fecha_fin)=anio),
                ADD CONSTRAINT ck_periodo_cerrado CHECK (cerrado IN (0,1))');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('periodos_fiscales');
    }
};
