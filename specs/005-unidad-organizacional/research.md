# Research: Unidades organizacionales

Sin `NEEDS CLARIFICATION` abiertos. Revisión del 2026-10-15: decisiones a partir de la spec actualizada, el insumo `plan-unidades.md`, el modelo `estructura_empresarial.dbml`, la constitución y el estado real del repositorio (000–004 implementados y la versión anterior de 005 en el código). Donde el insumo y la realidad o la spec difieren, se indica cuál prevalece.

## D-01 Ubicación, rutas y una sola pantalla

- **Decision**: Backend en `backend/app/Modules/Estructura/`; frontend en `frontend/src/modules/estructura/unidades/`. Rutas `/api/unidades` y `/api/tipos-unidad`. La interfaz tiene **una sola ruta**, `/unidades`: crear, editar, desactivar y gestionar tipos son **modales** sobre el listado.
- **Rationale**: Lo fija el insumo (igual que en 001–004). Sin rutas de formulario, `RequireAdmin` protege botones y modales; el backend es la autoridad.
- **Alternatives considered**: `/api/estructura/…` (contradice el contrato); formulario en línea (versión anterior, ya no coincide con la spec ni con el diseño); páginas `/nueva` y `/editar`.

## D-02 Estado del modelo de datos frente al DBML

- **Hallazgo**: El DBML define `unidades_organizacionales`, `tipos_unidad` (con `nivel` único, `created_by`, `updated_by`), `posiciones` completa (`unidad_organizacional_id` obligatoria), `auditoria_cambios` y las tablas `roles`, `permisos`, `rol_permisos` con `users.rol_id`. En el repositorio existen `unidades_organizacionales` (con `version`), `tipos_unidad` solo con `nombre`, `posiciones` mínima (más la columna nullable añadida por 005), `auditoria_cambios` y `users.role` (texto). **No existen** las tablas de roles y permisos.
- **Decision**: Editar la migración de `tipos_unidad` para añadir `nivel` (único), `created_by` y `updated_by` (la feature no se ha publicado; `migrate:fresh --seed`). Conservar `unidades_organizacionales` y la columna nullable de `posiciones` (la tabla de 004 ya tiene filas sin unidad). No crear roles ni permisos (D-04). `auditoria_cambios` se reutiliza con `entidad = 'unidad'` y `'tipo_unidad'`.
- **Rationale**: Sin `nivel` no hay cadena. Una nueva migración de alteración obligaría a lidiar con la restricción única en una tabla con datos, sin ganar nada.
- **Alternatives considered**: migración de alteración (más ruido sin beneficio); crear las tablas de roles (otra feature).

## D-03 Columnas e índices en `unidades_organizacionales` (sin cambios)

- **Decision**: Se conservan: `codigo` anulable único, `version` (default 1), índices en `tipo_unidad_id`, `unidad_padre_id` y `estado`, FK `restrictOnDelete`, y el índice único **parcial** `unidades_una_raiz_activa` como red de seguridad de la raíz única (la API ya no crea ni desactiva raíces).
- **Rationale**: `version` implementa FR-048; el índice parcial protege la invariante ante escrituras directas.

## D-04 Autorización: capacidad `admin`, no permisos nuevos

- **Decision**: Escrituras, `opciones` e `impacto-desactivacion`: `auth:sanctum` + `admin`. Listar, consultar y ver la cadena: `auth:sanctum`. Los cuatro permisos del insumo (`unidades.crear`, `.editar`, `.cambiar_estado`, `.gestionar_tipos`) se asignan a un único rol, así que equivalen a `admin`.
- **Rationale**: No hay tablas ni servicio de permisos; la constitución define la autorización administrativa con Sanctum + `admin`, como en 001–004. Los seeders de este módulo no dependen de roles ni permisos.
- **Alternatives considered**: Implantar roles y permisos por acción (otra feature; la spec no la pide).

## D-05 Código `UO-###` derivado del `id`

- **Decision**: Se inserta la unidad con `codigo` nulo y, en la misma transacción, se asigna `sprintf('UO-%03d', id)`. Sin reutilización ni reintento.
- **Alternatives considered**: "máximo + 1 con reintento" (insumo): más carrera y más código.

## D-06 Escrituras atómicas en SQLite: primero se toma el candado de escritura

- **Decision**: Toda escritura que depende del estado vigente se hace en una transacción cuyo **primer** enunciado es una escritura; después se valida contra el estado vigente y se lanza la excepción de dominio si no cumple (se revierte todo). `lockForUpdate`/`sharedLock` (insumo) no protegen en SQLite.
  - **Crear**: inserta la fila y luego valida la padre (existe, activa, tipo que admite hijas) y calcula el tipo.
  - **Actualizar**: sin cambios reales no escribe; si hay, el primer enunciado sube `version` (0 filas → 409) y luego valida la padre.
  - **Cascada**: ver D-09. **Cadena de tipos**: ver D-11 y D-12.
