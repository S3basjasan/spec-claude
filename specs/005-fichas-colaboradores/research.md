# Research: Fichas de colaboradores (GP-01)

Sin `NEEDS CLARIFICATION` abiertos. Decisiones a partir del insumo «Plan de implementación · GP-01», la spec (con sus
cuatro clarificaciones), la constitución v1.0.0 y el estado real del repositorio (000–004 implementados). Donde el insumo
y la realidad o la spec difieren, se indica cuál prevalece y por qué.

## D-01 Ubicación, rutas y módulo

- **Decision**: Backend en `backend/app/Modules/GestionPersonal/`; frontend en
  `frontend/src/modules/gestionPersonal/fichas/`. Rutas `/api/colaboradores` y `/api/mi-ficha`; el recurso se identifica
  por el `id` numérico (global) de la ficha.
- **Rationale**: Lo fija el insumo. La constitución pide `modules/<modulo>/<funcionalidad>/` en frontend, por eso la
  subcarpeta `fichas` (el insumo proponía `modules/gestionPersonal` sin funcionalidad). Rutas fuera de
  `/api/<modulo>/`: mismo precedente aceptado en 000–004.
- **Alternatives considered**: `/api/gestion-personal/colaboradores` (contradice el contrato indicado).

## D-02 Almacenamiento: SQLite con `tenant_id`, no PostgreSQL por esquema

- **Hallazgo**: El insumo describe «PostgreSQL por tenant» (esquema por tenant, `bigint identity`, `COLLATE "C"`,
  `timestamptz`, `pg_trgm`). El repositorio usa SQLite (`DB_CONNECTION=sqlite`, constitución I) y no tiene tenants ni
  PostgreSQL. La spec (Assumptions) ya prevé que el mecanismo se decida aquí.
- **Decision**: Una sola base SQLite; cada ficha lleva `tenant_id`. Toda consulta del repositorio recibe el `tenant_id`
  como parámetro obligatorio (sin *global scope* implícito, para que olvidarlo sea un error de firma y no un filtro
  silencioso). Las unicidades son compuestas: `(tenant_id, codigo)`, `(tenant_id, tipo_identificacion,
  identificacion_clave)`, `(tenant_id, operacion_id)`. Equivalencias: `bigint identity` → `id()` autoincremental;
  `COLLATE "C"` → comparación binaria por defecto de SQLite sobre una clave ya en minúsculas; `timestamptz` →
  `timestamps()` (UTC, como 001–004); `pg_trgm` → no aplica.
- **Rationale**: Es lo único compatible con la constitución sin enmienda y con las features ya entregadas. El
  aislamiento observable (FR-001, SC-004) es el mismo.
- **Alternatives considered**: esquema por tenant en PostgreSQL (requiere enmienda MINOR de la constitución, servicio de
  conexión por tenant y migrar 000–004; otra feature); una base SQLite por tenant (migraciones y pruebas por archivo,
  sin beneficio sobre la columna). **Riesgo registrado**: si el destino productivo es PostgreSQL por esquema, `tenant_id`
  pasa a ser redundante pero inocuo; la migración sería un cambio de infraestructura, no de dominio.

## D-03 Introducción del tenant en `Shared` (clarificación Q1)

- **Decision**: Migración `tenants` (`id`, `nombre`, `zona_horaria`, marcas de tiempo) y `users.tenant_id` (no nulo, con
  tenant `1` por defecto para las cuentas existentes). El tenant se resuelve en el servidor a partir de la cuenta del
  token Sanctum: `SharedFacade::getContextoDeTenant(int $userId)` devuelve `{tenant_id, nombre, zona_horaria}`. La
  respuesta de sesión añade `tenant: {id, nombre}` (aditivo). Nunca se toma de la petición: un `tenant_id` en el cuerpo
  o la URL se rechaza con 422 `CAMPO_NO_PERMITIDO`.
- **Rationale**: «La sesión lleva el tenant» sin tocar la autenticación: el token ya identifica a la cuenta y la cuenta
  fija el tenant; no hay forma de que el cliente lo cambie. Cumple I (un módulo no lee tablas de otro).
