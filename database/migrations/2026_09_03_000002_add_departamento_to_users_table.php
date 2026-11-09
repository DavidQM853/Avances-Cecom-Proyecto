<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega el campo departamento_id y activo a la tabla users.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('departamento_id')
                  ->nullable()
                  ->after('password')
                  ->constrained('departamentos')
                  ->nullOnDelete();
            $table->boolean('activo')->default(true)->after('departamento_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['departamento_id']);
            $table->dropColumn(['departamento_id', 'activo']);
        });
    }
};
