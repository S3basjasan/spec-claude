# Research: Catálogo de categorías documentales

Sin marcadores `NEEDS CLARIFICATION`. Este documento fija las decisiones donde el insumo técnico y el repositorio no coinciden o dejan una elección abierta.

## Divergencias con el insumo

| Insumo | Realidad del repositorio / constitución | Decisión |
|---|---|---|
| PostgreSQL con esquema independiente por tenant | SQLite predeterminado; aislamiento por columna `tenant_id` (fichas, desarrollo) | Columna `tenant_id`; unicidad `(tenant_id, nombre_clave)`; el diseño evita funciones propias de un motor |
| `COLLATE "C"` en `nombre_clave` | En SQLite la comparación de texto es binaria por defecto | La clave ya se normaliza en la aplicación (NFC + recorte + minúsculas); la comparación binaria equivale a «C» |
| `backend/app/Modules/Personal`, `frontend/src/modules/personal` | Módulo real: `GestionPersonal` / `gestionPersonal` | Se usa la ubicación equivalente existente |
| Capacidad `personal.categorias.gestionar` | No existe un sistema de capacidades por permiso; la constitución exige Sanctum + capacidad `admin` | Middleware `admin`; el nombre del permiso queda como alias documental |
| IDs como cadenas decimales «cuando sea necesario» | Los contratos del módulo entregan enteros; SQLite/JS no pierden precisión con estos ids | Enteros, igual que GP-01..03 |
| 409 por «clave de operación reutilizada» | Este catálogo no usa idempotencia (no hay alta repetible con clave) | Solo 409 por versión obsoleta |
| FK de documentos a categoría con `restrictOnDelete` | La tabla de documentos no existe | La FK la crea la migración de GP-05; aquí solo se documenta como contrato |
| Prototipo con columna «Documentos» y botón «Editar nombre» | La spec prohíbe consultar documentos para el catálogo | Sin columna «Documentos»; botón «Editar»; diferencia registrada en `DESIGN.md` §9 |
| Spec: «sin paginación» (Assumptions) | El insumo pide `page`/`per_page` y el listado paginado | Se pagina (20 por defecto, máximo 100); la spec se actualiza |

## D1. Almacenamiento y unicidad

- **Decisión**: tabla `categorias_documentales` con `tenant_id`, `nombre`, `nombre_clave`, `nombre_busqueda`, `estado`, `version`, `created_by`, `updated_by` y timestamps. `UNIQUE (tenant_id, nombre_clave)` incluye las categorías inactivas.
- **Rationale**: la restricción de base de datos es la única garantía ante dos creaciones simultáneas; la validación previa solo mejora el mensaje. `nombre_clave` (NFC, recorte, minúsculas, con tildes) cumple «sin distinguir mayúsculas ni espacios exteriores»; `nombre_busqueda` (sin tildes) sirve a la búsqueda parcial sin alterar la unicidad.
- **Alternativas**: `UNIQUE` sobre `lower(nombre)` (dependiente del motor y de la colación); unicidad solo entre activas (permitiría reactivar duplicados, contradice la spec).

## D2. Normalización

- **Decisión**: añadir `claveDeNombre()` al `NormalizadorDeTexto` existente, con la misma regla de `claveDeIdentificacion` (NFC, `trim`, minúsculas) y reutilizar `busquedaDeTexto` para la búsqueda. La longitud de 100 se mide tras recortar.
- **Rationale**: evita duplicar la lógica de normalización; se nombra por su propósito.
- **Alternativas**: llamar a `claveDeIdentificacion` para nombres (nombre engañoso); clase nueva (duplicación).

## D3. Concurrencia y cambios sin efecto