- **Alternatives considered**: tenant dentro del token (habilidad `tenant:N`: cambia la emisión de tokens de 000);
  cabecera `X-Tenant` (manipulable, contradice FR-001).
- **Impacto**: pruebas de 000-login que comparan el JSON de usuario exacto deberán admitir `tenant`.

## D-04 Roles, asociación cuenta-ficha y permisos (clarificaciones Q2 y Q3)

- **Hallazgo**: No existen tablas de roles ni de permisos; el rol está en `users.role` y la autorización es la capacidad
  `admin` del token. El insumo usa `personal.ficha_propia.consultar`, que no existe.
- **Decision**: Administrador de Talento Humano = rol `admin` (middleware `admin`); Colaborador = rol `user`. La
  asociación y los tres permisos independientes (consulta de ficha, consulta documental, exportación) viven en
  `colaborador_cuentas` (`tenant_id`, `user_id` único, `colaborador_id` único, `puede_consultar_ficha`,
  `puede_consultar_documentos`, `puede_exportar`), de **solo lectura** para esta feature y cargada con datos iniciales.
  `personal.ficha_propia.consultar` ≡ `puede_consultar_ficha`. `GET /api/mi-ficha` evalúa, en orden: sin fila →
  403 `CUENTA_SIN_FICHA`; fila sin permiso → 403 `SIN_PERMISO_FICHA`; la ficha debe ser del mismo tenant que la cuenta.
- **Rationale**: Es exactamente lo que la spec delimita; sin pantalla de gestión. Los tres permisos se leen sin
  deducirse entre sí (FR-006).
- **Alternatives considered**: columnas en `users` (la asociación es del dominio de personal; mezclaría módulos); crear
  roles y permisos (feature de identidad y acceso).

## D-05 Normalización de identificación y de búsqueda

- **Decision**: Un único servicio de dominio `NormalizadorDeTexto` con dos funciones.
  - `claveDeIdentificacion`: `Normalizer::normalize(trim(x), NFC)` y `mb_strtolower`. Conserva ceros, separadores
    internos y tildes («0102-03» ≠ «010203»; «AB123» = «ab123»; el tipo forma parte de la clave).
  - `busquedaDeTexto`: `mb_strtolower(Str::ascii(trim(x)))`, igual que `PuestoModel::busquedaDeTexto` (004).
- **Rationale**: Reutiliza el criterio ya aceptado para búsqueda. `ext-intl` **no está cargada** en el PHP local, pero
  `symfony/polyfill-intl-normalizer` ya figura en `composer.lock` y define `Normalizer`; la primera prueba Unit debe
  confirmarlo con una cadena NFD («e» + U+0301) que iguale a su NFC. Si la clase no existiera en algún entorno, la
  prueba falla y se solicita aprobar el polyfill como dependencia directa (Constitución IV); no se degrada en silencio.
- **Alternatives considered**: no normalizar NFC (dos teclados producen claves distintas para la misma persona);
  `Str::ascii` también para la identificación (colapsa «ñ» con «n» y puede unir identidades distintas: prohibido por
  el insumo). Sin validadores nacionales de dígito verificador (FR-011).

## D-06 Código `EMP-###`

- **Decision**: En la misma transacción: insertar la ficha con un `codigo` temporal único (`TMP-<operacion_id>`), leer
  el `id` generado y actualizar `codigo = 'EMP-' . str_pad(id, 3, '0', STR_PAD_LEFT)`. El `id` es global y creciente
  (`AUTOINCREMENT`), por tanto los códigos de un tenant tienen saltos válidos (FR-007) y nunca se reutilizan; 1000 →
  `EMP-1000`.
- **Rationale**: Patrón ya usado para `COMP-` y `PU-`; atómico y sin carreras. La spec admite saltos.
- **Alternatives considered**: contador por tenant (tabla adicional y bloqueo; numeración densa que la spec no exige);
  `MAX+1` (carrera, prohibido por el insumo).
- **Prueba**: forzar `sqlite_sequence` a 998 para comprobar `EMP-999` → `EMP-1000`.

## D-07 Alta idempotente

