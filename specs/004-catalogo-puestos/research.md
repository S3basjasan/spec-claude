# Research: Catálogo maestro de puestos

Sin `NEEDS CLARIFICATION` abiertos. Decisiones a partir del insumo `plan-puestos.md`, la spec (con sus cuatro
clarificaciones), la constitución y el estado real del repositorio (000–003 implementados). Donde el insumo y la
realidad o la spec difieren, se indica cuál prevalece y por qué.

## D-01 Ubicación y rutas

- **Decision**: Backend en `backend/app/Modules/Estructura/` y frontend en `frontend/src/modules/estructura/puestos/`.
  Rutas `/api/puestos`; el recurso se identifica por el `id` del puesto (numérico).
- **Rationale**: Lo fija el insumo, igual que en 001–003. Las relaciones Eloquent son internas del módulo (un módulo, una
  base de datos); los casos de uso y el dominio no las ven: trabajan con entidades y el repositorio del módulo.
- **Alternatives considered**: `/api/estructura/puestos` (contradice el contrato indicado); identificar por código
  (`PU-014`), que obligaría a codificarlo en la URL y no aporta nada.

## D-02 El modelo de datos del insumo no existe en el repositorio

- **Hallazgo**: El insumo afirma que el modelo "ya existe en `estructura_empresarial.dbml`" y que no se crean tablas
  para `puestos`, `puesto_funciones`, `puesto_requisitos`, `puesto_competencias` ni `posiciones`. En el repositorio no hay
  ningún `.dbml` y las migraciones solo cubren usuarios, competencias, grados, historial de estado y los registros de
  comportamientos y preguntas. Tampoco existen `auditoria_cambios` ni tablas de roles y permisos.
- **Decision**: Crear las migraciones con los campos, longitudes y restricciones que el insumo sí detalla (ver
  [data-model.md](data-model.md)): `puestos`, `puesto_funciones`, `puesto_requisitos`, `puesto_competencias` y
  `auditoria_cambios`; y una `posiciones` **mínima** (`id`, `puesto_id`, `estado`, marcas de tiempo) como puente hasta
  que la feature 005 la defina y amplíe con migraciones propias.
- **Rationale**: Sin tablas no hay feature. El insumo describe cada columna con suficiente detalle para crearlas con
  fidelidad; lo que falta (columnas adicionales de `posiciones`) pertenece a 005 y no afecta a esta feature.
- **Alternatives considered**: esperar al DBML (bloquea la feature y el plan); sustituir `posiciones` por un contador
  simulado (no permite probar el rechazo ni la atomicidad con datos reales); crear la tabla `posiciones` completa
  adivinando columnas (invade el diseño de 005).
- **Riesgo registrado**: si el DBML real difiere de lo que el insumo detalla, habrá que alinear las migraciones. Se
  recomienda facilitar el DBML antes de `/speckit-implement`.

## D-03 Columnas adicionales en `puestos`

- **Decision**: Además de las columnas del insumo se añaden `nombre_clave` (único), `nombre_busqueda` (con índice) y
  `version`.
  - `nombre_clave`: nombre en minúsculas (Unicode) y recortado; su restricción única impone FR-006 (nombre único entre
    activos e inactivos, sin distinguir mayúsculas ni espacios alrededor) incluso con creaciones simultáneas, igual que
    en `competencias`.
  - `nombre_busqueda`: nombre sin tildes y en minúsculas; permite que "gestion" encuentre "Gestión" (FR-018). La búsqueda
    por código usa el propio `codigo`, comparado en minúsculas.
  - `version`: entero que empieza en 1 y sube con cada edición con cambios; base del control de edición simultánea
    (FR-026).
- **Rationale**: La spec exige tres comportamientos que ninguna columna del insumo soporta. `like` de SQLite solo ignora
  mayúsculas ASCII, no tildes; guardar el texto normalizado es la forma más simple de buscar y paginar en la base.
- **Alternatives considered**: normalizar al vuelo con una función SQLite personalizada (acopla la base al controlador
  PDO); filtrar en PHP sobre todo el catálogo (no pagina bien, pierde el total); comparar `updated_at` en lugar de
  `version` (la resolución de 1 s permite conflictos no detectados).

