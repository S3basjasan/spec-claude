# Research: Posiciones organizacionales

Sin `NEEDS CLARIFICATION` abiertos. Decisiones a partir del insumo `plan-posiciones.md`, la spec (con sus tres clarificaciones), la constitución y el estado real del repositorio (000–005 implementados). Donde el insumo y la realidad o la spec difieren, se indica cuál prevalece y por qué.

## D-01 Ubicación, rutas y una sola pantalla

- **Decision**: Backend en `backend/app/Modules/Estructura/`; frontend en `frontend/src/modules/estructura/posiciones/`. Rutas `/api/posiciones`; el recurso se identifica por el `id` numérico. Una sola ruta de interfaz, `/posiciones`, con modales (crear y editar) y un diálogo (suprimir).
- **Rationale**: Lo fija el insumo, igual que en 001–005.
- **Alternatives considered**: `/api/estructura/posiciones` (contradice el contrato); páginas de formulario (contradice el insumo y el patrón de modales de 005).

## D-02 El modelo del insumo no coincide con el repositorio

- **Hallazgo**: El insumo afirma que el modelo "está completo en estructura_empresarial.dbml" y que no se crean tablas ni columnas. En el repositorio, `posiciones` es la tabla **mínima** de 004 (`id`, `puesto_id`, `estado`, marcas de tiempo) a la que 005 añadió `unidad_organizacional_id` (anulable). Faltan `codigo`, `user_id`, `posicion_jefe_id`, `modalidad`, `created_by`, `updated_by` y la columna `version`. No existen las tablas de roles y permisos. La tabla ya tiene filas sembradas (seis, sin unidad ni código en 004; con unidad en 005).
- **Decision**: Una migración nueva `2026_10_16_000001_complete_posiciones_table` añade: `codigo` (`string(20)`, anulable, único), `user_id` (FK a `users`, `restrictOnDelete`, anulable, con índice), `posicion_jefe_id` (FK a `posiciones`, `restrictOnDelete`, anulable, con índice), `modalidad` (`string(30)`, con valor por defecto `Nombramiento` solo para las filas ya sembradas), `version` (entero, por defecto 1), `created_by` y `updated_by` (enteros anulables, sin FK). Más el índice único parcial de D-04. `unidad_organizacional_id` y `codigo` se quedan **anulables en la base**; la aplicación los exige siempre y el seeder completa las filas existentes. `PosicionModel` gana los campos y las relaciones.
- **Rationale**: La migración nueva no rompe bases ya migradas; SQLite no permite añadir NOT NULL sin valor ni alterar columnas sin recrear la tabla.
- **Alternatives considered**: Editar las migraciones de 004/005 (rompe bases migradas); recrear la tabla (invade features ya cerradas).

## D-03 Autorización: capacidad `admin`, no permisos nuevos

- **Decision**: Crear, actualizar, suprimir y `opciones`: `auth:sanctum` + `admin`. Listar y consultar: `auth:sanctum`. Los tres permisos del insumo equivalen a `admin`.
- **Rationale**: No hay tablas ni servicio de permisos; la constitución define la autorización administrativa con Sanctum + `admin`.
- **Alternatives considered**: Implantar roles y permisos por acción (otra feature).

## D-04 Máxima autoridad única: validación y índice parcial

- **Decision**: Se valida en la transacción y, además, un índice único parcial `posiciones_una_maxima_autoridad` sobre una expresión constante (`(1)`) donde `posicion_jefe_id is null and estado <> 'Suprimida'` impide dos filas vigentes sin supervisor aunque dos peticiones lleguen a la vez. Una violación se traduce a `PosicionInvalida` (error en `posicion_jefe_id`).
- **Rationale**: SQLite ignora `lockForUpdate`; el índice hace la regla atómica.
- **Alternatives considered**: Bloquear filas (insumo): no soportado; validar solo en PHP: ventana de carrera. **Nota**: si el almacenamiento dejara de ser SQLite, se revisa (los índices de expresión parciales no existen en MySQL).

## D-05 Código `POS-####` derivado del `id`

- **Decision**: Se inserta la posición con `codigo` nulo y, en la misma transacción, se asigna `sprintf('POS-%04d', id)`. Sin reutilización ni reintento. Las filas sembradas anteriormente reciben su código del mismo modo al sembrarse.
- **Alternatives considered**: "consecutivo con reintento" (insumo).

## D-06 Escrituras atómicas sobre SQLite: primero el candado de escritura