- **Decision**: `POST` exige `Idempotency-Key` (UUID). Se guarda `operacion_id` y `operacion_hash` (SHA-256 del cuerpo
  canónico: campos editables ordenados y normalizados). Flujo: (1) transacción corta de lectura de
  `(tenant_id, operacion_id)`; si existe con mismo hash y mismo actor → 200 con la ficha (no consulta duplicidad); con
  otro hash o actor → 422 `CLAVE_DE_OPERACION_REUTILIZADA`; (2) si no existe, validar y comprobar duplicidad de
  identificación (mejora el mensaje); (3) insertar. Ante `UniqueConstraintViolation`, la transacción se cierra y en
  una **nueva** se resuelve: si ahora existe la operación → 200; si no, la colisión es de identificación → 422
  `IDENTIFICACION_DUPLICADA`. Nunca se reintenta con otra identidad.
- **Rationale**: Cubre doble clic, reenvío tras corte y envíos simultáneos (FR-014) y el edge case «segundo intento
  recibe el resultado del primero». El índice único es la garantía; la comprobación previa solo mejora el mensaje.
- **Alternatives considered**: solo unicidad de identificación (el segundo intento recibiría un duplicado aunque el
  primero sí guardó); `operacion_id` global (permite sondear claves de otro tenant).
- **Falta de cabecera**: 422 `CLAVE_DE_OPERACION_REQUERIDA`. Desde GP-07 la clave la aporta el llamador interno.

## D-08 Auditoría y concurrencia

- **Decision**: `created_by`/`updated_by` como enteros sin clave foránea a `users` (como 004). `version` entero desde 1.
  `PATCH` incluye `version`; la actualización es `UPDATE … WHERE id=? AND tenant_id=? AND version=?`; 0 filas →
  409 `FICHA_MODIFICADA`. Una versión obsoleta devuelve 409 aunque los datos coincidan. Si la versión coincide y no hay
  cambios reales, no se escribe nada (`updated_at`, `updated_by` y `version` intactos).
- **Rationale**: FR-019/FR-021 y la regla de 001 y 004. Sin historial campo a campo (fuera de alcance).
- **Alternatives considered**: `If-Match` con `updated_at` (resolución de 1 s).

## D-09 Lectores de capacidad (puesto, unidad, estado, trayectoria, secciones)

- **Hallazgo**: GP-02 (vínculos y hechos laborales) y las unidades de Estructura no existen; `EstructuraFacade` solo
  conoce puestos. La tabla `posiciones` mínima de 004 pertenece a Estructura y no se toca.
- **Decision**: Cuatro puertos en `Application/Ports/`: `LectorLaboralPort` (estado, posición vigente e ids con
  vínculo, en una fecha), `LectorEstructuraPort` (puesto y unidad por posición, unidades filtrables, ids de posición
  por texto de puesto), `LectorDeCapacidadesPort` (¿están instaladas Desarrollo, Documentos, Ingreso, Exportación?) y
  `ContextoDeTenantPort`. Cada lectura devuelve `Lectura` con tres resultados: `disponible(datos)`, `noInstalado` y
  `noDisponible` (excepción o tiempo de espera real). Los adaptadores actuales devuelven `noInstalado`; el módulo lo
  trata como ausencia confirmada: estado «Sin vínculo vigente», puesto y unidad «No registrado», trayectoria vacía.
  `noDisponible` se muestra «Información no disponible» sin ocultar los datos personales (FR-029, GP01-RN08).
- **Rationale**: Permite demostrar GP-01 sin GP-02/03/05/06 (SC-010) y distingue ausencia de caída (CA05). Cuando GP-02
  exista, solo se sustituye el adaptador.
- **Alternatives considered**: leer `posiciones` (viola I); estado editable en la ficha (duplica vínculos, prohibido
  por RC-11); datos simulados (inventa asignaciones, contradice FR-028).

## D-10 Listado, búsqueda y filtros