## D-04 Autorización: capacidad `admin`, no permisos nuevos

- **Hallazgo**: El insumo usa permisos `puestos.crear`, `puestos.editar` y `puestos.cambiar_estado` "asignados al rol del
  administrador" y un seeder de roles y permisos. No hay tablas ni servicio de permisos; el rol está en
  `users.role` (`admin`|`user`) y la autorización es la capacidad `admin` del token Sanctum (middleware `admin`).
- **Decision**: `POST`, `PUT`, `PATCH …/estado` y `GET …/competencias-disponibles` usan `auth:sanctum` + `admin`; `GET`
  (listado y detalle) solo `auth:sanctum`. No se crean permisos, tablas ni seeders de roles.
- **Rationale**: La constitución (Principio I) define el control de acceso administrativo así y 001–003 lo cumplen igual.
  Los tres permisos del insumo se asignan a un único rol, así que el comportamiento observable es idéntico.
- **Alternatives considered**: implantar permisos por acción (otra feature, con migraciones y un modelo de roles que la
  spec no pide).

## D-05 Competencia inactiva ya asignada: grado bloqueado

- **Hallazgo**: El insumo dice que la competencia inactiva "se conserva y puede cambiarse su grado". La clarificación Q2 de
  la spec dice lo contrario: solo se mantiene igual (grado bloqueado) o se quita.
- **Decision**: Prevalece la spec. Al actualizar, una competencia inactiva ya asignada solo se acepta con el **mismo
  grado** que tenía; con otro grado se rechaza con error en la fila (`competencias.N.grado_requerido`). Una competencia
  inactiva no asignada antes se rechaza (`competencias.N.competencia_id`).
- **Rationale**: Es una decisión explícita y posterior del usuario sobre la misma regla.
- **Alternatives considered**: el comportamiento del insumo (descartado por la clarificación).

## D-06 Reglas de competencias dependientes de datos

- **Decision**: `GuardarPuestoRequest` valida solo lo que no necesita datos (forma, longitudes, mínimos, máximos,
  repeticiones, tipos permitidos). Que cada competencia exista, esté activa (o ya asignada y con el mismo grado) y que
  el grado exista para esa competencia lo comprueba el caso de uso con `ReglasDeCompetenciasDePuesto` (clase de dominio
  pura) a partir de los datos que le entrega el repositorio. Si hay fallos lanza `CompetenciasDePuestoInvalidas` con un
  mensaje por fila, que el controlador convierte en 422 con claves `competencias.N.competencia_id` o
  `competencias.N.grado_requerido`.
- **Rationale**: Mantiene Clean Architecture (el dominio no conoce Eloquent) y permite pruebas unitarias sin base de
  datos. Los grados válidos salen de `competencia_grados` (validación en la aplicación; no hay clave foránea
  compuesta, como indica el insumo).
- **Alternatives considered**: reglas `exists`/`Rule::exists` en la request (acopla la validación a tablas y no puede
  expresar "ya asignada") ; comprobar solo en el repositorio (mezcla reglas de negocio con persistencia).

## D-07 Auditoría: por elemento, canónica y sin orden

- **Hallazgo**: El insumo guarda una fila por **campo** modificado y serializa las listas completas como texto. La spec
  (US5, FR-028) pide un registro por cada alta, baja o modificación de funciones, requisitos y competencias, y la
  clarificación Q4 excluye el orden de las funciones. El insumo también escribe auditoría al **crear**; la spec (revisada
  en este plan) ya lo recoge.
