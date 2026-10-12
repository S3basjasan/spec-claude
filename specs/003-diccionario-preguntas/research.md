# Research: Diccionario de preguntas

Sin `NEEDS CLARIFICATION` abiertos. Decisiones a partir del insumo del 2026-10-11, la spec (con sus dos
clarificaciones), la constitución y el estado de 001 y 002 (ya implementados).

## D-01 Ubicación y rutas

- **Decision**: Backend en `backend/app/Modules/Estructura/` y frontend en `frontend/src/modules/estructura/preguntas/`.
  Rutas `/api/preguntas`; el recurso se identifica por `competencia_id` (hay un solo registro por competencia).
- **Rationale**: Lo fija el insumo, igual que en 002. Las relaciones Eloquent son internas del módulo.
- **Alternatives considered**: `/api/estructura/preguntas` (contradice el contrato indicado); id propio en las rutas
  (redundante con el 1↔1).

## D-02 Modelo de datos: cabecera más cuatro filas

- **Decision**: Se añade `competencia_pregunta_registros` (`competencia_id` único con FK sin cascada, `version`,
  `created_by`, `updated_by`, timestamps) y `competencia_preguntas` (`registro_id` FK sin cascada, `orden` 1–4,
  `pregunta` ≤ 500, `created_by`, `updated_by`, timestamps, única en `(registro_id, orden)`). No se tocan `competencias`
  ni `competencia_grados`. "Existe registro" ⇔ existe su cabecera, y la invariante "exactamente 4 filas con orden 1–4"
  se garantiza escribiendo todo en una transacción.
- **Rationale**: La spec exige auditoría por registro que solo cambia con cambios reales (FR-018), versión para el
  control de edición simultánea (FR-021) y unicidad por competencia que frene creaciones simultáneas (SC-005). El
  insumo dice que "una competencia tiene registro cuando existen sus 4 preguntas", pero con solo filas la unicidad
  del registro completo no se puede forzar con una restricción y no hay dónde guardar `version`. Es la misma solución
  ya aprobada en 002.
- **Alternatives considered**: solo `competencia_preguntas` con `competencia_id` (como el insumo; sin versión ni
  auditoría de registro); `version` repetida en las 4 filas (duplicación e inconsistencias posibles).

## D-03 El estado y los datos de la competencia no se almacenan

- **Decision**: Código, nombre, descripción, tipo y estado se obtienen con `join` a `competencias` en cada lectura
  (listado, detalle, Facade). No se copian ni sincronizan.
- **Rationale**: FR-008 y FR-009; desactivar o reactivar la competencia cambia el registro al instante.
- **Alternatives considered**: copiar campos o escuchar eventos de 001 (duplica datos).

## D-04 Visibilidad por rol (igual que 001 y 002)

- **Decision**: Sin capacidad `admin`, el listado es siempre `Activo` (el parámetro `estado` se ignora); con ella, el
  pedido y por defecto `Activo` (`Inactivo` o `Todos`). `GET /{competencia_id}` devuelve también registros de
  competencias inactivas a cualquier persona autenticada.
- **Rationale**: FR-015, FR-020 y FR-023.
- **Alternatives considered**: 403/422 al Usuario que envía `estado` (filtra información).

## D-05 Reglas de validación

- **Decision**: `preguntas` es un arreglo de **exactamente 4** textos, recortados, no vacíos, ≤ 500 caracteres y sin
  repetir entre sí (comparación sin distinguir mayúsculas ni espacios alrededor). `competencia_id` en `POST` debe
  existir, estar `Activo` y no tener registro (cada causa con su mensaje). En `PUT` un `competencia_id` distinto del
  de la ruta es 422 y `version` es obligatorio. El orden de cada texto es su posición en el arreglo (1–4).
- **Rationale**: FR-006, FR-007, FR-005 y FR-021 y las clarificaciones (500 caracteres, sin repetidas).
- **Alternatives considered**: validar duplicados solo en el frontend (la API es el límite de confianza).

## D-06 Edición: reemplazo con detección de cambios

- **Decision**: `ActualizarPreguntas` compara los cuatro textos normalizados con los actuales. Si son idénticos
  devuelve 200 con el registro tal cual, **sin** tocar filas, cabecera, `version` ni auditoría. Si hay cambios y la
  `version` coincide (`UPDATE … WHERE competencia_id = ? AND version = ?`), actualiza **por `orden`** las filas
  existentes (conserva `created_by`; fija `updated_by` solo en las filas cuyo texto cambió), incrementa `version` y
  fija `updated_by` en la cabecera, todo en una transacción. No inserta ni elimina filas (siempre son 4). Versión
  distinta → 409. Permitido con la competencia inactiva.
