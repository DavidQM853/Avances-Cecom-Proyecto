# 📊 Dashboard Presupuestos CECOM — Backend API

Backend del sistema web para la creación, gestión, filtrado y monitoreo de presupuestos interdepartamentales (OPEX) desarrollado para **CECOM / Seanalytics Solutions**.

Desarrollado en **Laravel 13** con **MySQL / MariaDB / SQLite** siguiendo una arquitectura limpia orientada a servicios y repositorios.

---

## 👥 Equipo de Trabajo

- **Alvarez Vela Israel** — Backend Developer
- **Quinteros Macias David** — Backend Developer
- **Ortega Monreal David** — Frontend Developer
- **Mena Gonzalez Saravia Jorge** — Frontend Developer

---

## 🚀 Características Implementadas

1. **Gestión Presupuestal y Partidas OPEX:**
   - Normalización de base de datos en 3FN con tablas para `departamentos`, `periodos_fiscales`, `categorias_opex`, `presupuestos`, `partidas_presupuestales` y `gastos_operativos`.
   - Columna calculada virtual `monto_disponible`.
   - Conciliación y bloqueo transaccional de partidas ante registro, edición y eliminación de gastos.

2. **Comparativa Year-over-Year (YoY) — Objetivo 1:**
   - Endpoint `/api/v1/presupuestos-yoy/comparativa` que calcula deltas absolutos, variaciones porcentuales interanuales y tendencias (*incremento*, *decremento*, *estable*) con desglose por categoría OPEX.

3. **Filtrado y Agregaciones en 6 Escalas Temporales — Objetivo 2:**
   - Motor de agregaciones [`AgregacionTemporalService`](app/Services/AgregacionTemporalService.php) que agrupa y totaliza gastos en:
     - **Diario** (día natural)
     - **Quincenal** (1ra quincena 1-15 y 2da quincena 16-fin de mes)
     - **Mensual** (mes calendario)
     - **Trimestral** (Q1, Q2, Q3, Q4)
     - **Semestral** (S1, S2)
     - **Anual** (año fiscal)
   - Comando de pre-cálculo masivo: `php artisan agregaciones:generar`.

4. **Motor de Roles Dinámicos — Objetivo 4:**
   - Algoritmo en [`RolDinamicoService`](app/Services/RolDinamicoService.php) que evalúa variables históricas y reglas JSON para asignar o revocar permisos/restricciones automáticamente:
     - **Aprobador:** Se asigna automáticamente si la ejecución trimestral es $\ge 80\%$.
     - **Auditor OPEX:** Se asigna si la variación interanual supera el $15\%$.
     - **Controlador:** Restringe operaciones en partidas críticas cuando la ejecución mensual supera el $95\%$.
   - Middleware de protección: `CheckOpexPermission` y `CheckRole`.
   - Auditoría completa en `historial_asignaciones`.
   - Comando de evaluación: `php artisan roles:evaluar`.

5. **Monitoreo de Latencia de Base de Datos en Tiempo Real — Objetivo 5:**
   - Endpoint `/api/v1/monitor/latencia` que reporta tiempos de respuesta (en ms) de ping, conteos masivos y consultas complejas con múltiples JOINs.

6. **Análisis de Variabilidad Intermensual — Objetivo 6:**
   - Endpoint `/api/v1/reportes/variabilidad-mensual` con cálculo estadístico de media, varianza, desviación estándar y Coeficiente de Variación (CV%).

7. **Pruebas de Estrés y Optimización:**
   - Comando `php artisan stress:test` que valida inserción en bloque (40,000+ ops/seg) y consultas agrupadas con latencia sub-segundo (< 5 ms).

---

## 🛠️ Requisitos de Instalación

- **PHP** $\ge 8.2$ (probado en PHP 8.4) con extensiones `curl`, `openssl`, `mbstring`, `pdo_mysql`, `pdo_sqlite`, `fileinfo`, `zip`.
- **Composer** $\ge 2.0$
- **MySQL / MariaDB** o **SQLite**

---

## 📦 Puesta en Marcha Local

1. **Clonar el repositorio:**
   ```bash
   git clone https://github.com/DavidQM853/dashboard-presupuestos-cecom.git
   cd dashboard-presupuestos-cecom
   ```

2. **Instalar dependencias:**
   ```bash
   composer install
   ```

3. **Configurar el entorno:**
   ```bash
   copy .env.example .env
   php artisan key:generate
   ```

4. **Ejecutar migraciones y datos semilla históricos (2024-2026):**
   ```bash
   php artisan migrate:fresh --seed
   ```
   *(Esto generará 8 departamentos, 21 categorías OPEX, 51 períodos fiscales, 21 presupuestos y 4,477 gastos operativos reales para pruebas inmediatas).*

5. **Iniciar el servidor de desarrollo:**
   ```bash
   php artisan serve
   ```
   El servidor estará disponible en `http://127.0.0.1:8000`.

---

## 🧪 Pruebas y Benchmarking

- **Correr suite de pruebas con PHPUnit:**
  ```bash
  php artisan test
  ```
- **Ejecutar prueba de estrés con 3,000 registros:**
  ```bash
  php artisan stress:test --records=3000
  ```
- **Ejecutar motor de roles dinámicos:**
  ```bash
  php artisan roles:evaluar --anio=2026
  ```

---

## 📚 Documentación de API

Consulta el archivo completo [`API_DOCUMENTATION.md`](API_DOCUMENTATION.md) para ver la especificación detallada de los 40 endpoints, parámetros de consulta, códigos de respuesta y ejemplos de payloads JSON.
