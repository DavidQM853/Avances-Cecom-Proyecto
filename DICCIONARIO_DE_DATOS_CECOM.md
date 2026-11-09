# 📘 DICCIONARIO DE DATOS OFICIAL — SISTEMA DE PRESUPUESTOS CECOM
## Base de Datos: `cecom_presupuestos_er`
### Archivo Fuente: `CECOM_Base_de_Datos.sql` (MySQL 8.0.16+ / MariaDB 10.6+)

---

**Información General del Proyecto:**
- **Sistema:** Dashboard de Presupuestos CECOM
- **Institución / Empresa:** Seanalytics Solutions / Universidad Politécnica de Durango (UNIPOLI)
- **Desarrollador / Alumno:** David Quinteros Macias (Matrícula: 2303150087)
- **Motor de Base de Datos:** MySQL 8.0.16+ / MariaDB 10.6+ (Motor: `InnoDB`)
- **Juego de Caracteres y Cotejamiento:** `utf8mb4` / `utf8mb4_unicode_ci`
- **Estrategia de Integridad Referencial:** `ON DELETE RESTRICT` en todas las claves foráneas (prevención activa de borrados accidentales de trazabilidad financiera).

---

## 1. Arquitectura General y Catálogo de Entidades

La base de datos `cecom_presupuestos_er` está normalizada en **Tercera Forma Normal (3FN)** con extensiones analíticas y de auditoría inmutable, agrupada en 6 módulos funcionales:

```
                    ┌──────────────────────────────────────────────┐
                    │            DEPARTAMENTOS (Jerárquico)        │
                    └──────┬───────────────┬──────────────┬────────┘
                           │ 1:N           │ 1:N          │ 1:N
                           ▼               ▼              ▼
                     PRESUPUESTOS       USERS      PROYECCIONES_ANUALES
                           │ 1:N           │              ▲
                           ▼               ├──────────────┼──────────┐
                PARTIDAS_PRESUPUESTALES    │              │          │
                     │             │       ▼              │          ▼
                     │ 1:N         │  ROLES/PERMISOS      │   BITACORA_EVENTOS
                     ▼             │  (Seguridad Dinámica)│
            COMPROMISOS_RESERVA    │                      │
                     │ 1:N         │                      │
                     ▼             │                      ▼
             GASTOS_OPERATIVOS ◄───┘             MODELOS_PREDICTIVOS
                     │
                     ▼ (Pre-cálculo)
            AGREGACIONES_TEMPORALES
```

### Índice de Tablas y Vistas

| # | Objeto de Base de Datos | Tipo | Módulo | Propósito Principal |
| :-: | :--- | :---: | :--- | :--- |
| **1** | `departamentos` | Tabla | Estructura Organizacional | Áreas, direcciones y gerencias jerárquicas de CECOM. |
| **2** | `periodos_fiscales` | Tabla | Dimensión Temporal | Años, trimestres y meses contables con clave generada. |
| **3** | `categorias_opex` | Tabla | Catálogo Financiero | Cuentas de gasto operativo categorizadas con tipos enum. |
| **4** | `users` | Tabla | Seguridad y Acceso | Cuentas de usuarios y vinculación con departamentos. |
| **5** | `roles` | Tabla | Seguridad y Acceso | Perfiles de acceso estáticos y dinámicos basados en reglas. |
| **6** | `permisos` | Tabla | Seguridad y Acceso | Acciones atómicas del sistema clasificadas por módulo. |
| **7** | `rol_permiso` | Tabla Pivot | Seguridad y Acceso | Asignación M:N de permisos por rol. |
| **8** | `user_rol` | Tabla Pivot | Seguridad y Acceso | Asignación de roles con vigencia, alcance global o departamental. |
| **9** | `user_rol_categoria_restriccion` | Tabla Pivot | Seguridad y Acceso | Restricciones explícitas por cuenta OPEX para un rol asignado. |
| **10** | `historial_asignaciones` | Tabla | Auditoría | Registro inmutable de asignación y revocación de roles. |
| **11** | `presupuestos` | Tabla | Gestión Presupuestal | Asignación anual por departamento y estado de aprobación. |
| **12** | `partidas_presupuestales` | Tabla | Gestión Presupuestal | Techo financiero por cuenta OPEX y saldo virtual disponible. |
| **13** | `compromisos_reserva` | Tabla | Control de Compras | Apartados de saldo antes de la factura (preventivas/adjudicaciones). |
| **14** | `gastos_operativos` | Tabla | Transaccional | Hechos económicos devengados con folio fiscal y metadata JSON. |
| **15** | `bitacora_eventos` | Tabla | Auditoría Global | Log centralizado de mutaciones en cualquier entidad del sistema. |
| **16** | `modelos_predictivos` | Tabla | Inteligencia de Negocio | Registro de modelos de ML, versiones y métricas de validación. |
| **17** | `proyecciones_anuales` | Tabla | Inteligencia de Negocio | Estimación de gastos proyectados para el siguiente ejercicio. |
| **18** | `agregaciones_temporales` | Tabla | Analítica OLAP | Series de tiempo precalculadas en 6 escalas (diario a anual). |
| **19** | `v_reservas_conciliacion` | Vista SQL | Conciliación y Control | Cuadre contable entre reservas y gastos devengados. |
| **20** | `v_partidas_conciliacion` | Vista SQL | Conciliación y Control | Verificación de integridad entre montos de partida y transacciones. |
| **21** | `v_presupuestos_conciliacion`| Vista SQL | Conciliación y Control | Validación de sumatorias entre partidas y el presupuesto total. |

---

## 2. Especificación Detallada de Tablas

---

### 2.1 Tabla: `departamentos`
**Descripción:** Almacena el organigrama de la entidad con soporte de autorreferencia jerárquica (dirección general $\rightarrow$ gerencias $\rightarrow$ coordinaciones).

