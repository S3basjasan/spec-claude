# Data model: Registro de ingresos y reingresos laborales (GP-02)

Almacenamiento SQLite. Migraciones nuevas con fecha `2026_10_16_00000N`. Equivalencias con el insumo (PostgreSQL) en
[research.md](research.md) D-02. Fechas de calendario como `date` (texto `AAAA-MM-DD`); intervalos `[inicio, fin)` con
fin `NULL` = abierto.

## Cambio en `posiciones` (módulo Estructura; tabla mínima de 004/005)

| Columna | Tipo | Restricción |
|---|---|---|
| tenant_id | unsignedBigInteger | NOT NULL, DEFAULT 1, índice; las posiciones existentes pasan al tenant 1 |

Sin otras columnas ni cambios de estado: GP-02 **no escribe** `posiciones.estado`.

## Tabla `vinculos_laborales` (módulo GestionPersonal)

| Columna | Tipo | Restricción / regla |
|---|---|---|
| id | integer autoincremental | PK |
| tenant_id | unsignedBigInteger | NOT NULL; siempre del contexto de la cuenta |
| colaborador_id | unsignedBigInteger | NOT NULL; FK a `colaboradores.id`, `ON DELETE RESTRICT` |
| fecha_ingreso | date | NOT NULL; pasada, actual o futura |
| fecha_salida | date | NULL; solo la fija el proceso de salidas; `CHECK (fecha_salida IS NULL OR fecha_salida >= fecha_ingreso)` |
| tipo_contrato_clave | string(80) | NOT NULL; una de `indefinido`, `plazo_fijo`, `temporal`, `servicios_profesionales` |
| version | unsignedInteger | NOT NULL, DEFAULT 1 |
| operacion_id | char(36) | NOT NULL; UNIQUE(tenant_id, operacion_id) |
| operacion_hash | char(64) | NOT NULL; SHA-256 del cuerpo canónico del alta |
| created_by, updated_by | unsignedBigInteger | NOT NULL; id de cuenta, sin FK |
| created_at, updated_at | timestamps | `created_at` = momento del registro |

Índices: `(colaborador_id)`, `(tenant_id, colaborador_id, fecha_ingreso)`, único parcial
`UNIQUE(colaborador_id) WHERE fecha_salida IS NULL` (un vínculo abierto por ficha).

## Tabla `asignaciones_laborales` (módulo GestionPersonal)

| Columna | Tipo | Restricción / regla |
|---|---|---|
| id | integer autoincremental | PK |
| tenant_id | unsignedBigInteger | NOT NULL |
| vinculo_id | unsignedBigInteger | NOT NULL; FK a `vinculos_laborales.id`, `ON DELETE RESTRICT` |
| posicion_id | unsignedBigInteger | NOT NULL; **sin FK** (contrato de Estructura) |
| fecha_inicio | date | NOT NULL; igual a `fecha_ingreso` en la asignación inicial |
| fecha_fin | date | NULL; `CHECK (fecha_fin IS NULL OR fecha_fin >= fecha_inicio)` |
| version | unsignedInteger | NOT NULL, DEFAULT 1 |
| created_by, updated_by | unsignedBigInteger | NOT NULL |
| created_at, updated_at | timestamps | |

Índices: `(vinculo_id, fecha_inicio)`, `(posicion_id, fecha_inicio)`, único parcial
`UNIQUE(posicion_id) WHERE fecha_fin IS NULL` (una asignación abierta por posición) y
`UNIQUE(vinculo_id) WHERE fecha_fin IS NULL`.

## Reglas de integridad (triggers SQLite, además de la aplicación)

`BEFORE INSERT` y `BEFORE UPDATE` abortan con un mensaje que el repositorio traduce a un error de negocio:

| Trigger | Condición que aborta | Mensaje → error |
|---|---|---|
| `vinculos_sin_superposicion` | Otro vínculo de la misma ficha cuyo intervalo no vacío se superpone | `VINCULO_SUPERPUESTO` → `VINCULO_SUPERPUESTO` |
| `posicion_sin_superposicion` | Otra asignación de la misma posición con intervalo superpuesto no vacío | `POSICION_SUPERPUESTA` → `POSICION_NO_DISPONIBLE` |
| `vinculo_una_posicion_por_fecha` | Otra asignación del mismo vínculo superpuesta | `ASIGNACION_SUPERPUESTA` → `VINCULO_SUPERPUESTO` |
| `asignacion_contenida_en_vinculo` (insert/update de asignación) | `fecha_inicio < fecha_ingreso` o `fecha_fin` mayor que la salida del vínculo (con salida nula la asignación puede ser abierta) | `ASIGNACION_FUERA_DE_VINCULO` → error interno (no debe ocurrir) |
| `vinculo_contiene_asignaciones` (update de vínculo) | Acortar o mover el vínculo deja una asignación fuera | `VINCULO_ACORTADO_SOBRE_ASIGNACION` → error interno |

Superposición entre A y B: `A.inicio < COALESCE(B.fin,'9999-12-31') AND B.inicio < COALESCE(A.fin,'9999-12-31') AND
A.inicio < COALESCE(A.fin,'9999-12-31') AND B.inicio < COALESCE(B.fin,'9999-12-31')`.

## Entidades de dominio y derivados

| Elemento | Origen | Regla |
|---|---|---|
| `PeriodoLaboral` | Dominio puro | Inicio, fin opcional; `superpone(otro)`, `contiene(fecha)`, `estaVacio()`, `terminaAntesDe(fecha)` |
| `SituacionLaboral` | Derivado | `estado` (Activo \| Desvinculado \| SinVinculoVigente), vínculo vigente, próximo ingreso; nunca almacenado |
| Hecho de trayectoria | Derivado de vínculos | `tipo` = `Ingreso` si es el primer vínculo de la ficha, `Reingreso` si hay uno anterior; `fecha_efectiva`, `tipo_contrato`, `posicion_id`, `programado = fecha_ingreso > hoy` |

## Transiciones

| Operación | Efecto |
|---|---|
| Registrar ingreso | Crea vínculo (`version` 1, abierto) y asignación inicial (`fecha_inicio = fecha_ingreso`, abierta) en una transacción |
| Cruce de fecha | Sin escritura: el estado se recalcula en la lectura |
| Rechazo o fallo | Sin escritura; ni vínculo ni asignación |
| Salida, movimiento, corrección | Fuera de alcance (las salidas fijarán `fecha_salida` y `fecha_fin`) |

## Datos iniciales (`IngresosSeeder`, después de `PersonalSeeder`, `PuestosSeeder` y `UnidadesSeeder`)

Datos sintéticos; fecha de referencia = hoy del tenant.

| Elemento | Detalle |
|---|---|
| Puesto compartido | `PU-002` con 3 posiciones; dos reciben unidad activa y tenant 1 |
| Ocupada hoy | EMP-001 (Ana Núñez): vínculo `indefinido` desde hace 1 año, asignación en la posición A |
| Posición libre | Posición B del mismo puesto, sin asignaciones |
| Ingreso futuro | EMP-004: vínculo `plazo_fijo` que inicia en +30 días en la posición C |
| Vínculo cerrado | EMP-005: vínculo `temporal` de hace 2 años a hace 6 meses (salida efectiva): estado Desvinculado |
| Sin vínculo | EMP-002 y EMP-003 |
| Otro tenant | Una posición con `tenant_id` = 2 y su ficha, para probar aislamiento |
