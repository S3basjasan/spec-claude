# Research: Diccionario de competencias

Sin `NEEDS CLARIFICATION` abiertos. Decisiones a partir del insumo del 2026-10-10, la spec, la constitución y
la implementación existente (que sigue el plan del 2026-10-09).

## D-01 Ubicación y rutas

- **Decision**: Backend en `backend/app/Modules/Estructura/` y frontend en
  `frontend/src/modules/estructura/competencias/`. Rutas `/api/competencias`.
- **Rationale**: Lo fija el insumo. El módulo `Estructura` ya existe y está registrado.
- **Alternatives considered**: `/api/estructura/competencias` (cumple la constitución al pie de la letra,
  pero contradice el contrato indicado).

## D-02 Cómo se ajusta el modelo ya construido

- **Decision**: Modificar en su sitio las dos migraciones existentes (`competencias`, `competencia_grados`) y
  añadir una tercera (`competencia_estado_historial`); luego `migrate:fresh --seed` en desarrollo y pruebas.
- **Rationale**: La feature no se ha desplegado y los únicos datos son semillas. Una migración incremental
  tendría que reescribir el formato del código (`COM-001` → `COMP-0001`), los valores de `tipo` y quitar la
  cascada de la FK, algo muy incómodo en SQLite (reconstrucción de tablas) para ningún beneficio.
- **Alternatives considered**: migraciones nuevas que alteran las tablas (más pasos y riesgo, sin datos que
  preservar). Si en algún momento existieran datos reales, esta decisión se revisa antes de aplicarla.

## D-03 Estado y vocabulario de valores

- **Decision**: `estado` ∈ `Activo` | `Inactivo` (por defecto `Activo`) y `tipo` ∈ `Cardinal` | `Especifica`,
  con esos mismos textos en base de datos y API. Enums de dominio `EstadoCompetencia` y `TipoCompetencia`.
- **Rationale**: Es lo que define el insumo (modelo de datos y cuerpo del `PATCH`). Un solo vocabulario en
  todas las capas evita traducciones. Cambia el `tipo` actual (`cardinal`/`especifica`).
- **Alternatives considered**: mantener `tipo` en minúsculas (menos cambios, pero dos vocabularios distintos
  para dos campos hermanos).

## D-04 Código `COMP-NNNN`

- **Decision**: Columna `codigo` única asignada en la misma transacción de creación a partir del `id`
  autoincremental (`COMP-` + `id` con al menos 4 dígitos); anulable solo mientras se crea la fila; nunca
  se modifica. Al no existir `DELETE`, no hay huecos que reutilizar.
- **Rationale**: Mantiene el mecanismo ya probado y cumple FR-004 (secuencial, inmutable).
- **Alternatives considered**: secuencia propia (más piezas para el mismo resultado).

## D-05 Nombre único normalizado

- **Decision**: Se conservan `nombre` (tal como se escribió) y `nombre_clave` (recortado y en minúsculas
  Unicode) con índice único; la unicidad abarca activas e inactivas.
- **Rationale**: El insumo pide "nombre (único, normalizado)". La colación `nocase` de SQLite no ignora
  mayúsculas con acento, por eso la clave explícita (ya corregido en la implementación actual).
- **Alternatives considered**: solo `nombre` normalizado (pierde la capitalización original que se muestra).

## D-06 Auditoría y historial

- **Decision**: `created_by` y `updated_by` son enteros anulables **sin clave foránea** (id del usuario del
  token); `created_at`/`updated_at` son los de Eloquent. Tabla `competencia_estado_historial` (id,
  `competencia_id` FK sin cascada, `estado_anterior`, `estado_nuevo`, `user_id`, `created_at`) de solo
  inserción: sin `updated_at`, sin endpoints ni casos de uso de edición o borrado.
- **Rationale**: Constitución I prohíbe acceder a tablas de otro módulo; el id del actor llega desde el borde
  de entrada. `updated_by` cambia solo con `PUT`; el cambio de estado queda en el historial. La creación no es
  un cambio de estado y no genera registro.
