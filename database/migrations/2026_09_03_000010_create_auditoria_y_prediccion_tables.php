<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tablas de bitácora, modelos predictivos, proyecciones anuales y vistas de conciliación
 * alineadas con CECOM_Base_de_Datos.sql (cecom_presupuestos_er).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $esMysql = in_array(DB::getDriverName(), ['mysql', 'mariadb']);

        // 1. Bitácora general de eventos de auditoría
        Schema::create('bitacora_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')
                  ->nullable()
                  ->constrained('users', 'id', 'fk_evento_actor')
                  ->restrictOnDelete();
            $table->string('entidad_tipo', 40);
            $table->unsignedBigInteger('entidad_id')
                  ->comment('Referencia logica validada por servicio; no FK universal');
            $table->string('accion', 40);
            $table->string('motivo', 500);
            $table->json('valores_antes')->nullable();
            $table->json('valores_despues')->nullable();
            $table->timestamp('ocurrido_en')->useCurrent();

            $table->index(['entidad_tipo', 'entidad_id', 'ocurrido_en'], 'idx_evento_objetivo');
            $table->index(['actor_id', 'ocurrido_en'], 'idx_evento_actor_fecha');
        });

        // 2. Modelos predictivos entrenados
        Schema::create('modelos_predictivos', function (Blueprint $table) {
            $table->id();
            $table->string('version', 100)->unique();
            $table->string('version_datos', 255);
            $table->date('entrenamiento_desde');
            $table->date('entrenamiento_hasta');
            $table->date('validacion_desde');
            $table->date('validacion_hasta');
            $table->json('metricas_validacion');
            $table->string('referencia_artefacto', 1024);
            $table->timestamps();
        });

        // 3. Proyecciones presupuestales anuales
        Schema::create('proyecciones_anuales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modelo_id')
                  ->constrained('modelos_predictivos', 'id', 'fk_proy_modelo')
                  ->restrictOnDelete();
            $table->foreignId('departamento_id')
                  ->constrained('departamentos', 'id', 'fk_proy_depto')
                  ->restrictOnDelete();
            $table->unsignedSmallInteger('anio_base');
            $table->unsignedSmallInteger('anio_objetivo');
            $table->date('fecha_corte_datos');
            $table->decimal('monto_proyectado', 15, 2);
            $table->timestamp('generado_en')->useCurrent();

            $table->unique(
                ['modelo_id', 'departamento_id', 'anio_base', 'anio_objetivo', 'fecha_corte_datos'],
                'uq_proyeccion_version_corte'
            );
            $table->index(['departamento_id', 'anio_objetivo'], 'idx_proyeccion_depto_anio');
        });

        if ($esMysql) {
            DB::statement("ALTER TABLE bitacora_eventos
                ADD CONSTRAINT ck_evento_tipo CHECK (entidad_tipo IN ('departamentos','periodos_fiscales','categorias_opex','users','roles','permisos','rol_permiso','user_rol','user_rol_categoria_restriccion','presupuestos','partidas_presupuestales','compromisos_reserva','gastos_operativos','modelos_predictivos','proyecciones_anuales'))");

            DB::statement('ALTER TABLE modelos_predictivos
                ADD CONSTRAINT ck_modelo_fechas CHECK (entrenamiento_desde<=entrenamiento_hasta AND entrenamiento_hasta<validacion_desde AND validacion_desde<=validacion_hasta)');

            DB::statement('ALTER TABLE proyecciones_anuales
                ADD CONSTRAINT ck_proy_anios CHECK (anio_base BETWEEN 1900 AND 9998 AND anio_objetivo=anio_base+1),
                ADD CONSTRAINT ck_proy_monto CHECK (monto_proyectado>=0)');
        }

        // 4. Vistas de comprobación y conciliación
        DB::statement('DROP VIEW IF EXISTS v_presupuestos_conciliacion');
        DB::statement('DROP VIEW IF EXISTS v_partidas_conciliacion');
        DB::statement('DROP VIEW IF EXISTS v_reservas_conciliacion');

        DB::statement("CREATE VIEW v_reservas_conciliacion AS
            SELECT r.id AS compromiso_id, r.partida_id, r.estado, r.monto_original, r.monto_liberado,
                   COALESCE(g.ejercido, 0) AS monto_ejercido,
                   r.monto_original - r.monto_liberado - COALESCE(g.ejercido, 0) AS saldo_pendiente,
                   CASE WHEN r.monto_original - r.monto_liberado - COALESCE(g.ejercido, 0) < 0
                      OR (r.estado <> 'activa' AND r.monto_original - r.monto_liberado - COALESCE(g.ejercido, 0) <> 0)
                      THEN 1 ELSE 0 END AS requiere_revision
            FROM compromisos_reserva r
            LEFT JOIN (SELECT compromiso_id, SUM(monto) AS ejercido FROM gastos_operativos
                       WHERE compromiso_id IS NOT NULL GROUP BY compromiso_id) g ON g.compromiso_id = r.id");

        DB::statement("CREATE VIEW v_partidas_conciliacion AS
            SELECT p.id AS partida_id, p.presupuesto_id, p.monto_asignado, p.monto_ejercido, p.monto_comprometido,
                   COALESCE(g.ejercido, 0) AS ejercido_segun_gastos,
                   COALESCE(r.pendiente, 0) AS comprometido_segun_reservas,
                   p.monto_asignado - COALESCE(g.ejercido, 0) - COALESCE(r.pendiente, 0) AS disponible_segun_movimientos,
                   CASE WHEN p.monto_ejercido <> COALESCE(g.ejercido, 0)
                     OR p.monto_comprometido <> COALESCE(r.pendiente, 0) THEN 1 ELSE 0 END AS requiere_revision
            FROM partidas_presupuestales p
            LEFT JOIN (SELECT partida_id, SUM(monto) AS ejercido FROM gastos_operativos GROUP BY partida_id) g ON g.partida_id = p.id
            LEFT JOIN (SELECT partida_id, SUM(saldo_pendiente) AS pendiente FROM v_reservas_conciliacion GROUP BY partida_id) r ON r.partida_id = p.id");

        DB::statement("CREATE VIEW v_presupuestos_conciliacion AS
            SELECT p.id AS presupuesto_id, p.monto_total, p.monto_ejercido, p.monto_comprometido, p.monto_disponible,
                   COALESCE(d.asignado, 0) AS total_segun_partidas,
                   COALESCE(d.ejercido, 0) AS ejercido_segun_partidas,
                   COALESCE(d.comprometido, 0) AS comprometido_segun_partidas,
                   CASE WHEN COALESCE(d.partidas, 0) = 0 OR p.monto_total <> COALESCE(d.asignado, 0)
                     OR p.monto_ejercido <> COALESCE(d.ejercido, 0) OR p.monto_comprometido <> COALESCE(d.comprometido, 0)
                     THEN 1 ELSE 0 END AS requiere_revision
            FROM presupuestos p
            LEFT JOIN (SELECT presupuesto_id, COUNT(*) AS partidas, SUM(monto_asignado) AS asignado,
                       SUM(monto_ejercido) AS ejercido, SUM(monto_comprometido) AS comprometido
                       FROM partidas_presupuestales GROUP BY presupuesto_id) d ON d.presupuesto_id = p.id");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_presupuestos_conciliacion');
        DB::statement('DROP VIEW IF EXISTS v_partidas_conciliacion');
        DB::statement('DROP VIEW IF EXISTS v_reservas_conciliacion');
        Schema::dropIfExists('proyecciones_anuales');
        Schema::dropIfExists('modelos_predictivos');
        Schema::dropIfExists('bitacora_eventos');
    }
};