| Columna | Tipo de Dato | Nulo | Default | Restricciones / Claves | Descripción |
| :--- | :--- | :---: | :---: | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | NO | Auto | `PRIMARY KEY` | Identificador único del departamento. |
| `nombre` | `VARCHAR(255)` | NO | - | - | Nombre descriptivo del departamento. |
| `codigo` | `VARCHAR(20)` | NO | - | `UNIQUE (codigo)` | Código nemónico institucional único (e.g. `DG`, `TI`, `FIN`). |
| `descripcion` | `TEXT` | SÍ | NULL | - | Misión, funciones o facultades del área. |
| `departamento_padre_id`| `BIGINT UNSIGNED`| SÍ | NULL | `FK (id) ON DELETE RESTRICT` | Referencia al departamento jerárquico superior. |
| `activo` | `BOOLEAN` | NO | `TRUE` | `CHECK (activo IN (0,1))` | Estatus de operación (1=Activo, 0=Inactivo). |
| `created_at` | `TIMESTAMP` | SÍ | `CURRENT_TIMESTAMP` | - | Fecha y hora de creación del registro. |
| `updated_at` | `TIMESTAMP` | SÍ | `CURRENT_TIMESTAMP ON UPDATE` | - | Fecha y hora de la última modificación. |

- **Índices:**
  - `PRIMARY KEY (id)`
  - `UNIQUE KEY (codigo)`
  - `INDEX idx_depto_activo (activo)`
- **Claves Foráneas:**
  - `CONSTRAINT fk_depto_padre FOREIGN KEY (departamento_padre_id) REFERENCES departamentos(id) ON DELETE RESTRICT`

---

### 2.2 Tabla: `periodos_fiscales`
**Descripción:** Modela los ejercicios presupuestales y sus subdivisiones trimestrales y mensuales con clave normalizada generada por el motor.

| Columna | Tipo de Dato | Nulo | Default | Restricciones / Claves | Descripción |
| :--- | :--- | :---: | :---: | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | NO | Auto | `PRIMARY KEY` | Identificador único del periodo fiscal. |
| `anio` | `SMALLINT UNSIGNED` | NO | - | `CHECK (anio BETWEEN 1900 AND 9999)` | Año calendario del ejercicio presupuestal. |
| `trimestre` | `TINYINT UNSIGNED` | SÍ | NULL | `CHECK (trimestre IS NULL OR BETWEEN 1 AND 4)` | Número de trimestre (1 a 4) o NULL para periodo anual. |
| `mes` | `TINYINT UNSIGNED` | SÍ | NULL | `CHECK (mes IS NULL OR BETWEEN 1 AND 12)` | Número de mes (1 a 12) o NULL si no es escala mensual. |
| `clave_periodo`| `VARCHAR(12)` | NO | VIRTUAL | `GENERATED ALWAYS AS ... STORED`, `UNIQUE` | Código unívoco del periodo: `YYYY-A`, `YYYY-T1`, `YYYY-M01`. |
| `fecha_inicio` | `DATE` | NO | - | `CHECK (fecha_inicio <= fecha_fin)` | Fecha formal de apertura del periodo. |
| `fecha_fin` | `DATE` | NO | - | `CHECK (YEAR(fecha_inicio)=anio AND YEAR(fecha_fin)=anio)` | Fecha formal de cierre del periodo. |
| `cerrado` | `BOOLEAN` | NO | `FALSE` | `CHECK (cerrado IN (0,1))` | Indicador de ejercicio dictaminado/bloqueado. |
| `created_at` | `TIMESTAMP` | SÍ | `CURRENT_TIMESTAMP` | - | Creación. |
| `updated_at` | `TIMESTAMP` | SÍ | `CURRENT_TIMESTAMP ON UPDATE` | - | Modificación. |

- **Regla de Coherencia Contable:**
  - `CONSTRAINT ck_periodo_coherencia CHECK (mes IS NULL OR trimestre IS NULL OR trimestre = CEILING(mes/3))`
- **Índices:**
  - `PRIMARY KEY (id)`
  - `UNIQUE KEY uq_periodo_clave (clave_periodo)`
  - `INDEX idx_periodo_fechas (fecha_inicio, fecha_fin)`

---

### 2.3 Tabla: `categorias_opex`
**Descripción:** Catálogo de cuentas y conceptos de gastos operativos (OPEX) clasificados por naturaleza técnica y financiera.

| Columna | Tipo de Dato | Nulo | Default | Restricciones / Claves | Descripción |
| :--- | :--- | :---: | :---: | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | NO | Auto | `PRIMARY KEY` | Identificador único de la categoría. |
| `nombre` | `VARCHAR(255)` | NO | - | - | Nombre contable (e.g., Servicios de Nube, Licenciamiento). |
| `codigo` | `VARCHAR(20)` | NO | - | `UNIQUE (codigo)` | Código contable único (e.g. `OPEX-TI-01`). |
| `descripcion` | `TEXT` | SÍ | NULL | - | Justificación de gastos imputables a esta cuenta. |
| `tipo_gasto` | `VARCHAR(30)` | SÍ | NULL | `CHECK (tipo_gasto IN ('nomina','servicios','materiales','mantenimiento','tecnologia','capacitacion','viaticos','arrendamiento','seguros','otros'))` | Clasificador económico estandarizado. |
| `categoria_padre_id`| `BIGINT UNSIGNED`| SÍ | NULL | `FK (id) ON DELETE RESTRICT` | Cuenta mayor a la que pertenece jerárquicamente. |
| `activo` | `BOOLEAN` | NO | `TRUE` | `CHECK (activo IN (0,1))` | Disponibilidad para formulación presupuestal. |
| `created_at` | `TIMESTAMP` | SÍ | `CURRENT_TIMESTAMP` | - | Creación. |
| `updated_at` | `TIMESTAMP` | SÍ | `CURRENT_TIMESTAMP ON UPDATE` | - | Modificación. |

- **Índices:**
  - `PRIMARY KEY (id)`
  - `UNIQUE KEY (codigo)`
  - `INDEX idx_categoria_tipo (tipo_gasto)`

---

### 2.4 Tabla: `users`
**Descripción:** Usuarios del sistema con credenciales cifradas y asociación a un departamento institucional.