- **Nota**: si el almacenamiento dejara de ser SQLite, se revisa (bloqueo de filas del servidor).

## D-07 El árbol se arma en la aplicación con un servicio de dominio puro

- **Decision**: `ArbolDeUnidades` (sin Laravel) arma el árbol a partir de id y padre, y devuelve: orden jerárquico (hermanas por la parte numérica del código), `nivel_arbol`, `descendientesDe(id)`, **`rama(id)`** (la unidad y sus dependientes activas) y **`ancestrasDe(ids)`**. El listado calcula orden y nivel sobre el árbol completo **antes** de filtrar y, con `q` o con un estado distinto de Activa, añade las ancestras de las coincidencias para que el cliente pueda armar el árbol.
- **Rationale**: Pocas unidades (≤ 500): una consulta de unidades y otra de posiciones, sin consultas recursivas ni por fila.

## D-08 Reglas de tipo y de unidad padre

- **Decision**: 
  - **Tipo**: lo calcula el backend al crear: el tipo con `nivel = nivel(padre) + 1`. Nunca viene del cliente (422 si llega) ni cambia al editar.
  - **Crear**: padre obligatoria, existente, activa y de un tipo que no sea el del último nivel ("Este tipo no admite subunidades.").
  - **Actualizar**: la raíz sigue sin padre y solo se renombra. En las demás, si la padre cambia: existente, activa, de tipo del nivel inmediatamente superior al de la unidad, distinta de la propia unidad y de sus dependientes (se recorre la cadena de padres). Si no cambia se conserva aunque no siga la cadena o esté inactiva.
  - **Raíz**: una sola, sembrada, de nivel 1 y siempre activa; la API no crea otra ni la desactiva ni la reubica.
  - **`sigue_cadena`** (calculado): `nivel(tipo de la unidad) = nivel(tipo de la padre) + 1`. **`admite_hijas`**: el tipo no es del último nivel.
- **Rationale**: FR-018–FR-020 y FR-031; con la regla de nivel, los ciclos son imposibles, pero se conserva la comprobación como invariante.

## D-09 Desactivación en cascada

- **Decision**: Una transacción:
  1. Primer enunciado: `UPDATE` condicional de la unidad objetivo (`Activa` → `Inactiva`, `fecha_supresion` de hoy). 0 filas y ya está inactiva → 200 sin cambios; la raíz se rechaza antes.
  2. Con el candado tomado, se calcula la **rama** (dependientes activas, directas e indirectas) sobre el estado vigente. Como una unidad nueva bajo una padre inactiva se rechaza, ninguna dependiente puede aparecer después.
  3. Se cuentan las posiciones `Ocupada`/`Vacante` de toda la rama (objetivo incluido). Si hay, se lanza `DesactivacionBloqueada` (unidades y cantidades) y **todo se revierte**.
  4. Si procede, `UPDATE` de las dependientes activas y una fila de auditoría de `estado` y `fecha_supresion` por unidad. No se tocan `version`, `updated_by` ni `updated_at`.
- **Reactivar**: una sentencia condicional con padre activa; solo esa unidad; sin cascada.
- **Impacto** (`GET …/impacto-desactivacion`): lectura previa para el diálogo; la decisión final siempre la toma el `PATCH`.
- **Alternatives considered**: calcular la rama antes de la transacción (ventana de carrera con una creación simultánea); `lockForUpdate` sobre la rama (SQLite lo ignora).

## D-10 Historial con comparadores de dominio puros

- **Decision**: `ComparadorDeCambiosDeUnidad` (existente) y `ComparadorDeCambiosDeTipo` (nuevo) devuelven las filas a escribir: una por campo cambiado; en la creación, una por dato inicial con valor y anterior nulo. Referencias como id; fechas `YYYY-MM-DD`. Reubicar registra solo la unidad movida; la cascada, una pareja de filas por unidad; el reordenamiento, una fila por cada tipo y campo modificado. `auditoria_cambios.entidad` tiene 30 caracteres: caben `unidad` y `tipo_unidad`.

## D-11 Cadena de tipos y reordenamiento

- **Decision**:
  - `CadenaDeTipos` (dominio puro) calcula la cadena resultante de un borrador, los tipos que cambian y las **unidades en conflicto** (nivel resultante de la padre ≥ al de la unidad), con sus tipos y niveles nuevos y los de su padre.
  - **Crear tipo**: nivel = máximo + 1, nombre normalizado único (`NormalizadorDeTexto`, sin mayúsculas ni tildes).
  - **Guardar cadena** (`PUT`): lista completa con id y nombre. Valida ids exactos, raíz primera, nombres únicos (errores por índice), token y conflictos. Aplica en una transacción en **dos pasos** (`nivel → −nivel → nivel final`) porque `nivel` es único y se valida fila por fila (también en SQLite). Sin cambios: 200 sin escribir. Los saltos de nivel (padre de nivel menor pero no inmediato) se permiten y se marcan con `sigue_cadena = false`.