- **Decision**: Un comparador de dominio puro (`ComparadorDeCambiosDePuesto`) recibe el estado anterior (nulo al crear)
  y el nuevo como `InstantaneaDePuesto` (value object de dominio que construye el repositorio, con código y nombre de
  cada competencia leídos en vivo), y devuelve una lista de `CambioAuditado(campo, anterior, nuevo)`. El repositorio la guarda en
  `auditoria_cambios` (`entidad = 'puesto'`) en la misma transacción que el cambio.
  - **Datos del puesto**: una fila por campo cambiado (`codigo` al crear, `nombre`, `descripcion`, `mision`, `estado`).
  - **Listas**: una fila por elemento, con `campo` = `funciones`, `requisitos` o `competencias`.
    - Función: clave = descripción normalizada (recortada y en minúsculas); valor `«Tipo»: «descripción»`. Alta: anterior
      vacío. Baja: nuevo vacío. Modificación: mismo texto con otro tipo (anterior `Esencial: …`, nuevo
      `Complementaria: …`).
    - Requisito: clave = tipo + descripción normalizada; valor `«Tipo»: «descripción»`. Cualquier cambio es una baja y un
      alta.
    - Competencia: clave = `competencia_id`; valor `«código» «nombre»: grado «X»`. Modificación: otro grado.
  - **Orden de funciones**: la comparación ignora el orden; reordenar, o los cambios de numeración que se derivan de
    agregar o quitar, no generan filas.
  - Cambiar el texto de una función o de un requisito se registra como una baja y un alta (el texto es su identidad: no
    hay ids estables en el cuerpo).
  - **Creación**: todas las filas con anterior vacío (incluido el estado inicial `Activo` y el código).
  - **Estado**: una fila `estado` (`Activo` ↔ `Inactivo`) por cada cambio efectivo.
- **Rationale**: Cumple la spec con el modelo `campo/valor anterior/valor nuevo/usuario/fecha` del insumo. La clave
  natural por texto es posible porque FR-008 y FR-009 prohíben funciones y requisitos del mismo tipo repetidos.
- **Alternatives considered**: una fila por campo con la lista serializada (insumo; no identifica el elemento y el orden
  alteraría el valor); ids estables en el cuerpo para detectar ediciones de texto como "modificación" (complica el
  contrato sin pedirlo la spec).

## D-08 Código `PU-###` derivado del `id`

- **Decision**: Se inserta el puesto con `codigo` nulo y, en la misma transacción, se asigna `sprintf('PU-%03d', $id)`.
  La restricción única de `codigo` queda como red de seguridad. Pasado el 999 el número simplemente crece (`PU-1000`).
- **Rationale**: Es el patrón de `COMP-` (001): atómico, sin reintentos y sin reutilización (los puestos nunca se
  eliminan y las inserciones revertidas no consumen número en SQLite).
- **Alternatives considered**: el cálculo "máximo + 1 con reintento si falla la unicidad" del insumo (añade un bucle y
  una condición de carrera que el `id` evita).

## D-09 Desactivación atómica

- **Decision**: Desactivar es una sola sentencia condicional: `UPDATE puestos SET estado = 'Inactivo' WHERE id = ? AND
  estado = 'Activo' AND NOT EXISTS (posición Ocupada o Vacante del puesto)`. Si afecta una fila, se escribe la auditoría
  en la misma transacción. Si no afecta ninguna, se distingue: el puesto no existe (404), ya estaba Inactivo (200 sin
  cambios y sin auditoría) o tiene posiciones en uso (se cuentan Ocupadas más Vacantes y se responde 422 con la
  cantidad). Activar es otra sentencia condicional (`estado = 'Inactivo'`) sin restricciones.
- **Rationale**: Comprobar y cambiar en una única operación evita la carrera entre "no hay posiciones" y "se crea una
  posición" (borde del spec). La contraparte la cumple 005: crear una posición debe exigir en su propia operación que el
  puesto esté `Activo` (ver [contracts/estructura-facade.md](contracts/estructura-facade.md)).
- **Alternatives considered**: contar y luego actualizar en dos pasos (ventana de carrera); bloqueo a nivel de aplicación
  (innecesario en SQLite, cuya escritura es serial).

## D-10 Edición concurrente, estado y "sin cambios reales"