- **Decision**: Toda escritura que depende del estado vigente va en una transacción cuyo **primer** enunciado es una escritura; después se valida contra el estado vigente.
  - **Crear**: inserta la fila (Vacante, `version` 1, `user_id` nulo, `codigo` nulo) y valida con el candado tomado: puesto existente y activo, unidad existente y activa, supervisor existente y no Suprimido, y máxima autoridad única. El estado y el ocupante nunca vienen del cliente.
  - **Actualizar**: sin cambios reales no escribe; si hay, el primer enunciado es `UPDATE … SET version = version + 1, updated_by, updated_at WHERE id = ? AND version = ? AND estado <> 'Suprimida'`; 0 filas → 409 si cambió la versión o 422 si está Suprimida. Valida unidad activa (solo si cambia), supervisor (no Suprimido, no ella ni subordinadas, máxima autoridad) y escribe campos y historial.
- **Rationale**: Mismo principio que 004 y 005. Evita las carreras de ciclos, de máxima autoridad y de "desactivo el puesto o la unidad mientras creo la posición" (las desactivaciones de 004 y 005 son sentencias condicionales con `NOT EXISTS` sobre `posiciones`, así que se serializan con estas escrituras).
- **Alternatives considered**: `lockForUpdate` / `sharedLock` (insumo; SQLite los ignora).

## D-07 Árbol de supervisión en la aplicación

- **Decision**: `ArbolDeSupervision` (dominio puro) se arma a partir de `id` y `posicion_jefe_id` de las posiciones **no Suprimidas** y calcula las subordinadas directas e indirectas y detecta ciclos; no hay consultas recursivas. El filtro de unidad usa `ArbolDeUnidades` (005) para obtener los ids de la rama.
- **Rationale**: Pocos miles de posiciones; mismo patrón que 005.

## D-08 Suprimir: una sola sentencia condicional

- **Decision**: `UPDATE posiciones SET estado = 'Suprimida', version = version … WHERE id = ? AND estado = 'Vacante' AND NOT EXISTS (subordinadas con estado <> 'Suprimida')`; no toca `version`, `updated_by` ni `updated_at` (como el cambio de estado de 004 y 005). Si no afecta filas se distingue: ya Suprimida → 200 sin cambios; Ocupada → 422 con el motivo; con subordinadas → 422 con el motivo y la cantidad. La auditoría (estado Vacante → Suprimida) se escribe en la misma transacción.
- **Rationale**: Comprobar y cambiar es una operación atómica; el módulo de personal debe ocupar con otra sentencia condicional sobre `estado = 'Vacante'`.

## D-09 Estado y ocupante: este módulo no los escribe

- **Decision**: Al crear: `estado = 'Vacante'`, `user_id` nulo. Ningún endpoint acepta `estado`, `user_id`, `codigo` ni `puesto_id` (al editar). El **ocupante** se lee de `user_id` y su nombre por `NombresDeUsuariosInterface` (`SharedFacade`). Las posiciones Ocupadas existentes (sembradas) se muestran con su ocupante.
- **Contrato con personal**: el módulo de gestión de personal ocupará solo posiciones `Vacante` con una sentencia condicional, escribirá `user_id` y `estado` y registrará `user_id`/`estado` en `auditoria_cambios` con `entidad = 'posicion'`.

## D-10 Historial con comparador de dominio puro

- **Decision**: `ComparadorDeCambiosDePosicion` devuelve las filas: una por campo cambiado (`puesto_id`, `unidad_organizacional_id`, `posicion_jefe_id`, `modalidad`, `estado`); en la creación, una por dato inicial con valor y anterior nulo; `puesto_id` solo al crear. Referencias como id. `auditoria_cambios.entidad` (30 caracteres) admite `posicion`. En esa tabla `user_id` es quien hizo el cambio, no el ocupante.

## D-11 Visibilidad por rol y datos de auditoría

- **Decision**: El Usuario solo recibe Ocupadas y Vacantes (`Suprimida` y `Todas` se tratan como `Vigentes`; `Ocupada` y `Vacante` se respetan) y nunca la auditoría; el Administrador recibe `auditoria` en el detalle, con nombres de usuario. El detalle de cualquier posición, también Suprimida, está disponible para todos.
- **Rationale**: La clarificación de la spec prevalece sobre el insumo.

## D-12 Listado, búsqueda, filtros y paginación

- **Decision**: Orden numérico por la parte secuencial del código; `per_page` por defecto 10 (máximo 50). Búsqueda `q` sobre código, nombre del puesto (columna `nombre_busqueda` ya normalizada) y el puesto o código de la posición supervisora. Filtro `estado`: `Vigentes` (predeterminado), `Ocupada`, `Vacante`, `Suprimida`, `Todas`. Filtro `unidad_organizacional_id`: la unidad y todas sus dependientes. Respuesta con `meta` de paginación igual que 004.
- **Alternatives considered**: Cargar todo y filtrar en memoria (no escala a miles); búsqueda recursiva en SQL (el insumo pide armar el árbol en la aplicación).