- **Decision**: Orden `apellidos_busqueda, nombres_busqueda, id` (el insumo pide «apellidos, nombres y código»; el
  código es monótono con el `id`, y el índice compuesto lo sirve). Predicado: `tenant_id = :t AND ( todos los tokens de q
  ∈ (nombres_busqueda || ' ' || apellidos_busqueda) OR lower(codigo) LIKE :q OR id IN (:fichas_por_puesto) ) AND
  estado AND unidad`. `q` se normaliza con `busquedaDeTexto`, se tokeniza por espacios y los comodines `%`/`_` se
  escapan. `fichas_por_puesto` proviene de `LectorEstructuraPort` → `LectorLaboralPort` (no instalado ⇒ conjunto vacío
  sin error). El filtro de estado usa `idsConVinculoVigente` del lector; «Sin vínculo vigente» = fichas del tenant sin
  vínculo. Si un lector necesario para un filtro activo está `noDisponible` → 503 `INFORMACION_NO_DISPONIBLE`
  (nunca un listado engañoso); sin filtro activo, las columnas puesto/unidad se marcan `no_disponible` y las fichas
  se listan. Puesto y unidad se enriquecen **por lote** sobre la página, no fila a fila.
- **Opciones de filtros**: `GET /api/colaboradores/filtros` devuelve «Todos» + los estados que alguna ficha del tenant
  tiene hoy (solo `SinVinculoVigente` mientras no exista GP-02) y las unidades que Estructura publique (vacío hoy).
  Un `unidad_id` con el lector no instalado → 422 en ese campo.
- **Rationale**: Cumple FR-023/FR-024 sin paginar en memoria ni relaciones cruzadas; la búsqueda contains recorre filas
  del tenant, aceptable a la escala prevista (miles); no se promete que el B-tree acelere `LIKE '%x%'`.
- **Alternatives considered**: FTS5 de SQLite (dependencia de compilación y otra tabla); filtrar en PHP tras cargar el
  tenant completo (pierde el total y no pagina).

## D-11 Contratos de respuesta del detalle

- **Decision**: El detalle devuelve la ficha, `laboral` (`estado`, `puesto`, `unidad`, cada una con su `disponibilidad`),
  `trayectoria` (lista por fecha efectiva descendente, con `programado`), `capacidades` (`desarrollo`, `documentos`,
  `registrar_ingreso`, `exportar`: booleanos ya combinados con instalación y permiso) y `acciones.editar`. Mi ficha usa
  la misma representación con `acciones.editar = false`. No hay campos de adecuación, potencial ni económicos (FR-031).
- **Rationale**: El frontend no decide permisos (la guarda es de presentación, I); «Reintentar» repite la petición.
- **Alternatives considered**: endpoints separados por sección (más viajes sin beneficio con una sola sección hoy).

## D-12 Fecha no futura según el tenant

- **Decision**: Puerto `ContextoDeTenantPort` entrega la zona horaria del tenant (`tenants.zona_horaria`, por defecto
  `America/Guayaquil`) y el caso de uso compara contra `now(zona)->toDateString()`. La fecha igual a hoy es válida.
- **Rationale**: Spec (Assumptions). El frontend valida con la fecha del navegador solo como ayuda; manda el servidor.
- **Alternatives considered**: `TenantClock` global (abstracción especulativa con un solo uso).

## D-13 Rollback y migración

- **Decision**: `hrms.gestion_personal.escritura_habilitada` (`.env`, por defecto `true`); en `false`, `RegistrarFicha`
  y `ActualizarFicha` responden 503 `ESCRITURA_DESHABILITADA` y las consultas siguen. `down()` de las migraciones
  elimina las tablas y se documenta como destructivo; el rollback operativo es la bandera. Las tablas son nuevas
  (no existían en el repositorio), por lo que no hay identidades previas que reportar; si una carga futura (GP-07
  histórica) encontrara duplicados, el índice único los rechaza sin fusionarlos.
- **Rationale**: Cumple el insumo sin una pantalla ni un panel de configuración.

## D-14 Frontend y diseño

- **Decision**: Ver «Diseño de frontend» del plan. `GP-01_Fichas-de-colaboradores.html` es referencia de
  comportamiento y estilo; los demás `GP-0x_*.html` untracked pertenecen a otras features. Como no son tableros
  `.dc.html` ni están en `canvas.json`, la primera tarea de frontend crea `ColaboradorFicha.dc.html` y actualiza
  `DESIGN.md` (Constitución III) antes de implementar.
- **Alternatives considered**: implementar directamente desde el HTML del prototipo (viola III y arrastra funciones de
  GP-02/03/05/06/07).
