<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega el campo departamento_id y activo a la tabla users
     * (alineado con CECOM_Base_de_Datos.sql: FK RESTRICT + CHECK activo).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('departamento_id')
                  ->nullable()
                  ->after('remember_token')
                  ->constrained('departamentos', 'id', 'fk_user_depto')
                  ->restrictOnDelete();
            $table->boolean('activo')->default(true)->after('departamento_id');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            DB::statement('ALTER TABLE users ADD CONSTRAINT ck_user_activo CHECK (activo IN (0,1))');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            DB::statement('ALTER TABLE users DROP CHECK ck_user_activo');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign('fk_user_depto');
            $table->dropColumn(['departamento_id', 'activo']);
        });
    }
};