- **Decision**: `PUT` exige `version`; la actualización incrementa `version` con una condición `WHERE version = ?` y, si
  no afecta ninguna fila, responde 409. Cambiar el **estado** no toca `version`, `updated_by` ni `updated_at` (como en
  001): se audita en `auditoria_cambios`, y editar sigue siendo posible aunque otra persona cambie el estado.
  - Guardar sin cambios reales (mismos datos, mismas funciones en el mismo orden, mismos requisitos y mismas
    competencias con sus grados) no escribe nada: ni filas, ni `version`, ni auditoría.
  - Un cambio que solo reordena funciones **sí** es un cambio real: se reemplazan las funciones, sube `version` y se
    actualiza `updated_by`/`updated_at`, pero no se escribe auditoría (D-07).
- **Rationale**: FR-026 y FR-027, y la decisión sobre el orden registrada en la spec.
- **Alternatives considered**: subir `version` al cambiar el estado (haría fallar guardados legítimos por una acción que
  la spec dice que no los impide).

## D-11 Reemplazo de filas hijas

- **Decision**: En una edición con cambios se borran y reinsertan las filas de `puesto_funciones`,
  `puesto_requisitos` y `puesto_competencias` del puesto, dentro de la misma transacción, con `orden` desde 1 para las
  funciones. Los requisitos se devuelven ordenados por tipo (Formación, Experiencia, Capacitación) y luego por `id`; las
  competencias, por nombre.
- **Rationale**: Es lo que dice el insumo y evita choques con la restricción única `(puesto_id, orden)` al reordenar.
  Las tablas hijas no tienen otras referencias ni auditoría propia (la trazabilidad está en `puestos` y
  `auditoria_cambios`), así que sus `id` pueden cambiar. La prohibición de borrado se refiere a puestos, no a sus
  elementos.
- **Alternatives considered**: sincronizar fila por fila (exige ids estables en el cuerpo y desplaza `orden` en dos
  pasos).

## D-12 Listado ligero y detalle bajo demanda

- **Decision**: `GET /api/puestos` devuelve solo `id`, `codigo`, `nombre`, `descripcion` y `estado`. El detalle
  (`misión`, funciones, requisitos y competencias con datos vivos de la competencia) se pide con `GET /api/puestos/{id}`
  al desplegar una fila. La visibilidad por rol se decide en el borde con la capacidad `admin` del token (D-04): sin
  ella el listado es siempre `Activo` y el parámetro `estado` se ignora; el detalle sí devuelve puestos inactivos.
- **Rationale**: Igual que 001–003 y el insumo; evita cargar tres listas por cada fila de la página.
- **Alternatives considered**: incluir el detalle en cada elemento del listado (peso innecesario).

## D-13 Reglas de las listas

- **Decision**: Máximo 20 elementos por lista (funciones, requisitos, competencias), configurable (valor de
  configuración con 20 por defecto). Sin descripciones repetidas entre funciones ni entre requisitos del mismo tipo,
  comparadas sin distinguir mayúsculas ni espacios alrededor (Q3). Competencias sin `competencia_id` repetido.
  Descripciones de funciones y requisitos ≤ 500. `funciones` conserva el orden recibido (`orden` = posición + 1).
- **Rationale**: El máximo viene del insumo y la spec lo recoge; la comparación sin repetición reutiliza el patrón de
  003.
- **Alternatives considered**: sin máximo (riesgo de cargas enormes; el insumo lo acota).

## D-14 Facade de lectura para otros módulos

- **Decision**: Se amplía `EstructuraFacade` con `getPuesto`, `getPuestosPorIds`, `existePuesto`, `puestoEstaActivo` y
  `getPuestosActivos` (ver [contracts/estructura-facade.md](contracts/estructura-facade.md)). Solo lectura: devuelve
  arreglos, nunca modelos, y no expone la auditoría. El catálogo lee de `posiciones` únicamente para contar las que
  impiden desactivar.
- **Rationale**: FR-030, FR-031 y FR-032; mismo patrón que 001–003.
- **Alternatives considered**: que cada módulo lea las tablas (viola la constitución).

## D-15 Datos de ejemplo

