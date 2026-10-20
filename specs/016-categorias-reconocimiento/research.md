# Research: Categorías e indicadores cualitativos de reconocimiento

Decisiones de la fase 0. No quedan `NEEDS CLARIFICATION` abiertos; la exclusión del Candidato se resolvió acotando el alcance (D2).

## Divergencias con el insumo

El insumo `plan-categorias-reconocimiento-v2.md` se contrastó con el repositorio.

### D1. Permisos y roles

- **Hallazgo**: no existen tablas ni seeders de permisos de módulo, ni los roles Líder, Colaborador, Gerencia o Candidato. `users.role` solo vale `admin` o `user`, y el token Sanctum se emite con la capacidad `admin` o `user` (`SanctumEmisorToken`); los controladores usan `tokenCan('admin')` y el middleware `EnsureAdmin`.
- **Decisión**: sustituir los permisos `categorias_reconocimiento.{ver,crear,editar,cambiar_estado}` por:
  - `ver` (listar y consultar): cualquier usuario autenticado (`auth:sanctum`).
  - `crear`, `editar`, `cambiar_estado` y `competencias-disponibles`: capacidad `admin` (middleware `EnsureAdmin`).
  - «Con permiso editar» del insumo (estado inactivo en el listado, auditoría en el detalle) = `tokenCan('admin')`.
- **Racional**: es el único mecanismo real de autorización; patrón idéntico a `CompetenciaController`. La lógica queda concentrada en una política de acceso del módulo (`PoliticaDeAcceso`), de modo que un futuro sistema de permisos se cambie en un solo lugar.
- **Excepción a la constitución I**: los endpoints de lectura (`GET /categorias`, `GET /categorias/{categoria}`, `GET /competencias-filtro`) no son administrativos y quedan abiertos a cualquier usuario autenticado, igual que `CompetenciaController`. El usuario aprobó este alcance (solo roles `admin` y `user`) el 2026-10-16; toda la escritura exige `admin`.
- **Alternativas**: crear tablas de permisos y roles (alcance mayor, fuera de esta feature); aceptado solo si el usuario lo pide.

### D2. Alcance acotado a los roles existentes

- **Hallazgo**: la versión original del insumo y de la spec distinguía Líder, Colaborador, Gerencia y Candidato, pero ningún rol o capacidad actual los separa de un `user`.
- **Decisión (confirmada por el usuario)**: la feature se acota a los dos roles que existen: **Administrador** (`admin`) y **Usuario** (cualquier otro autenticado). La spec (FR-002 y escenarios) se actualizó en consecuencia; Líder, Colaborador, Gerencia y Candidato quedan fuera de alcance hasta que existan.
- **Consecuencia**: no hay brecha de seguridad abierta respecto a la spec. Cuando se incorporen los roles diferenciados, solo cambia `PoliticaDeAcceso.puedeVer` (punto de extensión) y se añade el caso de denegación del Candidato.

### D3. Facade de Estructura

- **Hallazgo**: `EstructuraFacade` ya ofrece `getCompetencia(id)`, `getCompetenciasActivas()`, `getTodasLasCompetencias()` (con `estado`), `existeCompetencia` y `estaActiva`. **No** ofrece «obtener ids por nombre».
- **Decisión**: no modificar Estructura. La búsqueda `q` por nombre de competencia se resuelve en el adaptador con `getTodasLasCompetencias()` (catálogo pequeño), normalizando nombre (minúsculas, sin tildes) y devolviendo los ids coincidentes; el repositorio los usa en `WHERE competencia_id IN (…)`. Sin JOIN ni lectura de tablas ajenas.
- **Alternativa**: agregar `getIdsPorNombre` a Estructura. Más limpio a gran escala, pero modifica otro módulo sin necesidad hoy.

### D4. Una sola organización

- Un único esquema SQLite y la constitución sin tenants: unicidad global de `codigo` y `nombre_normalizado`. La spec se alineó (FR-003, SC-004 y supuestos): la instalación equivale a una única organización y no hay segmentación multiorganización. Desviación respecto del Plan DTIC multi-tenant, registrada aquí.

### D5. Competencias: estados

- `competencias.estado` vale `Activo|Inactivo`. `competencia_activa` y `seleccionable` se calculan al consultar con `getCompetenciasPorIds`/`getCompetencia`, sin persistirse (FR-020).

## Decisiones técnicas

### D6. Concurrencia optimista

