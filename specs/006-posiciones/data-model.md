# Data Model: Posiciones organizacionales

Basado en `estructura_empresarial.dbml` y en lo que ya existe en el repositorio. Las marcas **(plan)** indican lo que decide este plan por encima del DBML.

## Posición (`posiciones`)

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| codigo | varchar(20) único, anulable en la base | `POS-` + id (≥ 4 dígitos), asignado en la transacción de creación; inmutable. **(plan: anulable por las filas anteriores; la aplicación lo exige)** |
| puesto_id | FK → puestos, restrict | Obligatorio al crear; no cambia |
| unidad_organizacional_id | FK → unidades_organizacionales, restrict, anulable en la base | Obligatoria para la aplicación; solo unidades activas **(plan: creada en 005)** |
| posicion_jefe_id | FK → posiciones, restrict, nulo | Supervisor; nulo solo en la máxima autoridad |
| user_id | FK → users, restrict, nulo | Ocupante; **solo lo escribe el módulo de gestión de personal** |
| estado | varchar(10), default `Vacante` | `Ocupada` \| `Vacante` \| `Suprimida` |
| modalidad | varchar(30) | `Nombramiento` \| `Contrato` \| `Encargo` \| `Subrogacion` (sin tilde; la interfaz muestra "Subrogación") |
| version | int, default 1 | **(plan)** control de edición simultánea; solo sube con ediciones de contenido |
| created_by, updated_by | bigint, nulos | Sin FK a `users` |
| created_at, updated_at | timestamp | |

Índices: `puesto_id`, `unidad_organizacional_id`, `posicion_jefe_id`, `estado`, `user_id`, y el único parcial `posiciones_una_maxima_autoridad` **(plan)**. Todas las FK `restrictOnDelete`; sin soft delete.

### Datos calculados (no se almacenan)

- `subordinadas_vigentes` (posiciones que reportan a ésta y no están Suprimidas) y la lista "supervisa a".
- Datos del puesto (código, nombre, descripción), de la unidad (código, nombre, tipo, estado) y nombre del ocupante: se leen en vivo de sus módulos.

## Registro de cambios (`auditoria_cambios`, creada en 004)

`entidad = 'posicion'`, `entidad_id` = id de la posición, `campo` ∈ `puesto_id`, `unidad_organizacional_id`, `posicion_jefe_id`, `modalidad`, `estado`; con `valor_anterior`, `valor_nuevo`, `user_id` (quien hizo el cambio, **no** el ocupante) y `created_at`. Referencias como id; en la creación, valor anterior nulo; solo inserción. El ocupante (`user_id`) y el paso Ocupada ⇄ Vacante los registra el módulo de personal.

## Invariantes (los mantiene la aplicación; la máxima autoridad, además, la base)

1. La unidad raíz (sin `unidad_padre_id`) tiene como máximo una posición vigente (no Suprimida): la máxima autoridad. Una posición vigente tiene `posicion_jefe_id` nulo **si y solo si** está en la unidad raíz; por tanto hay como máximo una vigente sin supervisor (lo respalda el índice parcial).
1b. **Jerarquía de unidades**: la supervisora de una posición vigente está en la misma unidad o en una unidad superior (su padre, la padre de esta, hasta la raíz). Al cambiar la unidad de una posición, la nueva es la misma o superior a la de cada subordinada directa vigente.
2. Sin ciclos en `posicion_jefe_id`.
3. Un supervisor nunca está Suprimido: suprimir exige no tener subordinadas vigentes.
4. Una posición vigente tiene puesto y unidad activos: se exige al crear y al cambiar de unidad; después lo garantizan las reglas de desactivación de 004 y 005.
5. Coherencia estado y ocupante: Ocupada ⇔ `user_id` no nulo; Vacante ⇒ nulo; Suprimida ⇒ nulo.
6. Las posiciones no se eliminan; Suprimida es definitivo.

## Transiciones (este módulo)

- **Crear**: `Vacante`, `user_id` nulo, `version` 1.
- **Editar** (Ocupada o Vacante): cambia `unidad_organizacional_id`, `posicion_jefe_id` y `modalidad`; sube `version` solo con cambios reales.
- **Suprimir**: `Vacante` → `Suprimida` si no tiene subordinadas vigentes; no cambia `version` ni `updated_by`.
- `Ocupada` ⇄ `Vacante`: lo hace personal; este módulo no lo toca.

## Reglas de visibilidad

- Listado: Administrador todas (filtro de estado); Usuario solo Ocupadas y Vacantes. Detalle: cualquiera, también Suprimidas. Auditoría: solo Administrador. Ocupante: nombre visible para todos.

## Datos iniciales (`PosicionesSeeder`)

Estructura de las 12 unidades con una máxima autoridad en la Gerencia General; ver research D-17. La revisión 2026-10-16 añade una Vacante de «Desarrollador de software» en Desarrollo Web que reporta a la Ocupada de esa misma unidad (supervisión dentro de una unidad), junto a la sección que reporta a la gerencia y las dos ramas distintas.