| Columna | Tipo de Dato | Nulo | Default | Restricciones / Claves | Descripción |
| :--- | :--- | :---: | :---: | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | NO | Auto | `PRIMARY KEY` | Identificador único de usuario. |
| `name` | `VARCHAR(255)` | NO | - | - | Nombre completo del funcionario. |
| `email` | `VARCHAR(255)` | NO | - | `UNIQUE (email)` | Correo institucional único. |
| `email_verified_at`| `TIMESTAMP` | SÍ | NULL | - | Verificación de cuenta. |
| `password` | `VARCHAR(255)` | NO | - | `COMMENT 'Hash generado por Laravel'` | Contraseña bajo hash Bcrypt / Argon2ID. |
| `remember_token` | `VARCHAR(100)` | SÍ | NULL | - | Token de sesión persistente. |
| `departamento_id` | `BIGINT UNSIGNED` | SÍ | NULL | `FK departamentos(id) ON DELETE RESTRICT` | Departamento de adscripción. |
| `activo` | `BOOLEAN` | NO | `TRUE` | `CHECK (activo IN (0,1))` | Estado de la cuenta (1=Habilitada, 0=Suspendida). |
| `created_at` | `TIMESTAMP` | SÍ | `CURRENT_TIMESTAMP` | - | Creación. |
| `updated_at` | `TIMESTAMP` | SÍ | `CURRENT_TIMESTAMP ON UPDATE` | - | Modificación. |

---

### 2.5 Tabla: `roles`
**Descripción:** Perfiles de seguridad con soporte de evaluación dinámica mediante reglas JSON.

| Columna | Tipo de Dato | Nulo | Default | Restricciones / Claves | Descripción |
| :--- | :--- | :---: | :---: | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | NO | Auto | `PRIMARY KEY` | Identificador único de rol. |
| `nombre` | `VARCHAR(255)` | NO | - | - | Nombre humano del rol (e.g. Auditor OPEX, Aprobador). |
| `slug` | `VARCHAR(100)` | NO | - | `UNIQUE (slug)` | Identificador nemónico del rol en el código. |
| `descripcion` | `TEXT` | SÍ | NULL | - | Propósito del perfil de acceso. |
| `es_dinamico` | `BOOLEAN` | NO | `FALSE` | `CHECK (es_dinamico IN (0,1))` | Si es asignado automáticamente por motor de reglas. |
| `reglas_asignacion`| `JSON` | SÍ | NULL | - | Criterios lógicos de evaluación histórica en formato JSON. |
| `created_at` | `TIMESTAMP` | SÍ | `CURRENT_TIMESTAMP` | - | Creación. |
| `updated_at` | `TIMESTAMP` | SÍ | `CURRENT_TIMESTAMP ON UPDATE` | - | Modificación. |

---

### 2.6 Tabla: `permisos`
**Descripción:** Catálogo de capacidades granulares del backend.

| Columna | Tipo de Dato | Nulo | Default | Restricciones / Claves | Descripción |
| :--- | :--- | :---: | :---: | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | NO | Auto | `PRIMARY KEY` | Identificador único del permiso. |
| `nombre` | `VARCHAR(255)` | NO | - | - | Nombre legible del privilegio. |
| `slug` | `VARCHAR(100)` | NO | - | `UNIQUE (slug)` | Identificador del permiso (e.g. `presupuestos.aprobar`). |
| `modulo` | `VARCHAR(50)` | NO | - | `INDEX idx_permiso_modulo` | Subsistema al que aplica (`presupuestos`, `gastos`, etc.). |
| `descripcion` | `TEXT` | SÍ | NULL | - | Detalle de las acciones amparadas. |
| `created_at` | `TIMESTAMP` | SÍ | `CURRENT_TIMESTAMP` | - | Creación. |
| `updated_at` | `TIMESTAMP` | SÍ | `CURRENT_TIMESTAMP ON UPDATE` | - | Modificación. |

---

### 2.7 Tabla: `rol_permiso`
**Descripción:** Tabla de relación M:N entre roles y permisos.

| Columna | Tipo de Dato | Nulo | Default | Restricciones / Claves | Descripción |
| :--- | :--- | :---: | :---: | :--- | :--- |
| `rol_id` | `BIGINT UNSIGNED` | NO | - | `PK, FK roles(id) ON DELETE RESTRICT` | Rol titular del permiso. |
| `permiso_id` | `BIGINT UNSIGNED` | NO | - | `PK, FK permisos(id) ON DELETE RESTRICT` | Permiso concedido. |

- **Clave Primaria Compuesta:** `PRIMARY KEY (rol_id, permiso_id)`.

---

### 2.8 Tabla: `user_rol`
**Descripción:** Asignación de roles a usuarios, con control de vigencias y delimitación de alcance institucional o departamental.

| Columna | Tipo de Dato | Nulo | Default | Restricciones / Claves | Descripción |
| :--- | :--- | :---: | :---: | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | NO | Auto | `PRIMARY KEY` | Identificador de asignación. |
| `user_id` | `BIGINT UNSIGNED` | NO | - | `FK users(id) ON DELETE RESTRICT` | Usuario que recibe el rol. |
| `rol_id` | `BIGINT UNSIGNED` | NO | - | `FK roles(id) ON DELETE RESTRICT` | Rol otorgado. |
| `departamento_id` | `BIGINT UNSIGNED` | SÍ | NULL | `FK departamentos(id) ON DELETE RESTRICT` | Departamento al que queda acotado el rol. |
| `alcance_global` | `BOOLEAN` | NO | `FALSE` | `CHECK ((alcance_global=1 AND departamento_id IS NULL) OR (alcance_global=0 AND departamento_id IS NOT NULL))` | Define si el permiso trasciende a toda la entidad. |
| `vigencia_inicio`| `DATE` | SÍ | NULL | - | Fecha a partir de la cual el rol entra en vigor. |
| `vigencia_fin` | `DATE` | SÍ | NULL | `CHECK (vigencia_fin >= vigencia_inicio)` | Fecha de caducidad de la asignación. |
| `activo` | `BOOLEAN` | NO | `TRUE` | `CHECK (activo IN (0,1))` | Estatus de la asignación. |
| `created_at` | `TIMESTAMP` | SÍ | `CURRENT_TIMESTAMP` | - | Creación. |
| `updated_at` | `TIMESTAMP` | SÍ | `CURRENT_TIMESTAMP ON UPDATE` | - | Modificación. |

