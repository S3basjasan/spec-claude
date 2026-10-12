# Research: Diccionario de comportamientos

Sin `NEEDS CLARIFICATION` abiertos. Decisiones a partir del insumo del 2026-10-11, la spec (con sus cinco
clarificaciones), la constitución y el estado de 001-diccionario-competencias.

## D-01 Ubicación y rutas

- **Decision**: Backend en `backend/app/Modules/Estructura/` y frontend en
  `frontend/src/modules/estructura/comportamientos/`. Rutas `/api/comportamientos`; el recurso se identifica por
  `competencia_id` (hay un solo registro por competencia).
- **Rationale**: Lo fija el insumo. El módulo `Estructura` ya aloja el diccionario de competencias, y las relaciones
  Eloquent `Competencia → CompetenciaGrado → Comportamiento` son internas del módulo.
- **Alternatives considered**: `/api/estructura/comportamientos` (cumple la constitución al pie de la letra,
  contradice el contrato indicado); id propio del registro en las rutas (redundante con el 1↔1).

## D-02 Modelo de datos: filas más cabecera

- **Decision**: Se mantiene la tabla del insumo `competencia_comportamientos` (una fila por comportamiento: FK al
  grado sin cascada, `orden` 1–4, `descripcion` ≤ 300, `created_by`, `updated_by`, timestamps, única en
  `(competencia_grado_id, orden)`) y se **añade** `competencia_comportamiento_registros` (`competencia_id`
  único con FK sin cascada, `version`, `created_by`, `updated_by`, timestamps). No se tocan `competencias` ni
  `competencia_grados`. "Existe registro" ⇔ existe su fila de cabecera, y la invariante "cada grado tiene entre
  1 y 4 comportamientos" se garantiza en la misma transacción que la escribe.
- **Rationale**: La spec exige tres cosas que las filas solas no dan: auditoría por registro con "última
  modificación" aunque la edición solo quite comportamientos (FR-018); una columna de versión para rechazar el
  segundo guardado (FR-021); y una restricción de unicidad por competencia que frene dos creaciones simultáneas
  (SC-005). La cabecera es la fuente de verdad del registro; las columnas de auditoría de las filas se rellenan
  con el mismo actor, como pide el insumo.
- **Alternatives considered**: columnas en `competencias` o `competencia_grados` (prohibidas por el insumo);
  derivar auditoría y versión de las filas (pierde las ediciones que solo borran filas; la versión no se puede
  fijar sin una fila estable).

## D-03 El estado y los datos de la competencia no se almacenan

- **Decision**: Código, nombre, descripción, tipo y estado se obtienen con un `join` a `competencias` en cada
  lectura (listado, detalle, Facade). No se copian ni se sincronizan.
- **Rationale**: FR-008 y FR-009. Desactivar o reactivar la competencia (PATCH de 001) cambia el estado del
  registro de forma inmediata y sin trabajo extra; nombre y descripción actuales se ven siempre.
- **Alternatives considered**: copiar campos o escuchar eventos de 001 (duplica datos, riesgo de desfase).

## D-04 Visibilidad por rol (igual que 001)

- **Decision**: El borde de entrada resuelve el estado efectivo: sin capacidad `admin`, siempre `Activo` (el
  parámetro `estado` se ignora); con ella, el pedido y por defecto `Activo` (`Inactivo` o `Todos`). `GET
  /{competencia_id}` devuelve también los registros de competencias inactivas a cualquier persona autenticada.
- **Rationale**: FR-015, FR-020 y FR-023; autorización en el borde (constitución I); no se revela que existan
  inactivos.
- **Alternatives considered**: 403/422 al Usuario que envía `estado` (filtra información).

## D-05 Reglas de validación

- **Decision**: `comportamientos` es un objeto con exactamente las claves `A`, `B`, `C`, `D`; cada una un arreglo
  de 1 a 4 textos recortados, no vacíos, ≤ 300 caracteres y sin repetir dentro del grado (comparación sin
  distinguir mayúsculas). `competencia_id` en `POST` debe existir, estar `Activo` y no tener registro
  (cada causa con su mensaje). En `PUT` un `competencia_id` distinto del de la ruta es 422 y `version` es
  obligatorio. El orden de cada texto es su posición en el arreglo (1–4).
- **Rationale**: FR-006, FR-007, FR-005 y FR-021; el insumo define cuatro grados obligatorios con 1–4 textos.
- **Alternatives considered**: validar duplicados solo en el frontend (la API es el límite de confianza).