- **Rationale**: Es el contrato del insumo; la cadena es pequeña, así que se arma completa en memoria.

## D-12 Control de edición simultánea de la cadena: token

- **Decision**: `GET /api/tipos-unidad` devuelve `version`, el SHA-1 de `id:nombre:nivel` de los tipos ordenados por nivel; `PUT` lo recibe y responde 409 ("La cadena de tipos cambió desde que la abriste. Recarga para ver la versión actual.") si ya no coincide. Se comprueba dentro de la transacción, después del primer enunciado de escritura.
- **Alternatives considered**: una `version` por tipo (no detecta altas ni reordenamientos ajenos); una tabla de versión global (migración extra sin necesidad).

## D-13 Visibilidad por rol y datos de auditoría

- **Decision**: El Usuario solo recibe unidades activas (ignora `estado`) y nunca la auditoría; el Administrador recibe `auditoria` en el detalle, con los nombres de usuario obtenidos por el puerto `NombresDeUsuariosInterface` (adaptador sobre `SharedFacade::getNombresDeUsuarios`, solo lectura). La cadena de tipos es visible para todos.
- **Rationale**: La clarificación Q3 de la spec prevalece sobre el insumo (que daba la auditoría a cualquier autenticado).

## D-14 Endpoint de opciones

- **Decision**: `GET /api/unidades/opciones` (admin) devuelve la cadena de tipos y las unidades en orden de árbol con `habilitada` (activa y que admite hijas). Con `reubicar=id`, solo las activas del tipo inmediatamente superior al de esa unidad, sin la propia ni sus dependientes. Sustituye al antiguo parámetro `excluir` y a `hay_otra_raiz_activa` (ya no existe la opción de crear raíces).

## D-15 Contrato con la feature de posiciones

- **Decision**: Esta feature solo **lee** `posiciones` (estados exactos `Ocupada` | `Vacante` | `Suprimida`). La feature de posiciones debe asignar solo a unidades activas, en la misma operación, y volver obligatoria `unidad_organizacional_id`. Mientras no exista, las cantidades son 0 salvo las posiciones sembradas.

## D-16 Facade

- **Decision**: Se conservan los métodos de unidades y se añade la lectura de la cadena de tipos (`getCadenaDeTipos()`, `getTipoDeUnidad(int $id)`). Solo arreglos; nunca modelos. Ver [contracts/estructura-facade.md](contracts/estructura-facade.md).

## D-17 Interfaz y diseño

- **Decision**: Árbol como tabla con sangría de 24 px por nivel, botón de expandir con `aria-expanded` y prefijo oculto "Nivel N"; el cliente oculta las filas cuyas ancestras están contraídas. Expansión en el cliente: al entrar solo la raíz; con búsqueda o filtro de estado distinto de Activa todo expandido y controles deshabilitados; al limpiar vuelve la expansión previa; al crear se expanden el padre y sus ancestras. Un componente compartido nuevo, `Modal` (foco atrapado, Escape, foco devuelto), reutilizado por los cinco modales. Arrastre con HTML5 drag and drop; subir y bajar son la alternativa de teclado (WCAG 2.5.7). "Ver en el árbol" deja `estado=Todas` y `q=código`. `design/DESIGN.md` y `design/Unidades.dc.html` se actualizan antes del frontend.

## D-18 Datos iniciales

- **Decision**: `TiposUnidadSeeder`: Gerencia General (1), Gerencia (2), Área (3), Departamento (4), Sección (5). `UnidadesSeeder`: 12 unidades en el orden Gerencia General (UO-001), Gerencia de Talento Humano, Gerencia de Tecnología, Selección y Desarrollo, Infraestructura, Desarrollo de Software, Reclutamiento, Capacitación (UO-008, inactiva con `fecha_supresion`), Soporte, Aplicaciones, Selección Técnica, Desarrollo Web (UO-012). Posiciones: Ocupada en UO-012; 1 Ocupada y 1 Vacante en UO-002; 2 Suprimidas en UO-006 y 1 en UO-008; ninguna en UO-005, UO-009 ni UO-011 (rama para la cascada: UO-005 con UO-009; hoja libre: UO-011).

## D-19 Pruebas

- **Decision**: Unit (árbol, rama, cadena, comparadores, casos de uso con dobles en memoria); Feature (API de unidades y tipos, repositorios, atomicidad de la cascada y de la cadena, concurrencia, auditoría, volumen de 500 unidades); Vitest (árbol, expansión, modales, borrador de tipos); Playwright (consulta, creación, edición, cascada, tipos, accesibilidad y escala).