- **Decisión**: `updated_at` con precisión de microsegundos (`datetime(6)` / formato `Y-m-d H:i:s.u`) como token; el servidor serializa ISO 8601 con microsegundos. PUT y PATCH ejecutan `UPDATE … WHERE id = ? AND updated_at = ?`; si afecta 0 filas → 409 con el mensaje de la spec. PATCH al estado actual con token vigente → 200 sin escribir (FR-034). Guardado sin cambios reales → 200 sin tocar `updated_at` ni auditoría.
- **Racional**: evita lecturas previas y condiciones de carrera entre la comprobación y la escritura. Alternativa (columna `version` entera): más robusta ante relojes, pero el insumo y la spec fijan `updated_at`.
- **Riesgo**: SQLite almacena texto; la comparación exacta exige el mismo formato de serialización de ida y vuelta (se cubre con una prueba).

### D7. Código CAT-###

- Se calcula dentro de la transacción de creación como máximo numérico actual + 1, con relleno a 3 dígitos; `codigo` es único. Ante violación de unicidad por concurrencia se reintenta hasta 3 veces y luego se responde error. Como no hay borrado de categorías, la secuencia no deja huecos reutilizables.

### D8. Diseño frente a la spec

- Presentes los 27 archivos (`CategoriasReconocimiento.dc.html` + 26 `V_cr-*.dc.html`); `design/` no se modificó.
- Contraste campo por campo (T056). Coinciden con la spec: columnas del listado, filtros (competencia para todos; estado solo Administrador, por defecto Activos), búsqueda de hasta 100 caracteres, estados cargando/vacío/sin resultados/error con «Reintentar», «Competencia inactiva», formulario en modal con contadores `n/100` y `n/500`, indicadores de 1 a 5 con «Agregar indicador» y «Eliminar», mensaje de conflicto, diálogos de descartar y de desactivar, avisos de éxito y la nota sobre la puntuación.
- **No existe contador `n/250` para los indicadores** en el diseño: queda resuelto; la interfaz no lo muestra y el error por campo indica el rango de 5 a 250.
- Diferencias registradas (la spec prevalece):
  1. El diseño ofrece las competencias inactivas «(inactiva)» en el filtro a todos los roles; la spec (clarificación del 2026-10-16) limita eso al Administrador y los demás ven solo las activas.
  2. El diseño incluye la pantalla «Sin permiso» del Candidato; ese rol no existe todavía (D2) y no se implementa.
  3. El prototipo usa `Activo`/`Inactivo` en el filtro de estado con «Todos» primero; se conserva ese orden.
  4. «Auditoría» muestra `Usuario <id>` porque el sistema aún no expone nombres de usuario a este módulo.
- Los íconos viven en el objeto de valor `Icono` y en `iconos.ts`; registrarlos en `design/DESIGN.md` queda como tarea aparte sujeta a aprobación.

### D9. Reemplazo de indicadores

- Se eliminan y reinsertan en la misma transacción. Es la única eliminación física de la feature y no afecta a categorías. Ningún otro módulo referencia indicadores por id. Unicidad `(categoria, orden)` y `(categoria, texto_normalizado)` como red de seguridad.

### D10. Auditoría (tabla propia)

- **Hallazgo**: `auditoria_cambios`, su migración y `AuditoriaCambioModel` pertenecen al módulo Estructura y solo Estructura escribe en ella; la constitución I prohíbe que un módulo acceda a clases internas o tablas de otro.
- **Decisión (confirmada por el usuario)**: Compensaciones tiene su propia tabla `auditoria_categorias_reconocimiento` (solo inserción): `id`, `categoria_reconocimiento_id` (FK, `restrictOnDelete`, índice), `campo` string(30), `valor_anterior` text null, `valor_nuevo` text null, `user_id` unsignedBigInteger null sin FK, `created_at`. Una fila por campo realmente modificado; al crear, campos iniciales con `valor_anterior` nulo; indicadores como JSON de la lista de textos en orden; competencia como su id. Misma transacción que el cambio.
- **Alternativas descartadas**: reutilizar `auditoria_cambios` (viola la constitución) o crear un servicio compartido en `Shared` (alcance mayor).

### D11. Pendientes de la spec

- Se mantienen los supuestos de la spec: puntuación congelada en el hito (responsabilidad del módulo de hitos), desafíos en curso continúan al desactivar (confirmar con la feature de desafíos), sin filtro por tipo de competencia y competencia compartible.

### D12. Opciones del filtro de competencia

- **Hallazgo**: `competencias-disponibles` es solo `admin` y devuelve solo activas; el filtro del listado es para todos y el Administrador necesita ver también las inactivas.
- **Decisión**: endpoint de lectura `GET /api/compensaciones/competencias-filtro` (`auth:sanctum`): `admin` recibe activas e inactivas con `estado`; el resto, solo activas. Se resuelve con `CompetenciasPort` (`getTodasLasCompetencias` / `getCompetenciasActivas`).