- **Índices:**
  - `INDEX idx_ur_vigencia (user_id, activo, vigencia_inicio, vigencia_fin)`
  - `INDEX idx_ur_depto_rol (departamento_id, rol_id)`

---

### 2.9 Tabla: `user_rol_categoria_restriccion`
**Descripción:** Restricciones de seguridad por objeto de gasto aplicadas a una asignación de rol (`user_rol`).

| Columna | Tipo de Dato | Nulo | Default | Restricciones / Claves | Descripción |
| :--- | :--- | :---: | :---: | :--- | :--- |
| `user_rol_id` | `BIGINT UNSIGNED` | NO | - | `PK, FK user_rol(id) ON DELETE RESTRICT` | Asignación de rol afectada. |
| `categoria_opex_id`| `BIGINT UNSIGNED`| NO | - | `PK, FK categorias_opex(id) ON DELETE RESTRICT`| Categoría OPEX restringida/bloqueada para este rol. |

- **Clave Primaria Compuesta:** `PRIMARY KEY (user_rol_id, categoria_opex_id)`.

---

### 2.10 Tabla: `historial_asignaciones`
**Descripción:** Registro de auditoría inmutable sobre transiciones de roles dinámicos o manuales.

| Columna | Tipo de Dato | Nulo | Default | Restricciones / Claves | Descripción |
| :--- | :--- | :---: | :---: | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | NO | Auto | `PRIMARY KEY` | Identificador único de evento. |
| `user_id` | `BIGINT UNSIGNED` | NO | - | `FK users(id) ON DELETE RESTRICT` | Usuario sobre quien recae la acción. |
| `rol_id` | `BIGINT UNSIGNED` | NO | - | `FK roles(id) ON DELETE RESTRICT` | Rol involucrado. |
| `accion` | `VARCHAR(20)` | NO | - | `CHECK (accion IN ('asignar','revocar','modificar'))` | Tipo de operación ejecutada. |
| `datos_anteriores`| `JSON` | SÍ | NULL | - | Snapshot del estado previo. |
| `datos_nuevos` | `JSON` | SÍ | NULL | - | Snapshot del nuevo estado. |
| `motivo` | `VARCHAR(500)`| NO | - | - | Justificación de la decisión o regla activada. |
| `ejecutado_por` | `BIGINT UNSIGNED` | SÍ | NULL | `FK users(id) ON DELETE RESTRICT` | Usuario operador o NULL si fue el motor automático. |
| `created_at` | `TIMESTAMP` | SÍ | `CURRENT_TIMESTAMP` | - | Marca temporal exacta de la acción. |
| `updated_at` | `TIMESTAMP` | SÍ | `CURRENT_TIMESTAMP ON UPDATE` | - | - |

- **Índices:**
  - `INDEX idx_hist_user_fecha (user_id, created_at)`

---

### 2.11 Tabla: `presupuestos`
**Descripción:** Encabezado de la asignación presupuestal anual por departamento, con columna virtual persistida de saldo disponible.

| Columna | Tipo de Dato | Nulo | Default | Restricciones / Claves | Descripción |
| :--- | :--- | :---: | :---: | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | NO | Auto | `PRIMARY KEY` | Identificador del presupuesto. |
| `departamento_id`| `BIGINT UNSIGNED` | NO | - | `FK departamentos(id) ON DELETE RESTRICT` | Departamento asignatario. |
| `periodo_fiscal_id`|`BIGINT UNSIGNED`| NO | - | `FK periodos_fiscales(id) ON DELETE RESTRICT` | Ejercicio fiscal de vigencia. |
| `monto_total` | `DECIMAL(15,2)` | NO | `0.00` | `CHECK (monto_total >= 0)` | Techo presupuestal global autorizado. |
| `monto_ejercido` | `DECIMAL(15,2)` | NO | `0.00` | `CHECK (monto_ejercido >= 0)` | Suma de gastos devengados y liquidados. |
| `monto_comprometido`|`DECIMAL(15,2)`| NO | `0.00` | `CHECK (monto_comprometido >= 0)` | Fondos reservados en trámite de compra. |
| `monto_disponible`| `DECIMAL(15,2)` | NO | VIRTUAL | `GENERATED ALWAYS AS (monto_total - monto_ejercido - monto_comprometido) STORED` | Saldo libre calculable e indexable. |
| `estado` | `VARCHAR(20)` | NO | `'borrador'`| `CHECK (estado IN ('borrador','en_revision','aprobado','rechazado','ejecutando','cerrado'))` | Fase en el ciclo de vida contable. |
| `creado_por` | `BIGINT UNSIGNED` | SÍ | NULL | `FK users(id) ON DELETE RESTRICT` | Usuario que capturó el anteproyecto. |
| `aprobado_por` | `BIGINT UNSIGNED` | SÍ | NULL | `FK users(id) ON DELETE RESTRICT` | Funcionario facultado que autorizó. |
| `aprobado_en` | `TIMESTAMP` | SÍ | NULL | - | Momento de la aprobación. |
| `notas` | `TEXT` | SÍ | NULL | - | Justificaciones o decretos presupuestales. |
| `created_at` | `TIMESTAMP` | SÍ | `CURRENT_TIMESTAMP` | - | Creación. |
| `updated_at` | `TIMESTAMP` | SÍ | `CURRENT_TIMESTAMP ON UPDATE` | - | Modificación. |

- **Restricción Contable de No Sobregiro:**
  - `CONSTRAINT ck_pres_montos CHECK (monto_total>=0 AND monto_ejercido>=0 AND monto_comprometido>=0 AND monto_ejercido+monto_comprometido<=monto_total)`
- **Índices:**
  - `UNIQUE KEY uq_presupuesto_depto_periodo (departamento_id, periodo_fiscal_id)`
  - `INDEX idx_pres_depto_periodo_estado (departamento_id, periodo_fiscal_id, estado)`

---