- **Decision**: `PuestosSeeder` (depende de `CompetenciasSeeder`) crea 6 puestos inspirados en el tablero del diseño
  (Analista de datos, Jefe de talento humano, Desarrollador de software, Asistente contable, Ejecutivo de gestión comercial,
  Coordinador de operaciones), con al menos 2 inactivos, cada uno con funciones de ambos tipos, requisitos de Formación
  y Experiencia (y algunos de Capacitación) y competencias con grados válidos. Casos especiales: un puesto con una
  competencia inactiva asignada; un puesto activo con posiciones Ocupadas y Vacantes; uno con solo Suprimidas; uno sin
  posiciones. Escribe por Eloquent (no por el caso de uso) para poder sembrar la competencia inactiva.
- **Rationale**: Permite probar todos los escenarios de la spec sin preparar datos a mano, y respeta el criterio del
  insumo.
- **Alternatives considered**: sembrar con el caso de uso (rechazaría la competencia inactiva).

## D-16 Frontend: formulario y reordenación

- **Decision**: React Hook Form con Zod y `useFieldArray` para las tres listas. Cada fila de función o requisito es un
  `FilaTipoTexto` (selector de tipo y texto con contador). Las funciones añaden Subir y Bajar (`IconButton` con
  `aria-label` "Subir función N" / "Bajar función N", deshabilitados en los extremos) y un anuncio `aria-live` con la
  nueva posición. La fila de competencia muestra un `Combobox` (solo activas aún no elegidas), los datos de solo
  lectura (código, tipo y estado) y un `SelectField` de grados de la competencia elegida; si la competencia es inactiva,
  el grado aparece bloqueado y solo se ofrece Quitar. Los botones Agregar se deshabilitan al llegar a 20.
- **Rationale**: Reutiliza lo ya instalado y aprobado, sin librería de arrastre; reordenar con botones es accesible por
  teclado y lector de pantalla (WCAG 2.2 AA, incluido 2.5.7 alternativa al arrastre).
- **Alternatives considered**: arrastrar y soltar (requiere dependencia nueva y alternativa por teclado).

## D-17 Diseño

- **Hallazgo**: `design/Puestos.dc.html` (tablero "Puestos") cubre solo el listado: filas desplegables con código,
  nombre, descripción y estado, y un panel con misión, funciones, requisitos y competencias. No incluye filtro de
  estado, paginación, botones de gestión, diálogo, tipo de cada función, requisitos por tipo, indicador de competencia
  inactiva ni el formulario (el tablero "Formulario" es el de competencias). Además usa "Clasificación", "Conocimientos"
  y una competencia "Gerencial" que no existen en la spec ni en el modelo, y su menú trae Organigrama y Perfilamiento.
- **Decision**: Primera tarea de frontend: ampliar `design/` (nuevo tablero `PuestosFormulario.dc.html` + `DESIGN.md`
  §4, §5 y §9) antes de implementar, como exige la Constitución III. Se reutiliza el patrón de fila del tablero
  (`h2` con botón `aria-expanded` y panel `role="region"`).
- **Rationale**: La constitución obliga a actualizar primero el diseño ante cualquier desviación.
- **Alternatives considered**: implementar y documentar después (incumple la constitución).

## D-18 Estrategia de pruebas

- **Decision**: Pruebas Unit para `ReglasDeCompetenciasDePuesto` y `ComparadorDeCambiosDePuesto` (incluido: reordenar no
  audita, texto de función = baja + alta, creación con anterior vacío). Pruebas Feature de API (permisos, listado por
  rol, validaciones 422 por campo, 409, 404, nombre único con mayúsculas/espacios, guardar sin cambios, reordenar solo,
  competencia inactiva con grado bloqueado) y de repositorio (desactivación con Ocupadas/Vacantes/Suprimidas,
  atomicidad, código `PU-###`, auditoría). Vitest para `validation.ts`, `PuestoFila`/`PuestoDetalle`, fieldsets, y las
  dos páginas; E2E real de crear → ver detalle → editar → desactivar con rechazo → activar.
- **Rationale**: Constitución IV: el nivel de prueba más pequeño que demuestre cada comportamiento.
- **Alternatives considered**: cubrir todo con E2E (lento y poco preciso).
