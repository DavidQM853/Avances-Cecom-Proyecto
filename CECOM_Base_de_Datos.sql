-- CECOM modelo ER propuesto. Base NUEVA, no es una migracion del backend.
-- Destino: MySQL 8.0.16+ / MariaDB 10.6+. Ver LEEME antes de importar.
-- No contiene DROP, no desactiva claves foraneas, no crea usuarios del servidor.
-- El nombre debe estar libre. La segunda importacion se detiene con error.
SET NAMES utf8mb4;
CREATE DATABASE cecom_presupuestos_er CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cecom_presupuestos_er;

CREATE TABLE departamentos (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(255) NOT NULL,
  codigo VARCHAR(20) NOT NULL UNIQUE,
  descripcion TEXT NULL,
  departamento_padre_id BIGINT UNSIGNED NULL,
  activo BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_depto_padre FOREIGN KEY (departamento_padre_id) REFERENCES departamentos(id) ON DELETE RESTRICT,
  CONSTRAINT ck_depto_activo CHECK (activo IN (0,1)),
  INDEX idx_depto_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE periodos_fiscales (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  anio SMALLINT UNSIGNED NOT NULL,
  trimestre TINYINT UNSIGNED NULL,
  mes TINYINT UNSIGNED NULL,
  clave_periodo VARCHAR(12) GENERATED ALWAYS AS
    (CONCAT(anio, CASE WHEN mes IS NOT NULL THEN CONCAT('-M',LPAD(mes,2,'0'))
     WHEN trimestre IS NOT NULL THEN CONCAT('-T',trimestre) ELSE '-A' END)) STORED,
  fecha_inicio DATE NOT NULL,
  fecha_fin DATE NOT NULL,
  cerrado BOOLEAN NOT NULL DEFAULT FALSE,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_periodo_clave (clave_periodo),
  CONSTRAINT ck_periodo_anio CHECK (anio BETWEEN 1900 AND 9999),
  CONSTRAINT ck_periodo_trimestre CHECK (trimestre IS NULL OR trimestre BETWEEN 1 AND 4),
  CONSTRAINT ck_periodo_mes CHECK (mes IS NULL OR mes BETWEEN 1 AND 12),
  CONSTRAINT ck_periodo_coherencia CHECK (mes IS NULL OR trimestre IS NULL OR trimestre = CEILING(mes/3)),
  CONSTRAINT ck_periodo_fechas CHECK (fecha_inicio <= fecha_fin AND YEAR(fecha_inicio)=anio AND YEAR(fecha_fin)=anio),
  CONSTRAINT ck_periodo_cerrado CHECK (cerrado IN (0,1)),
  INDEX idx_periodo_fechas (fecha_inicio,fecha_fin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categorias_opex (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(255) NOT NULL,
  codigo VARCHAR(20) NOT NULL UNIQUE,
  descripcion TEXT NULL,
  tipo_gasto VARCHAR(30) NULL,
  categoria_padre_id BIGINT UNSIGNED NULL,
  activo BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_categoria_padre FOREIGN KEY (categoria_padre_id) REFERENCES categorias_opex(id) ON DELETE RESTRICT,
  CONSTRAINT ck_categoria_tipo CHECK (tipo_gasto IS NULL OR tipo_gasto IN ('nomina','servicios','materiales','mantenimiento','tecnologia','capacitacion','viaticos','arrendamiento','seguros','otros')),
  CONSTRAINT ck_categoria_activo CHECK (activo IN (0,1)),
  INDEX idx_categoria_tipo (tipo_gasto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  email_verified_at TIMESTAMP NULL,
  password VARCHAR(255) NOT NULL COMMENT 'Hash generado por Laravel; nunca texto plano',
  remember_token VARCHAR(100) NULL,
  departamento_id BIGINT UNSIGNED NULL,
  activo BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_user_depto FOREIGN KEY (departamento_id) REFERENCES departamentos(id) ON DELETE RESTRICT,
  CONSTRAINT ck_user_activo CHECK (activo IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE roles (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(255) NOT NULL,
  slug VARCHAR(100) NOT NULL UNIQUE,
  descripcion TEXT NULL,
  es_dinamico BOOLEAN NOT NULL DEFAULT FALSE,
  reglas_asignacion JSON NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT ck_rol_dinamico CHECK (es_dinamico IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permisos (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(255) NOT NULL,
  slug VARCHAR(100) NOT NULL UNIQUE,
  modulo VARCHAR(50) NOT NULL,
  descripcion TEXT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_permiso_modulo (modulo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rol_permiso (
rol_id BIGINT UNSIGNED NOT NULL,
  permiso_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (rol_id,permiso_id),
  CONSTRAINT fk_rp_rol FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE RESTRICT,
  CONSTRAINT fk_rp_permiso FOREIGN KEY (permiso_id) REFERENCES permisos(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_rol (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  rol_id BIGINT UNSIGNED NOT NULL,
  departamento_id BIGINT UNSIGNED NULL,
  alcance_global BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Explicito; requiere autorizacion del servicio',
  vigencia_inicio DATE NULL,
  vigencia_fin DATE NULL,
  activo BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_ur_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ur_rol FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ur_depto FOREIGN KEY (departamento_id) REFERENCES departamentos(id) ON DELETE RESTRICT,
  CONSTRAINT ck_ur_vigencia CHECK (vigencia_inicio IS NULL OR vigencia_fin IS NULL OR vigencia_fin >= vigencia_inicio),
  CONSTRAINT ck_ur_alcance CHECK ((alcance_global=1 AND departamento_id IS NULL) OR (alcance_global=0 AND departamento_id IS NOT NULL)),
  CONSTRAINT ck_ur_activo CHECK (activo IN (0,1)),
  INDEX idx_ur_vigencia (user_id,activo,vigencia_inicio,vigencia_fin),
  INDEX idx_ur_depto_rol (departamento_id,rol_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_rol_categoria_restriccion (
user_rol_id BIGINT UNSIGNED NOT NULL,
  categoria_opex_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (user_rol_id,categoria_opex_id),
  CONSTRAINT fk_restriccion_asignacion FOREIGN KEY (user_rol_id) REFERENCES user_rol(id) ON DELETE RESTRICT,
  CONSTRAINT fk_restriccion_categoria FOREIGN KEY (categoria_opex_id) REFERENCES categorias_opex(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE historial_asignaciones (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  rol_id BIGINT UNSIGNED NOT NULL,
  accion VARCHAR(20) NOT NULL,
  datos_anteriores JSON NULL,
  datos_nuevos JSON NULL,
  motivo VARCHAR(500) NOT NULL,
  ejecutado_por BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_hist_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_hist_rol FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE RESTRICT,
  CONSTRAINT fk_hist_actor FOREIGN KEY (ejecutado_por) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT ck_hist_accion CHECK (accion IN ('asignar','revocar','modificar')),
  INDEX idx_hist_user_fecha (user_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE presupuestos (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  departamento_id BIGINT UNSIGNED NOT NULL,
  periodo_fiscal_id BIGINT UNSIGNED NOT NULL,
  monto_total DECIMAL(15,2) NOT NULL DEFAULT 0,
  monto_ejercido DECIMAL(15,2) NOT NULL DEFAULT 0,
  monto_comprometido DECIMAL(15,2) NOT NULL DEFAULT 0,
  monto_disponible DECIMAL(15,2) GENERATED ALWAYS AS (monto_total-monto_ejercido-monto_comprometido) STORED,
  estado VARCHAR(20) NOT NULL DEFAULT 'borrador',
  creado_por BIGINT UNSIGNED NULL,
  aprobado_por BIGINT UNSIGNED NULL,
  aprobado_en TIMESTAMP NULL,
  notas TEXT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_presupuesto_depto_periodo (departamento_id,periodo_fiscal_id),
  CONSTRAINT fk_pres_depto FOREIGN KEY (departamento_id) REFERENCES departamentos(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pres_periodo FOREIGN KEY (periodo_fiscal_id) REFERENCES periodos_fiscales(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pres_creador FOREIGN KEY (creado_por) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pres_aprobador FOREIGN KEY (aprobado_por) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT ck_pres_montos CHECK (monto_total>=0 AND monto_ejercido>=0 AND monto_comprometido>=0 AND monto_ejercido+monto_comprometido<=monto_total),
  CONSTRAINT ck_pres_estado CHECK (estado IN ('borrador','en_revision','aprobado','rechazado','ejecutando','cerrado')),
  INDEX idx_pres_depto_periodo_estado (departamento_id,periodo_fiscal_id,estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE partidas_presupuestales (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  presupuesto_id BIGINT UNSIGNED NOT NULL,
  categoria_opex_id BIGINT UNSIGNED NOT NULL,
  monto_asignado DECIMAL(15,2) NOT NULL,
  monto_ejercido DECIMAL(15,2) NOT NULL DEFAULT 0,
  monto_comprometido DECIMAL(15,2) NOT NULL DEFAULT 0,
  monto_disponible DECIMAL(15,2) GENERATED ALWAYS AS (monto_asignado-monto_ejercido-monto_comprometido) STORED,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_partida_pres_categoria (presupuesto_id,categoria_opex_id),
  CONSTRAINT fk_partida_pres FOREIGN KEY (presupuesto_id) REFERENCES presupuestos(id) ON DELETE RESTRICT,
  CONSTRAINT fk_partida_categoria FOREIGN KEY (categoria_opex_id) REFERENCES categorias_opex(id) ON DELETE RESTRICT,
  CONSTRAINT ck_partida_montos CHECK (monto_asignado>=0.01 AND monto_ejercido>=0 AND monto_comprometido>=0 AND monto_ejercido+monto_comprometido<=monto_asignado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE compromisos_reserva (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  partida_id BIGINT UNSIGNED NOT NULL,
  creado_por BIGINT UNSIGNED NOT NULL,
  referencia_documento VARCHAR(255) NOT NULL,
  monto_original DECIMAL(15,2) NOT NULL,
  monto_liberado DECIMAL(15,2) NOT NULL DEFAULT 0,
  estado VARCHAR(15) NOT NULL DEFAULT 'activa',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_reserva_id_partida (id,partida_id),
  CONSTRAINT fk_reserva_partida FOREIGN KEY (partida_id) REFERENCES partidas_presupuestales(id) ON DELETE RESTRICT,
  CONSTRAINT fk_reserva_creador FOREIGN KEY (creado_por) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT ck_reserva_montos CHECK (monto_original>=0.01 AND monto_liberado>=0 AND monto_liberado<=monto_original),
  CONSTRAINT ck_reserva_estado CHECK (estado IN ('activa','cerrada','cancelada')),
  INDEX idx_reserva_partida_estado (partida_id,estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE gastos_operativos (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  partida_id BIGINT UNSIGNED NOT NULL,
  registrado_por BIGINT UNSIGNED NULL,
  compromiso_id BIGINT UNSIGNED NULL,
  monto DECIMAL(15,2) NOT NULL,
  concepto VARCHAR(255) NOT NULL,
  referencia_documento VARCHAR(255) NULL,
  fecha_gasto DATE NOT NULL,
  fecha_registro DATE NOT NULL,
  metadata JSON NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_gasto_partida FOREIGN KEY (partida_id) REFERENCES partidas_presupuestales(id) ON DELETE RESTRICT,
  CONSTRAINT fk_gasto_autor FOREIGN KEY (registrado_por) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_gasto_reserva_partida FOREIGN KEY (compromiso_id,partida_id) REFERENCES compromisos_reserva(id,partida_id) ON DELETE RESTRICT,
  CONSTRAINT ck_gasto_monto CHECK (monto>=0.01),
  INDEX idx_gasto_fecha_partida (fecha_gasto,partida_id),
  INDEX idx_gasto_registro (fecha_registro,partida_id),
  INDEX idx_gasto_referencia (referencia_documento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bitacora_eventos (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_id BIGINT UNSIGNED NULL,
  entidad_tipo VARCHAR(40) NOT NULL,
  entidad_id BIGINT UNSIGNED NOT NULL COMMENT 'Referencia logica validada por servicio; no FK universal',
  accion VARCHAR(40) NOT NULL,
  motivo VARCHAR(500) NOT NULL,
  valores_antes JSON NULL,
  valores_despues JSON NULL,
  ocurrido_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_evento_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT ck_evento_tipo CHECK (entidad_tipo IN ('departamentos','periodos_fiscales','categorias_opex','users','roles','permisos','rol_permiso','user_rol','user_rol_categoria_restriccion','presupuestos','partidas_presupuestales','compromisos_reserva','gastos_operativos','modelos_predictivos','proyecciones_anuales')),
  INDEX idx_evento_objetivo (entidad_tipo,entidad_id,ocurrido_en),
  INDEX idx_evento_actor_fecha (actor_id,ocurrido_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE modelos_predictivos (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  version VARCHAR(100) NOT NULL UNIQUE,
  version_datos VARCHAR(255) NOT NULL,
  entrenamiento_desde DATE NOT NULL,
  entrenamiento_hasta DATE NOT NULL,
  validacion_desde DATE NOT NULL,
  validacion_hasta DATE NOT NULL,
  metricas_validacion JSON NOT NULL,
  referencia_artefacto VARCHAR(1024) NOT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT ck_modelo_fechas CHECK (entrenamiento_desde<=entrenamiento_hasta AND entrenamiento_hasta<validacion_desde AND validacion_desde<=validacion_hasta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE proyecciones_anuales (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  modelo_id BIGINT UNSIGNED NOT NULL,
  departamento_id BIGINT UNSIGNED NOT NULL,
  anio_base SMALLINT UNSIGNED NOT NULL,
  anio_objetivo SMALLINT UNSIGNED NOT NULL,
  fecha_corte_datos DATE NOT NULL,
  monto_proyectado DECIMAL(15,2) NOT NULL,
  generado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_proy_modelo FOREIGN KEY (modelo_id) REFERENCES modelos_predictivos(id) ON DELETE RESTRICT,
  CONSTRAINT fk_proy_depto FOREIGN KEY (departamento_id) REFERENCES departamentos(id) ON DELETE RESTRICT,
  CONSTRAINT ck_proy_anios CHECK (anio_base BETWEEN 1900 AND 9998 AND anio_objetivo=anio_base+1),
  CONSTRAINT ck_proy_monto CHECK (monto_proyectado>=0),
  UNIQUE KEY uq_proyeccion_version_corte (modelo_id,departamento_id,anio_base,anio_objetivo,fecha_corte_datos),
  INDEX idx_proyeccion_depto_anio (departamento_id,anio_objetivo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE agregaciones_temporales (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  departamento_id BIGINT UNSIGNED NOT NULL,
  categoria_opex_id BIGINT UNSIGNED NULL,
  categoria_clave BIGINT UNSIGNED GENERATED ALWAYS AS (COALESCE(categoria_opex_id,0)) STORED,
  periodo_tipo VARCHAR(20) NOT NULL,
  fecha_inicio DATE NOT NULL,
  fecha_fin DATE NOT NULL,
  monto_total DECIMAL(15,2) NOT NULL DEFAULT 0,
  monto_promedio DECIMAL(15,2) NOT NULL DEFAULT 0,
  variacion_porcentual DECIMAL(12,4) NULL,
  cantidad_transacciones INT UNSIGNED NOT NULL DEFAULT 0,
  desglose JSON NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_agg_depto FOREIGN KEY (departamento_id) REFERENCES departamentos(id) ON DELETE RESTRICT,
  CONSTRAINT fk_agg_categoria FOREIGN KEY (categoria_opex_id) REFERENCES categorias_opex(id) ON DELETE RESTRICT,
  CONSTRAINT ck_agg_tipo CHECK (periodo_tipo IN ('diario','quincenal','mensual','trimestral','semestral','anual')),
  CONSTRAINT ck_agg_fechas CHECK (fecha_inicio<=fecha_fin),
  CONSTRAINT ck_agg_montos CHECK (monto_total>=0 AND monto_promedio>=0),
  UNIQUE KEY uq_agregacion_contexto (departamento_id,categoria_clave,periodo_tipo,fecha_inicio,fecha_fin),
  INDEX idx_agg_tipo_inicio (periodo_tipo,fecha_inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vistas de comprobacion. No modifican saldos ni sustituyen transacciones.
CREATE VIEW v_reservas_conciliacion AS
SELECT r.id AS compromiso_id,r.partida_id,r.estado,r.monto_original,r.monto_liberado,
       COALESCE(g.ejercido,0) AS monto_ejercido,
       r.monto_original-r.monto_liberado-COALESCE(g.ejercido,0) AS saldo_pendiente,
       CASE WHEN r.monto_original-r.monto_liberado-COALESCE(g.ejercido,0)<0
          OR (r.estado<>'activa' AND r.monto_original-r.monto_liberado-COALESCE(g.ejercido,0)<>0)
          THEN 1 ELSE 0 END AS requiere_revision
FROM compromisos_reserva r
LEFT JOIN (SELECT compromiso_id,SUM(monto) AS ejercido FROM gastos_operativos
           WHERE compromiso_id IS NOT NULL GROUP BY compromiso_id) g ON g.compromiso_id=r.id;

CREATE VIEW v_partidas_conciliacion AS
SELECT p.id AS partida_id,p.presupuesto_id,p.monto_asignado,p.monto_ejercido,p.monto_comprometido,
       COALESCE(g.ejercido,0) AS ejercido_segun_gastos,
       COALESCE(r.pendiente,0) AS comprometido_segun_reservas,
       p.monto_asignado-COALESCE(g.ejercido,0)-COALESCE(r.pendiente,0) AS disponible_segun_movimientos,
       CASE WHEN p.monto_ejercido<>COALESCE(g.ejercido,0)
         OR p.monto_comprometido<>COALESCE(r.pendiente,0) THEN 1 ELSE 0 END AS requiere_revision
FROM partidas_presupuestales p
LEFT JOIN (SELECT partida_id,SUM(monto) AS ejercido FROM gastos_operativos GROUP BY partida_id) g ON g.partida_id=p.id
LEFT JOIN (SELECT partida_id,SUM(saldo_pendiente) AS pendiente FROM v_reservas_conciliacion GROUP BY partida_id) r ON r.partida_id=p.id;

CREATE VIEW v_presupuestos_conciliacion AS
SELECT p.id AS presupuesto_id,p.monto_total,p.monto_ejercido,p.monto_comprometido,p.monto_disponible,
       COALESCE(d.asignado,0) AS total_segun_partidas,
       COALESCE(d.ejercido,0) AS ejercido_segun_partidas,
       COALESCE(d.comprometido,0) AS comprometido_segun_partidas,
       CASE WHEN COALESCE(d.partidas,0)=0 OR p.monto_total<>COALESCE(d.asignado,0)
         OR p.monto_ejercido<>COALESCE(d.ejercido,0) OR p.monto_comprometido<>COALESCE(d.comprometido,0)
         THEN 1 ELSE 0 END AS requiere_revision
FROM presupuestos p
LEFT JOIN (SELECT presupuesto_id,COUNT(*) AS partidas,SUM(monto_asignado) AS asignado,
           SUM(monto_ejercido) AS ejercido,SUM(monto_comprometido) AS comprometido
           FROM partidas_presupuestales GROUP BY presupuesto_id) d ON d.presupuesto_id=p.id;