### 2.12 Tabla: `partidas_presupuestales`
**Descripción:** Desglose del presupuesto asignado a cada cuenta de gasto operativo específica.

| Columna | Tipo de Dato | Nulo | Default | Restricciones / Claves | Descripción |
| :--- | :--- | :---: | :---: | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | NO | Auto | `PRIMARY KEY` | Identificador de la partida. |
| `presupuesto_id` | `BIGINT UNSIGNED` | NO | - | `FK presupuestos(id) ON DELETE RESTRICT` | Presupuesto matriz. |
| `categoria_opex_id`| `BIGINT UNSIGNED`| NO | - | `FK categorias_opex(id) ON DELETE RESTRICT` | Cuenta contable OPEX asignada. |
| `monto_asignado` | `DECIMAL(15,2)` | NO | - | `CHECK (monto_asignado >= 0.01)` | Techo autorizado para este concepto. |
| `monto_ejercido` | `DECIMAL(15,2)` | NO | `0.00` | `CHECK (monto_ejercido >= 0)` | Gasto devengado en esta partida. |
| `monto_comprometido`|`DECIMAL(15,2)`| NO | `0.00` | `CHECK (monto_comprometido >= 0)` | Reservas activas en esta partida. |
| `monto_disponible`| `DECIMAL(15,2)` | NO | VIRTUAL | `GENERATED ALWAYS AS (monto_asignado - monto_ejercido - monto_comprometido) STORED` | Saldo disponible en esta partida. |
| `created_at` | `TIMESTAMP` | SÍ | `CURRENT_TIMESTAMP` | - | Creación. |
| `updated_at` | `TIMESTAMP` | SÍ | `CURRENT_TIMESTAMP ON UPDATE` | - | Modificación. |

- **Restricción de No Sobregiro:**
  - `CONSTRAINT ck_partida_montos CHECK (monto_asignado>=0.01 AND monto_ejercido>=0 AND monto_comprometido>=0 AND monto_ejercido+monto_comprometido<=monto_asignado)`
- **Índices:**
  - `UNIQUE KEY uq_partida_pres_categoria (presupuesto_id, categoria_opex_id)`

---

### 2.13 Tabla: `compromisos_reserva`
**Descripción:** Registra las reservas de saldo preventivas sobre partidas presupuestales para licitaciones, órdenes de compra o contratos en trámite.

| Columna | Tipo de Dato | Nulo | Default | Restricciones / Claves | Descripción |
| :--- | :--- | :---: | :---: | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | NO | Auto | `PRIMARY KEY` | Identificador único de reserva. |
| `partida_id` | `BIGINT UNSIGNED` | NO | - | `FK partidas_presupuestales(id) ON DELETE RESTRICT`| Partida presupuestal sobre la que se aparta saldo. |
| `creado_por` | `BIGINT UNSIGNED` | NO | - | `FK users(id) ON DELETE RESTRICT` | Usuario solicitante de la reserva. |
| `referencia_documento`|`VARCHAR(255)`| NO | - | - | Requisición, orden de compra o folio de adjudicación. |
| `monto_original` | `DECIMAL(15,2)` | NO | - | `CHECK (monto_original >= 0.01)` | Saldo total inicialmente apartado. |
| `monto_liberado` | `DECIMAL(15,2)` | NO | `0.00` | `CHECK (monto_liberado >= 0 AND <= monto_original)`| Saldo no consumido reintegrado a la partida. |
| `estado` | `VARCHAR(15)` | NO | `'activa'`| `CHECK (estado IN ('activa','cerrada','cancelada'))` | Estado de la reserva. |
| `created_at` | `TIMESTAMP` | SÍ | `CURRENT_TIMESTAMP` | - | Creación. |
| `updated_at` | `TIMESTAMP` | SÍ | `CURRENT_TIMESTAMP ON UPDATE` | - | Modificación. |

- **Índices:**
  - `PRIMARY KEY (id)`
  - `UNIQUE KEY uq_reserva_id_partida (id, partida_id)`
  - `INDEX idx_reserva_partida_estado (partida_id, estado)`

---

### 2.14 Tabla: `gastos_operativos`
**Descripción:** Registro transaccional de hechos económicos devengados con vinculación obligatoria a una partida y opcional a una reserva previa.

| Columna | Tipo de Dato | Nulo | Default | Restricciones / Claves | Descripción |
| :--- | :--- | :---: | :---: | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | NO | Auto | `PRIMARY KEY` | Identificador único del gasto. |
| `partida_id` | `BIGINT UNSIGNED` | NO | - | `FK partidas_presupuestales(id) ON DELETE RESTRICT`| Partida afectada. |
| `registrado_por` | `BIGINT UNSIGNED` | SÍ | NULL | `FK users(id) ON DELETE RESTRICT` | Usuario que capturó la transacción. |
| `compromiso_id` | `BIGINT UNSIGNED` | SÍ | NULL | `FK (compromiso_id, partida_id) REFERENCES compromisos_reserva(id, partida_id) ON DELETE RESTRICT` | Reserva previa vinculada (validación estricta de partida). |
| `monto` | `DECIMAL(15,2)` | NO | - | `CHECK (monto >= 0.01)` | Importe total ejercido de la transacción. |
| `concepto` | `VARCHAR(255)` | NO | - | - | Objeto del gasto (factura, servicio, insumo). |
| `referencia_documento`|`VARCHAR(255)`| SÍ | NULL | `INDEX idx_gasto_referencia` | UUID del CFDI / Folio de Factura. |
| `fecha_gasto` | `DATE` | NO | - | `INDEX idx_gasto_fecha_partida` | Fecha contable real de emisión o pago. |
| `fecha_registro` | `DATE` | NO | - | `INDEX idx_gasto_registro` | Fecha en la que se introdujo al sistema. |
| `metadata` | `JSON` | SÍ | NULL | - | Metadatos flexibles (proveedor, RFC emisor, UUID, retenciones). |
| `created_at` | `TIMESTAMP` | SÍ | `CURRENT_TIMESTAMP` | - | Creación. |
| `updated_at` | `TIMESTAMP` | SÍ | `CURRENT_TIMESTAMP ON UPDATE` | - | Modificación. |

