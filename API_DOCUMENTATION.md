# 📡 Documentación Técnica de API REST v1 — Dashboard Presupuestos CECOM

**URL Base:** `http://localhost:8000/api/v1`  
**Autenticación:** Bearer Token (Sanctum)  
**Formato:** JSON (`Accept: application/json`, `Content-Type: application/json`)

---

## 1. Autenticación (`/auth`)

### 1.1 Iniciar Sesión
- **POST** `/auth/login`
- **Body:**
  ```json
  {
    "email": "admin@cecom.gob.mx",
    "password": "secret_password"
  }
  ```
- **Respuesta 200:**
  ```json
  {
    "message": "Autenticación exitosa",
    "token": "1|abc123456...",
    "token_type": "Bearer",
    "user": {
      "id": 1,
      "name": "Administrador CECOM",
      "email": "admin@cecom.gob.mx",
      "departamento": { "id": 1, "nombre": "Dirección General", "codigo": "DG" },
      "roles": [...],
      "permisos": ["presupuestos.ver", "gastos.crear", ...]
    }
  }
  ```

### 1.2 Cerrar Sesión
- **POST** `/auth/logout` (Requiere Bearer Token)

### 1.3 Perfil y Permisos
- **GET** `/auth/me` (Requiere Bearer Token)

---

## 2. Departamentos (`/departamentos`)

- **GET** `/departamentos` — Lista de departamentos con estructura jerárquica
- **GET** `/departamentos/{id}` — Detalle de departamento con presupuestos recientes
- **POST** `/departamentos` — Crear departamento
- **PUT** `/departamentos/{id}` — Actualizar departamento
- **GET** `/departamentos/{id}/presupuestos?anio=2026` — Presupuestos del departamento
- **GET** `/departamentos/{id}/gastos?desde=2026-01-01&hasta=2026-08-31` — Gastos del departamento

---

## 3. Presupuestos (`/presupuestos`)

- **GET** `/presupuestos?anio=2026&departamento_id=2&estado=ejecutando` — Listado filtrable
- **GET** `/presupuestos/{id}` — Detalle completo con partidas y semáforo
- **POST** `/presupuestos` — Crear presupuesto con partidas OPEX:
  ```json
  {
    "departamento_id": 2,
    "periodo_fiscal_id": 3,
    "notas": "Presupuesto ejercicio 2026",
    "partidas": [
      { "categoria_opex_id": 1, "monto_asignado": 1200000.00 },
      { "categoria_opex_id": 5, "monto_asignado": 800000.00 }
    ]
  }
  ```
- **PATCH** `/presupuestos/{id}/estado` — Cambiar estado:
  ```json
  {
    "estado": "aprobado",
    "motivo": "Aprobado en junta directiva"
  }
  ```

### 3.1 Comparativa YoY (Year-over-Year) — Objetivo 1
- **GET** `/presupuestos-yoy/comparativa?anio_base=2024&anio_comparar=2025`
- **Respuesta 200:**
  ```json
  {
    "metadatos": {
      "anio_base": 2024,
      "anio_comparar": 2025,
      "gran_total_base": 21000000.00,
      "gran_total_comparar": 22680000.00,
      "delta_total": 1680000.00,
      "variacion_total_porcentual": 8.0,
      "tendencia_global": "incremento"
    },
    "departamentos": [
      {
        "departamento": { "id": 2, "nombre": "Administración y Finanzas", "codigo": "AF" },
        "monto_base": 3500000.00,
        "monto_comparar": 3780000.00,
        "delta_absoluto": 280000.00,
        "variacion_porcentual": 8.0,
        "tendencia": "incremento",
        "desglose_opex": [...]
      }
    ]
  }
  ```

---

## 4. Gastos Operativos (`/gastos`)

- **GET** `/gastos?desde=2026-01-01&hasta=2026-08-31&departamento_id=2&categoria_opex_id=1` — Filtrado paginado
- **POST** `/gastos` — Registrar gasto (valida suficiencia presupuestal):
  ```json
  {
    "partida_id": 5,
    "monto": 25000.00,
    "concepto": "Renovación licencias Microsoft 365",
    "referencia_documento": "FAC-2026-9812",
    "fecha_gasto": "2026-08-15",
    "metadata": { "proveedor": "Microsoft México" }
  }
  ```
- **PUT** `/gastos/{id}` — Actualizar gasto y conciliar presupuestos
- **DELETE** `/gastos/{id}` — Eliminar gasto y reintegrar monto disponible

---

## 5. Agregaciones y Filtros Temporales (`/agregaciones`) — Objetivo 2

Permite filtrar series temporales en los 6 tipos de períodos requeridos:

- **GET** `/agregaciones?periodo={periodo}&desde={desde}&hasta={hasta}&departamento_id={id}`
  - `periodo`: `diario`, `quincenal`, `mensual`, `trimestral`, `semestral`, `anual`
- **GET** `/agregaciones/tendencia?meses=12` — Serie mensual de los últimos N meses
- **GET** `/agregaciones/variabilidad?anio=2025&departamento_id=2` — Variabilidad intermensual

### 5.1 Variabilidad Intermensual — Objetivo 6
- **GET** `/reportes/variabilidad-mensual?anio=2025`
- **Respuesta 200:**
  ```json
  {
    "analisis": {
      "anio": 2025,
      "gran_total": 14500000.00,
      "promedio_mensual": 1208333.33,
      "desviacion_estandar": 105432.21,
      "coeficiente_variacion": 8.72,
      "clasificacion_variabilidad": "Baja (Gasto predecible y homogéneo)",
      "serie_mensual": [...]
    }
  }
  ```

---

## 6. Monitoreo de Latencia en Tiempo Real (`/monitor`) — Objetivo 5

- **GET** `/monitor/latencia`
- **Respuesta 200:**
  ```json
  {
    "timestamp": "2026-09-03T19:40:00Z",
    "database_driver": "sqlite",
    "latencia_promedio_ms": 1.45,
    "metricas_detalle": {
      "ping_ms": 0.32,
      "count_gastos_ms": 1.15,
      "join_agregacion_ms": 2.88
    },
    "estado": "excelente"
  }
  ```
- **GET** `/monitor/health` — Verificación de estado del servidor y conexión a base de datos.