- **Decisión**: `version` entera; `UPDATE … WHERE id, tenant_id, version` incrementando la versión y fijando `updated_by`/`updated_at`. Si afecta 0 filas se distingue inexistente (404) de obsoleta (409). Un guardado cuyo estado final coincide con el actual y trae la versión vigente devuelve 200 sin escribir (ni versión ni auditoría). Una versión obsoleta nunca se acepta aunque el resultado «parezca equivalente».
- **Rationale**: mismo patrón que `EloquentDesarrolloRepository`; coherente con FR-012 y FR-027.
- **Alternativas**: `lockForUpdate` en cada edición (innecesario con versión); ETag (cambia la forma de la API respecto al resto del módulo).

## D4. Coordinación con la carga documental

- **Contexto**: GP-05 sube el archivo a almacenamiento privado, no visible, y luego publica el documento en una transacción corta. Si la categoría se desactiva durante la transferencia, la publicación debe rechazarse (FR-020).
- **Decisión**: el puerto expone `confirmarCategoriaActiva(tenantId, categoriaId)`, que **debe llamarse dentro de la transacción de publicación** del consumidor y ejecuta `UPDATE categorias_documentales SET version = version WHERE id = ? AND tenant_id = ? AND estado = 'Activa'`. Una fila afectada ⇒ activa y bloqueada hasta el commit; cero ⇒ `CategoriaInactiva` (o inexistente). `CambiarEstadoCategoria` actualiza la misma fila con `UPDATE`, así que ambas operaciones se serializan en el motor: si desactivar confirma primero, la carga falla; si la carga confirma primero, el documento queda asociado y la desactivación procede después.
- **Rationale**: un `UPDATE` condicional toma el bloqueo de escritura en SQLite y en motores con bloqueo por fila, sin depender de `SELECT … FOR UPDATE` (que SQLite ignora). Nunca se mantiene durante la transferencia del archivo.
- **Alternativas**: `lockForUpdate` (sin efecto en SQLite); comprobar el estado solo al iniciar la carga (no cubre la carrera); bloquear la desactivación si hay cargas en curso (contradice «no consulta documentos»).

## D5. Independencia de la tabla documental

- **Decisión**: el catálogo no consulta ni referencia una tabla documental. Desactivar no cuenta documentos ni escribe en ellos. El nombre vigente lo resuelven los consumidores por id con `getCategoriasPorIds`.
- **Rationale**: permite usar el catálogo antes de GP-05 (FR-001, spec §6) y evita acoplamiento entre migraciones.
- **Alternativas**: columna calculada «documentos» (rompe la independencia y la regla de no consultar documentos).

## D6. Contexto del consumidor

- **Decisión**: dos contextos, dos reglas. El selector de cargas pide `estado = Activa` (`getCategoriasActivas`); la lista y detalle de documentos resuelven cualquier categoría por id (`getCategoriasPorIds`) y muestran la etiqueta vigente aunque esté inactiva. Si la lectura falla, el puerto devuelve `Lectura::noDisponible()`; la fachada lanza `InformacionNoDisponible` y el consumidor decide su degradación sin tocar asociaciones.
- **Rationale**: FR-018, FR-019, FR-023 y la sección «Frontend» del insumo.

## D7. Selector frontend de la carga

- **Decisión**: `categorias/index.ts` expone la clave de caché y `useCategoriasActivas()` (consulta con `estado=Activa`, `per_page=100`). GP-05 solo importa ese punto público. Tras cualquier mutación del catálogo se invalida la familia de claves, de modo que selectores abiertos se refrescan.
- **Límite**: si algún tenant superara 100 categorías activas, GP-05 debe usar el puerto del servidor; el catálogo se asume de decenas de elementos.

## D8. Auditoría

- **Decisión**: quién y cuándo crea y modifica, en la propia fila; `updated_*` solo cambian con cambios reales (nombre o estado). Sin tabla de historial ni pantalla de consulta (FR-030). La API entrega los ids de actor y los instantes; no se resuelven nombres de usuario para no acoplar con el módulo de identidad.

## D9. Datos de ejemplo

- **Decisión**: `PersonalSeeder` añade Contratos y Certificados (Activas) y una categoría Inactiva en el tenant 1, idempotente, solo en entornos de ejemplo/pruebas. Producción nace vacía.