## D-13 Endpoint de opciones

- **Decision**: `GET /api/posiciones/opciones` (admin) devuelve los puestos activos, las unidades en orden de árbol con `habilitada` (= activa), las posiciones no Suprimidas candidatas a supervisor (con el nombre del ocupante) y `hay_maxima_autoridad`. Con `excluir=id` deja fuera la posición y todas sus subordinadas y `hay_maxima_autoridad` ignora la excluida. Se declara antes de `{posicion}`.

## D-14 Contrato con puestos y unidades

- **Decision**: Las reglas de desactivación de 004 (puesto) y 005 (unidad, incluso en cascada) ya cuentan posiciones Ocupadas o Vacantes sobre `posiciones`; esta feature las alimenta sin cambiarlas. Las posiciones Suprimidas conservan su puesto y su unidad aunque luego se desactiven.

## D-15 Facade

- **Decision**: Solo lectura: `getPosicion`, `getPosicionesPorIds`, `existePosicion`, `getArbolDeSupervision` y `posicionEstaVigente` (para el módulo de personal). Solo arreglos. Ver [contracts/estructura-facade.md](contracts/estructura-facade.md).

## D-16 Interfaz y diseño

- **Decision**: Listado con filas desplegables como `Puestos.dc.html` (una abierta a la vez, detalle cargado al desplegar) y paginador de `Pagination`. Badges: Ocupada normal, Vacante ámbar (`--warning`), Suprimida neutra; el texto del estado siempre visible. Modales con `Modal` (005) y supresión con `ConfirmDialog`. El tablero `design/Posiciones.dc.html` **no existe**: se crea antes del frontend con el listado, los filtros (el de unidad en árbol), la fila desplegada, los modales, el diálogo y el estado de suprimir deshabilitado. Diferencias del insumo con los datos de ejemplo del diseño (modalidades, unidades, supervisor como puesto, código de tres dígitos, posiciones sobre puestos inactivos) se documentan en `DESIGN.md` §9.

## D-17 Datos iniciales

- **Decision**: `PosicionesSeeder` (después de `UnidadesSeeder`) completa las seis filas ya sembradas (código, modalidad, supervisor; los dos ocupantes son `admin@hrms.test` y `usuario@hrms.test`) y añade posiciones Vacantes hasta reproducir la estructura de las 12 unidades: una máxima autoridad en la Gerencia General, gerentes que reportan a ella y posiciones de áreas, departamentos y secciones que reportan a su jefatura. Condiciones: una Ocupada en la Sección Desarrollo Web (UO-012); ninguna Ocupada ni Vacante en Infraestructura (UO-005), Soporte (UO-009) ni Capacitación (UO-008; solo Suprimidas); al menos una unidad activa sin posiciones (UO-011); al menos una posición de cada modalidad; una Vacante sin subordinadas, una Ocupada y una Vacante con subordinadas vigentes; al menos una Suprimida sin ocupante ni subordinadas. Las posiciones Ocupadas y Vacantes solo usan puestos y unidades activos; como solo hay cuatro puestos activos, se reutilizan.

## D-18 Pruebas

- **Decision**: Unit (árbol de supervisión, comparador, casos de uso con dobles en memoria); Feature (API, repositorio, atomicidad, concurrencia de máxima autoridad, supresión y desactivación de puesto o unidad, auditoría); Vitest (fila, detalle, modales, filtros); Playwright (consulta, creación, edición, supresión, accesibilidad y escala).

---

## Revisión 2026-10-16 (jerarquía de unidades y unidad raíz)

## D-19 Jerarquía de unidades

- **Decision**: Servicio de dominio puro `JerarquiaDeUnidades` sobre `ArbolDeUnidades` (005): `superioresDe(unidad)` (de la padre a la raíz), `raiz()` y `esIgualOSuperior(candidata, unidad)`. Una supervisora es válida si su unidad es la de la posición o una de sus superiores. Se comprueba en el repositorio, dentro de la transacción de escritura, contra el árbol vigente.
- **Rationale**: Los puestos no tienen nivel; el nivel lo da la unidad. Dentro de una misma unidad no se restringe quién supervisa a quién. El árbol de unidades ya se arma en memoria (sin consultas recursivas).
- **Alternatives**: Comparar solo el nivel del tipo de unidad (permite reportar a otra rama); añadir un nivel al puesto (cambia el modelo de datos).

## D-20 Unidad raíz con una sola posición; «sin supervisor ⇔ raíz»