- **Rationale**: Más simple que 002 porque el número de filas es fijo.
- **Alternatives considered**: reescribir siempre (rompe "sin cambios reales"); borrar y reinsertar (pierde
  `created_by`).

## D-07 Creación y concurrencia

- **Decision**: Igual que 002: en una transacción se valida competencia `Activo` sin cabecera, se inserta cabecera y
  4 filas; la violación del índice único de `competencia_id` se traduce a `CompetenciaYaTieneRegistro` (422); una
  competencia desactivada entre la elección y el guardado da `CompetenciaNoDisponible` (422).
- **Rationale**: Caso límite de dos administradores simultáneos y de competencia desactivada.
- **Alternatives considered**: bloqueo pesimista (innecesario con el índice único).

## D-08 Reutilización de código de 002

- **Decision**: Se reutilizan del módulo `Estructura` las excepciones `CompetenciaNoDisponible` ("La competencia no
  existe o no está activa.") y `RegistroModificado` ("El registro cambió desde que lo abriste…") y el value object
  `CompetenciaAsociada`, porque su significado y mensaje son genéricos. `CompetenciaYaTieneRegistro` y
  `RegistroNoEncontrado` de 002 son `final` y mencionan "comportamientos" en el mensaje, así que se crean
  `CompetenciaYaTienePreguntas` ("La competencia ya tiene un registro de preguntas.") y `PreguntasNoEncontradas`
  ("El registro de preguntas no existe."). Repositorio, entidad `RegistroPreguntas` y casos de uso son propios. No se
  refactoriza 002.
- **Rationale**: Evita duplicar clases sin cambiar 002; se mantiene el alcance.
- **Alternatives considered**: duplicar las excepciones (ruido); extraer una base común para ambos diccionarios
  (refactor fuera de alcance, posible mejora futura).

## D-09 Endpoint de competencias disponibles

- **Decision**: `GET /api/preguntas/competencias-disponibles` (solo `admin`) devuelve las competencias activas sin
  cabecera de preguntas, ordenadas por nombre, con código, nombre, descripción y tipo; se declara antes de
  `/{competencia_id}`. El filtrado por texto del selector se hace en el cliente.
- **Rationale**: Insumo y clarificación de 002; el volumen no justifica búsqueda en servidor.
- **Alternatives considered**: `q` en el servidor.

## D-10 Facade

- **Decision**: `EstructuraFacade` se amplía con `getPreguntas(int $competenciaId): ?array` (incluye inactivas, para
  históricos), `getPreguntasPorCompetencias(array $ids): array`, `tienePreguntasActivas(int $competenciaId): bool` y
  `getPreguntasActivas(): array`. Siempre arreglos, nunca modelos. Las cuatro preguntas se devuelven como lista
  ordenada de cuatro textos.
- **Rationale**: FR-019 y FR-020 con el mismo criterio que 001 y 002.
- **Alternatives considered**: que otros módulos consulten la API HTTP o las tablas (prohibido).

## D-11 Interfaz y diseño

- **Decision**: Patrones Listado, Formulario y Estados de `design/DESIGN.md`. Se reutilizan `Combobox` y `Alert`
  neutro de 002. Se registra en el diseño (§5 y §9) la fila desplegable con **lista numerada** de preguntas y el
  formulario con cuatro campos numerados fijos con contador. No hay campos repetibles.
- **Rationale**: Constitución III e insumo ("no crear componentes nuevos si existe uno equivalente").
- **Alternatives considered**: reutilizar `ListaDeCampos` (sirve para cantidades variables; aquí son 4 fijos y sin
  agregar/quitar).

## D-12 Pruebas

- **Decision**: Pest Feature por endpoint (éxito, 401, 403, 404, 409, 422, filtros, visibilidad por rol, estado
  derivado, auditoría, sin cambios reales, creaciones simultáneas, ausencia de `DELETE`), Unit por caso de uso con
  repositorio en memoria y prueba de la Facade. Vitest + Testing Library para listado, panel, formulario y validación.
  Playwright con los flujos del Administrador y del Usuario, el efecto de desactivar la competencia en 001 y los
  anchos 320/1440.
- **Rationale**: Constitución IV y II.