- **Índices Temporales de Alto Rendimiento:**
  - `INDEX idx_gasto_fecha_partida (fecha_gasto, partida_id)`
  - `INDEX idx_gasto_registro (fecha_registro, partida_id)`
  - `INDEX idx_gasto_referencia (referencia_documento)`

---

### 2.15 Tabla: `bitacora_eventos`
**Descripción:** Bitácora inmutable de auditoría forense para registrar cambios de estado y mutaciones en cualquier entidad del sistema.

| Columna | Tipo de Dato | Nulo | Default | Restricciones / Claves | Descripción |
| :--- | :--- | :---: | :---: | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | NO | Auto | `PRIMARY KEY` | Identificador de log. |
| `actor_id` | `BIGINT UNSIGNED` | SÍ | NULL | `FK users(id) ON DELETE RESTRICT` | Usuario responsable del evento. |
| `entidad_tipo` | `VARCHAR(40)` | NO | - | `CHECK (entidad_tipo IN ('departamentos','periodos_fiscales','categorias_opex','users','roles','permisos','rol_permiso','user_rol','user_rol_categoria_restriccion','presupuestos','partidas_presupuestales','compromisos_reserva','gastos_operativos','modelos_predictivos','proyecciones_anuales'))` | Nombre de la tabla afectada. |
| `entidad_id` | `BIGINT UNSIGNED` | NO | - | `COMMENT 'Referencia lógica validada por servicio'` | ID del registro afectado. |
| `accion` | `VARCHAR(40)` | NO | - | - | Acción (`crear`, `actualizar`, `aprobar`, etc.). |
| `motivo` | `VARCHAR(500)`| NO | - | - | Causa o justificación administrativa. |
| `valores_antes` | `JSON` | SÍ | NULL | - | Datos serializados antes del cambio. |
| `valores_despues`| `JSON` | SÍ | NULL | - | Datos serializados tras el cambio. |
| `ocurrido_en` | `TIMESTAMP` | NO | `CURRENT_TIMESTAMP` | - | Marca de tiempo exacta del evento. |

- **Índices:**
  - `INDEX idx_evento_objetivo (entidad_tipo, entidad_id, ocurrido_en)`
  - `INDEX idx_evento_actor_fecha (actor_id, ocurrido_en)`

---

### 2.16 Tabla: `modelos_predictivos`
**Descripción:** Registro de versiones y metadatos de modelos de Machine Learning utilizados para estimaciones presupuestales automáticas.

| Columna | Tipo de Dato | Nulo | Default | Restricciones / Claves | Descripción |
| :--- | :--- | :---: | :---: | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | NO | Auto | `PRIMARY KEY` | Identificador del modelo. |
| `version` | `VARCHAR(100)`| NO | - | `UNIQUE (version)` | Identificador de versión (e.g. `v1.2.0-sarimax`). |
| `version_datos` | `VARCHAR(255)`| NO | - | - | Hash del dataset utilizado en entrenamiento. |
| `entrenamiento_desde`|`DATE`| NO | - | - | Inicio de ventana de entrenamiento. |
| `entrenamiento_hasta`|`DATE`| NO | - | - | Término de ventana de entrenamiento. |
| `validacion_desde`| `DATE` | NO | - | - | Inicio de ventana de prueba out-of-time. |
| `validacion_hasta`| `DATE` | NO | - | - | Término de ventana de validación. |
| `metricas_validacion`|`JSON`| NO | - | - | Métricas de precisión (`MAE`, `RMSE`, `MAPE`, $R^2$). |
| `referencia_artefacto`|`VARCHAR(1024)`|NO| - | - | Ruta o URI del binario entrenado (joblib / onnx / h5). |
| `created_at` | `TIMESTAMP` | SÍ | `CURRENT_TIMESTAMP` | - | Creación. |
| `updated_at` | `TIMESTAMP` | SÍ | `CURRENT_TIMESTAMP ON UPDATE` | - | Modificación. |

- **Restricción de Ventanas Temporales:**
  - `CONSTRAINT ck_modelo_fechas CHECK (entrenamiento_desde<=entrenamiento_hasta AND entrenamiento_hasta<validacion_desde AND validacion_desde<=validacion_hasta)`

---

### 2.17 Tabla: `proyecciones_anuales`
**Descripción:** Estimaciones presupuestales generadas por modelos predictivos para departamentos en ejercicios futuros.

| Columna | Tipo de Dato | Nulo | Default | Restricciones / Claves | Descripción |
| :--- | :--- | :---: | :---: | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | NO | Auto | `PRIMARY KEY` | Identificador de proyección. |
| `modelo_id` | `BIGINT UNSIGNED` | NO | - | `FK modelos_predictivos(id) ON DELETE RESTRICT`| Modelo generador. |
| `departamento_id`| `BIGINT UNSIGNED` | NO | - | `FK departamentos(id) ON DELETE RESTRICT`| Departamento evaluado. |
| `anio_base` | `SMALLINT UNSIGNED`| NO | - | `CHECK (anio_base BETWEEN 1900 AND 9998)`| Año histórico de referencia. |
| `anio_objetivo` | `SMALLINT UNSIGNED`| NO | - | `CHECK (anio_objetivo = anio_base + 1)` | Año pronosticado. |
| `fecha_corte_datos`|`DATE`| NO | - | - | Fecha de snapshot de datos utilizados. |
| `monto_proyectado`|`DECIMAL(15,2)`| NO | - | `CHECK (monto_proyectado >= 0)` | Presupuesto anual estimado. |
| `generado_en` | `TIMESTAMP` | NO | `CURRENT_TIMESTAMP` | - | Momento de ejecución del pronóstico. |

- **Índices:**
  - `UNIQUE KEY uq_proyeccion_version_corte (modelo_id, departamento_id, anio_base, anio_objetivo, fecha_corte_datos)`
  - `INDEX idx_proyeccion_depto_anio (departamento_id, anio_objetivo)`

---

### 2.18 Tabla: `agregaciones_temporales`
**Descripción:** Estructura analítica optimizada con claves compuestas virtuales para acelerar consultas multi-escala temporal en el Dashboard.