- **Alternatives considered**: FK a `users` (acopla `Estructura` con `Shared`); registrar el alta como primer
  evento del historial (el insumo habla de "cambios de estado").

## D-07 Edición concurrente y cambio de estado

- **Decision**: La columna `version` (+1 en cada `PUT`) sigue protegiendo la edición (FR-022). El cambio de
  estado **no** modifica `version`: una competencia desactivada mientras alguien la edita sigue pudiendo
  guardarse. El cambio de estado es una actualización condicional (`WHERE id = ? AND estado <> nuevo`) que,
  si no afecta ninguna fila y la competencia existe, responde 422 (ya estaba en ese estado) sin escribir
  historial.
- **Rationale**: Cumple los casos límite de la spec (estado simultáneo sin registros repetidos; edición sigue
  posible) y evita carreras sin bloqueos.
- **Alternatives considered**: bump de `version` al cambiar de estado (obliga a recargar a quien edita
  datos que no cambian); leer y luego escribir (carrera entre dos administradores).

## D-08 Visibilidad por rol en el listado

- **Decision**: El borde de entrada (request/controlador) decide el estado efectivo: si el token no tiene la
  capacidad `admin`, siempre `Activo` (el parámetro `estado` se ignora); si la tiene, el valor pedido y por
  defecto `Activo` (`Inactivo` o `Todos`). El caso de uso recibe el filtro ya resuelto. `GET /{id}` devuelve
  también las inactivas a cualquier persona autenticada.
- **Rationale**: FR-021 pide verificar el rol en la petición; la autorización va en el borde (constitución I)
  y el caso de uso no conoce roles. Ignorar el parámetro no revela si existen inactivas.
- **Alternatives considered**: responder 403/422 a un Usuario que envía `estado` (filtraría información de
  que existen inactivas y rompería clientes que lo envíen por costumbre).

## D-09 Fin del bloqueo por uso

- **Decision**: Se eliminan `DELETE`, `EliminarCompetencia`, `CompetenciaEnUso`, `VerificadoresDeUso`,
  `VerificadorDeUsoInterface` y `registrarVerificadorDeUso`. La Facade ofrece `estaActiva` y
  `getCompetenciasActivas` para que otros módulos validen **nuevas** asignaciones, y `getCompetencia` /
  `getCompetenciasPorIds` devuelven también las inactivas para consultar registros históricos.
- **Rationale**: Sin borrado no hay nada que bloquear (FR-008); FR-019 pide solo-activas para nuevas
  asignaciones y conservar las referencias.
- **Alternatives considered**: mantener el registro de verificadores "por si acaso" (código muerto).

## D-10 Interfaz y diseño

- **Decision**: Patrones Listado, Formulario y Estados de `design/DESIGN.md`. Antes de implementar se
  actualiza `design/DESIGN.md` (§4 Badge con variante neutra para Inactivo; §9 columna Código, badge de
  estado, filtro de estado y botón Activar/Desactivar). Sin componentes nuevos: se extiende `Badge`
  (`Activo` con el estilo actual `--soft`/`--accent`; `Inactivo` neutro `--border`/`--muted`, ambos
  ≥ 4.5:1). Desactivar usa `ConfirmDialog`; activar actúa directamente.
- **Rationale**: Constitución III e insumo ("no crear componentes nuevos si existe uno equivalente").
- **Alternatives considered**: color `--danger` para Inactivo (transmite error, no un estado normal).

## D-11 Pruebas

- **Decision**: Pest Feature por endpoint (éxito, 401, 403, 404, 409, 422, filtros, paginación, visibilidad
  por rol, auditoría, historial, ausencia de `DELETE`) y Unit por caso de uso; Vitest + Testing Library para
  listado, formulario, `ConfirmDialog`, hooks y validación; Playwright con los flujos del Administrador y del
  Usuario (activar/desactivar, filtro de estado, inactivas ocultas para el Usuario).
- **Rationale**: Constitución IV y II.