## D-06 Edición: reemplazo con detección de cambios

- **Decision**: `ActualizarComportamientos` compara los comportamientos normalizados (recortados) con los
  actuales. Si son idénticos devuelve 200 con el registro tal cual, **sin** tocar filas, cabecera, `version` ni
  auditoría (clarificación 2). Si hay cambios, y la `version` coincide (`UPDATE … WHERE competencia_id = ? AND
  version = ?`), actualiza por `orden` las filas existentes (conserva `created_by`), inserta las nuevas, elimina
  las sobrantes (solo contenido, nunca registros), incrementa `version` y fija `updated_by` en la cabecera, todo
  en una transacción. Versión distinta → 409. Permitido con la competencia inactiva.
- **Rationale**: FR-006, FR-018, FR-021 y el caso límite de guardar sin cambios.
- **Alternatives considered**: reescribir siempre (rompe "sin cambios reales"); borrar y reinsertar todo (pierde
  `created_by` de las filas).

## D-07 Creación y concurrencia

- **Decision**: `CrearComportamientos` valida en una transacción que la competencia sea `Activo` y no tenga
  cabecera, inserta la cabecera (única en `competencia_id`) y las filas. La violación del índice único se
  traduce a `CompetenciaYaTieneRegistro` (422); una competencia desactivada entre la elección y el guardado da
  `CompetenciaNoDisponible` (422).
- **Rationale**: Cumple el caso límite de dos administradores simultáneos y de competencia desactivada.
- **Alternatives considered**: bloqueo pesimista (innecesario con el índice único).

## D-08 Endpoint de competencias disponibles

- **Decision**: `GET /api/comportamientos/competencias-disponibles` (solo `admin`) devuelve las competencias
  activas sin cabecera, ordenadas por nombre (`whereDoesntHave`), con código, nombre, descripción y tipo; se
  declara antes de `/{competencia_id}` para que no lo capture el parámetro. El filtrado por texto del
  selector se hace en el cliente sobre esa lista.
- **Rationale**: El insumo y la clarificación 3; el volumen (cientos) no justifica un parámetro de búsqueda.
- **Alternatives considered**: `q` en el servidor (más viajes para una lista corta).

## D-09 Facade

- **Decision**: `EstructuraFacade` se amplía con `getComportamientos(int $competenciaId): ?array` (incluye
  inactivas, para históricos), `getComportamientosPorCompetencias(array $ids): array`,
  `tieneComportamientosActivos(int $competenciaId): bool` y `getComportamientosActivos(): array` (para nuevas
  asignaciones). Siempre arreglos, nunca modelos.
- **Rationale**: FR-019 y FR-020 con el mismo criterio que 001 (fuente única, solo activos en nuevas
  asignaciones, referencias históricas conservadas).
- **Alternatives considered**: que otros módulos consulten la API HTTP o las tablas (prohibido).

## D-10 Interfaz y diseño

- **Decision**: Patrones Listado, Formulario y Estados de `design/DESIGN.md`. Antes de implementar se registran
  en el diseño dos componentes que no existen: **Combobox** (búsqueda al escribir, lista de opciones
  navegable con teclado, `role="combobox"`/`listbox`) y **Lista de campos** (campos de texto repetibles con
  Agregar/Quitar, mínimo 1 y máximo 4, contador por campo); y el aviso de **competencia inactiva**
  (`Alert` neutro). Sin componentes nuevos de datos: el listado reutiliza `Badge`, `SearchField`,
  `SelectField` y `Pagination`.
- **Rationale**: Constitución III e insumo ("no crear componentes nuevos si existe uno equivalente").
- **Alternatives considered**: `datalist` nativo (se ve distinto en cada navegador y no se estiliza con los
  tokens); inputs repetidos a mano en el formulario.

## D-11 Pruebas

- **Decision**: Pest Feature por endpoint (éxito, 401, 403, 404, 409, 422, filtros, visibilidad por rol,
  estado derivado de la competencia, auditoría, sin cambios reales, creaciones simultáneas, ausencia de `DELETE`),
  Unit por caso de uso con repositorio en memoria y prueba de la Facade. Vitest + Testing Library para
  `Combobox`, `ListaDeCampos`, listado, formulario y validación. Playwright con los flujos del Administrador y
  del Usuario y el efecto de desactivar la competencia en 001.
- **Rationale**: Constitución IV y II.