| Columna | Tipo de Dato | Nulo | Default | Restricciones / Claves | Descripción |
| :--- | :--- | :---: | :---: | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | NO | Auto | `PRIMARY KEY` | Identificador de agregado. |
| `departamento_id`| `BIGINT UNSIGNED` | NO | - | `FK departamentos(id) ON DELETE RESTRICT`| Unidad departamental. |
| `categoria_opex_id`|`BIGINT UNSIGNED`| SÍ | NULL | `FK categorias_opex(id) ON DELETE RESTRICT` | Cuenta OPEX o NULL para nivel departamento. |
| `categoria_clave`| `BIGINT UNSIGNED` | NO | VIRTUAL | `GENERATED ALWAYS AS (COALESCE(categoria_opex_id,0)) STORED`| Clave normalizada para índice único con nulos. |
| `periodo_tipo` | `VARCHAR(20)` | NO | - | `CHECK (periodo_tipo IN ('diario','quincenal','mensual','trimestral','semestral','anual'))` | Escala temporal de consolidación. |
| `fecha_inicio` | `DATE` | NO | - | `CHECK (fecha_inicio <= fecha_fin)` | Fecha inicio del cubo temporal. |
| `fecha_fin` | `DATE` | NO | - | - | Fecha fin del cubo temporal. |
| `monto_total` | `DECIMAL(15,2)` | NO | `0.00` | `CHECK (monto_total >= 0)` | Suma acumulada de gastos en el periodo. |
| `monto_promedio` | `DECIMAL(15,2)` | NO | `0.00` | `CHECK (monto_promedio >= 0)` | Ticket promedio de las transacciones. |
| `variacion_porcentual`|`DECIMAL(12,4)`| SÍ | NULL | - | Delta de cambio porcentual vs. periodo anterior. |
| `cantidad_transacciones`|`INT UNSIGNED`| NO | `0` | - | Número total de gastos agrupados. |
| `desglose` | `JSON` | SÍ | NULL | - | Vector JSON con subtotales por proveedor / partida. |
| `created_at` | `TIMESTAMP` | SÍ | `CURRENT_TIMESTAMP` | - | Creación. |
| `updated_at` | `TIMESTAMP` | SÍ | `CURRENT_TIMESTAMP ON UPDATE` | - | Modificación. |

- **Índices de Cobertura Analítica:**
  - `UNIQUE KEY uq_agregacion_contexto (departamento_id, categoria_clave, periodo_tipo, fecha_inicio, fecha_fin)`
  - `INDEX idx_agg_tipo_inicio (periodo_tipo, fecha_inicio)`

---

## 3. Especificación de Vistas de Conciliación y Comprobación

---

### 3.1 Vista: `v_reservas_conciliacion`
- **Propósito:** Audita el balance de compromisos y reservas contra los gastos efectivamente devengados, alertando si el saldo pendiente es negativo o si una reserva no activa mantiene saldo pendiente.
- **Definición SQL:**
```sql
CREATE VIEW v_reservas_conciliacion AS
SELECT r.id AS compromiso_id, r.partida_id, r.estado, r.monto_original, r.monto_liberado,
       COALESCE(g.ejercido,0) AS monto_ejercido,
       r.monto_original - r.monto_liberado - COALESCE(g.ejercido,0) AS saldo_pendiente,
       CASE WHEN r.monto_original - r.monto_liberado - COALESCE(g.ejercido,0) < 0
          OR (r.estado <> 'activa' AND r.monto_original - r.monto_liberado - COALESCE(g.ejercido,0) <> 0)
          THEN 1 ELSE 0 END AS requiere_revision
FROM compromisos_reserva r
LEFT JOIN (SELECT compromiso_id, SUM(monto) AS ejercido FROM gastos_operativos
           WHERE compromiso_id IS NOT NULL GROUP BY compromiso_id) g ON g.compromiso_id = r.id;
```

---

### 3.2 Vista: `v_partidas_conciliacion`
- **Propósito:** Verifica que los campos acumuladores `monto_ejercido` y `monto_comprometido` de la tabla `partidas_presupuestales` coincidan exactamente con la suma en tiempo real de `gastos_operativos` y `compromisos_reserva`.
- **Definición SQL:**
```sql
CREATE VIEW v_partidas_conciliacion AS
SELECT p.id AS partida_id, p.presupuesto_id, p.monto_asignado, p.monto_ejercido, p.monto_comprometido,
       COALESCE(g.ejercido,0) AS ejercido_segun_gastos,
       COALESCE(r.pendiente,0) AS comprometido_segun_reservas,
       p.monto_asignado - COALESCE(g.ejercido,0) - COALESCE(r.pendiente,0) AS disponible_segun_movimientos,
       CASE WHEN p.monto_ejercido <> COALESCE(g.ejercido,0)
         OR p.monto_comprometido <> COALESCE(r.pendiente,0) THEN 1 ELSE 0 END AS requiere_revision
FROM partidas_presupuestales p
LEFT JOIN (SELECT partida_id, SUM(monto) AS ejercido FROM gastos_operativos GROUP BY partida_id) g ON g.partida_id = p.id
LEFT JOIN (SELECT partida_id, SUM(saldo_pendiente) AS pendiente FROM v_reservas_conciliacion GROUP BY partida_id) r ON r.partida_id = p.id;
```

---

### 3.3 Vista: `v_presupuestos_conciliacion`
- **Propósito:** Comprueba que la suma de los montos asignados, ejercidos y comprometidos de todas las partidas de un departamento sumen con exactitud el total declarado en la cabecera `presupuestos`.
- **Definición SQL:**
```sql
CREATE VIEW v_presupuestos_conciliacion AS
SELECT p.id AS presupuesto_id, p.monto_total, p.monto_ejercido, p.monto_comprometido, p.monto_disponible,
       COALESCE(d.asignado,0) AS total_segun_partidas,
       COALESCE(d.ejercido,0) AS ejercido_segun_partidas,
       COALESCE(d.comprometido,0) AS comprometido_segun_partidas,
       CASE WHEN COALESCE(d.partidas,0) = 0 OR p.monto_total <> COALESCE(d.asignado,0)
         OR p.monto_ejercido <> COALESCE(d.ejercido,0) OR p.monto_comprometido <> COALESCE(d.comprometido,0)
         THEN 1 ELSE 0 END AS requiere_revision
FROM presupuestos p
LEFT JOIN (SELECT presupuesto_id, COUNT(*) AS partidas, SUM(monto_asignado) AS asignado,
           SUM(monto_ejercido) AS ejercido, SUM(monto_comprometido) AS comprometido
           FROM partidas_presupuestales GROUP BY presupuesto_id) d ON d.presupuesto_id = p.id;
```