- **Decision**: La raíz es la unidad sin `unidad_padre_id`. Al crear y editar, tras el primer enunciado de escritura: si la unidad es la raíz, el supervisor debe ser nulo y no puede haber otra posición vigente en ella (422 «La Gerencia General ya tiene su posición (máxima autoridad).»); si no lo es, el supervisor es obligatorio. El índice único parcial `posiciones_una_maxima_autoridad` (sin supervisor y no Suprimida) se conserva como respaldo atómico: como «sin supervisor ⇔ raíz», dos máximas autoridades siguen chocando. Suprimir la máxima autoridad libera la raíz.
- **Rationale**: SQLite no admite subconsultas en índices, así que la regla de la raíz la impone la aplicación y el índice cubre la carrera.
- **Alternatives**: Quitar el índice (no atómico); trigger SQL (convención del proyecto: sin lógica en la base).

## D-21 Edición: unidades mínimas y máxima autoridad

- **Decision**: Al actualizar, la unidad nueva debe ser la misma o superior a la de cada subordinada directa vigente (`unidades_minimas`); si no, 422 con el motivo y cuántas subordinadas lo impiden. Las Suprimidas no cuentan. En la máxima autoridad solo se admite cambiar la modalidad: una unidad o supervisor distintos de los actuales dan 422. Mover una posición no raíz a la raíz se rechaza porque la raíz ya está ocupada (o porque exigiría quedar sin supervisor).
- **Rationale**: Basta comprobar las subordinadas directas: si la nueva unidad es igual o superior a la de cada una y estas ya cumplían la regla con sus propias subordinadas, el árbol entero queda consistente.

## D-22 Opciones para los selectores

- **Decision**: `GET /api/posiciones/opciones` devuelve las unidades con `unidad_padre_id`, `superiores`, `habilitada` y `motivo_deshabilitada` (`inactiva` | `raiz_ocupada`), las posiciones candidatas con su unidad (id y nombre) y su ocupante, y con `excluir` también `unidades_minimas`. El frontend filtra y agrupa los candidatos (unidad elegida y sus superiores) y deshabilita unidades según `unidades_minimas`; el servidor valida de nuevo todo.
- **Rationale**: Un solo viaje por modal y sin consultar de nuevo al cambiar la unidad; la autoridad sigue siendo el backend.

## D-23 Búsqueda por ocupante

- **Decision**: El puerto `NombresDeUsuariosInterface` añade `idsPorNombre(string $texto): array`; el adaptador lo resuelve con un método nuevo de `SharedFacade` (`buscarIdsDeUsuariosPorNombre`). El repositorio filtra `posiciones.user_id` por esos ids. La búsqueda `q` cubre ahora código, puesto, unidad, código o puesto del supervisor y ocupante.
- **Rationale**: `Estructura` no puede leer `users`. `idsPorNombre` devuelve como máximo **200** ids, ordenados por nombre; si hay más coincidencias, la búsqueda por ocupante usa esos 200.
- **Alternatives**: `join` a `users` (viola la regla de módulos); guardar el nombre del ocupante (duplica datos).

## D-24 Ocupante en el listado

- **Decision**: Cada elemento del listado incluye `ocupante` (`{id, nombre}` o nulo). Los nombres se resuelven con una sola llamada por página al puerto.
- **Rationale**: Sin consultas por fila.

## D-25 Reubicación de unidades (FR-038)

- **Decision**: `EloquentUnidadRepository::actualizar` calcula el árbol resultante de la reubicación y rechaza (422 en `unidad_padre_id`) si alguna posición vigente cuya unidad, o la de su supervisora, esté en la rama movida deja de cumplir «la supervisora está en la misma unidad o en una superior». El mensaje indica cuántas posiciones lo impiden.
- **Rationale**: Es la única vía que rompe la regla después de crear la posición. Ambos módulos son del mismo módulo `Estructura`, así que el repositorio de unidades puede leer `posiciones`.
- **Alternatives**: Impedir toda reubicación (quita una función de 005); no validar (incumple FR-007 y SC-012).

## D-26 Badges y diseño

- **Decision**: Ocupada normal; Vacante con `--warning`/`--warningsoft`; Suprimida con `--danger`/`--dangersoft` (como el tablero «Posiciones» del insumo). `Badge` añade las variantes `warning` y `danger`. Se mantienen los tokens de `DESIGN.md` en lugar de los hex del tablero.
- **Rationale**: Una sola representación de los tokens (constitución III).

## Decisiones del insumo que no se adoptan (se mantienen D-02 a D-09)

Permisos `posiciones.*` (se mantiene la capacidad `admin`), «no se crean columnas nuevas» (la migración completa la tabla mínima), código «a partir del mayor existente con reintento» (se mantiene `POS-` + id) y `lockForUpdate`/`sharedLock` (SQLite: primer enunciado de escritura y sentencias condicionales).