---

## 4. Matriz Global de Relaciones y Claves Foráneas

| Tabla Origen | Clave Foránea | Tabla Destino | Clave Primaria Destino | Acción ON DELETE | Cardinalidad | Justificación de Negocio |
| :--- | :--- | :--- | :--- | :---: | :---: | :--- |
| `departamentos` | `departamento_padre_id` | `departamentos` | `id` | `RESTRICT` | 0..1 : N | No se permite eliminar una dirección que contenga gerencias subordinadas. |
| `users` | `departamento_id` | `departamentos` | `id` | `RESTRICT` | 0..1 : N | Un departamento no puede eliminarse si cuenta con personal adscrito. |
| `categorias_opex` | `categoria_padre_id` | `categorias_opex` | `id` | `RESTRICT` | 0..1 : N | Protege la integridad de las cuentas contables dependientes. |
| `rol_permiso` | `rol_id` | `roles` | `id` | `RESTRICT` | 1 : N | Impide borrar roles sin desvincular sus privilegios. |
| `rol_permiso` | `permiso_id` | `permisos` | `id` | `RESTRICT` | 1 : N | Impide borrar permisos en uso. |
| `user_rol` | `user_id` | `users` | `id` | `RESTRICT` | 1 : N | Protección de historial de accesos por usuario. |
| `user_rol` | `rol_id` | `roles` | `id` | `RESTRICT` | 1 : N | Protección de asignaciones vigentes. |
| `user_rol` | `departamento_id` | `departamentos` | `id` | `RESTRICT` | 0..1 : N | Integridad de ámbito departamental. |
| `user_rol_categoria_restriccion` | `user_rol_id` | `user_rol` | `id` | `RESTRICT` | 1 : N | Integridad de la restricción del rol. |
| `user_rol_categoria_restriccion` | `categoria_opex_id`| `categorias_opex` | `id` | `RESTRICT` | 1 : N | Integridad de la cuenta OPEX restringida. |
| `historial_asignaciones` | `user_id` | `users` | `id` | `RESTRICT` | 1 : N | Trazabilidad forense inmutable de usuarios. |
| `historial_asignaciones` | `rol_id` | `roles` | `id` | `RESTRICT` | 1 : N | Trazabilidad forense inmutable de roles. |
| `historial_asignaciones` | `ejecutado_por` | `users` | `id` | `RESTRICT` | 0..1 : N | Registro de funcionario auditor. |
| `presupuestos` | `departamento_id` | `departamentos` | `id` | `RESTRICT` | 1 : N | Un departamento no puede eliminarse si tiene ejercicio presupuestal. |
| `presupuestos` | `periodo_fiscal_id` | `periodos_fiscales` | `id` | `RESTRICT` | 1 : N | Conserva la historia contable de ejercicios anteriores. |
| `presupuestos` | `creado_por` | `users` | `id` | `RESTRICT` | 0..1 : N | Auditoría de autoría de presupuestos. |
| `presupuestos` | `aprobado_por` | `users` | `id` | `RESTRICT` | 0..1 : N | Auditoría de autorización directiva. |
| `partidas_presupuestales` | `presupuesto_id` | `presupuestos` | `id` | `RESTRICT` | 1 : N | Ninguna partida existe sin un presupuesto matriz aprobado. |
| `partidas_presupuestales` | `categoria_opex_id` | `categorias_opex` | `id` | `RESTRICT` | 1 : N | No se puede eliminar una cuenta OPEX con partidas asignadas. |
| `compromisos_reserva` | `partida_id` | `partidas_presupuestales`| `id` | `RESTRICT` | 1 : N | Todo compromiso debe afectar a una partida existente. |
| `compromisos_reserva` | `creado_por` | `users` | `id` | `RESTRICT` | 1 : N | Responsable de la solicitud de reserva. |
| `gastos_operativos` | `partida_id` | `partidas_presupuestales`| `id` | `RESTRICT` | 1 : N | Todo gasto devengado requiere partida presupuestal válida. |
| `gastos_operativos` | `registrado_por` | `users` | `id` | `RESTRICT` | 0..1 : N | Identificación del capturista del gasto. |
| `gastos_operativos` | `(compromiso_id, partida_id)` | `compromisos_reserva` | `(id, partida_id)` | `RESTRICT` | 0..1 : 1..N | **Integridad Compuesta:** Si se usa reserva, debe pertenecer a la misma partida. |
| `bitacora_eventos` | `actor_id` | `users` | `id` | `RESTRICT` | 0..1 : N | Responsable del evento de auditoría. |
| `proyecciones_anuales` | `modelo_id` | `modelos_predictivos`| `id` | `RESTRICT` | 1 : N | Trazabilidad del algoritmo predictivo que estimó el gasto. |
| `proyecciones_anuales` | `departamento_id` | `departamentos` | `id` | `RESTRICT` | 1 : N | Departamento al que aplica el pronóstico. |
| `agregaciones_temporales` | `departamento_id` | `departamentos` | `id` | `RESTRICT` | 1 : N | Departamento totalizado en la serie temporal. |
| `agregaciones_temporales` | `categoria_opex_id` | `categorias_opex` | `id` | `RESTRICT` | 0..1 : N | Cuenta OPEX totalizada o NULL (total departamental). |

---
*Fin del Diccionario de Datos Oficial de `cecom_presupuestos_er` (Generado a partir de `CECOM_Base_de_Datos.sql`).*
