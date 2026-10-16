# Data Model: Catálogo de categorías documentales

Todo pertenece a `GestionPersonal`. Aislamiento por `tenant_id`. Sin eliminación (sin SoftDeletes, sin rutas DELETE, sin método de borrado en el repositorio).

## `categorias_documentales` (nueva)

| Columna | Tipo | Reglas |
|---|---|---|
| id | bigint PK autoincremental | |
| tenant_id | unsignedBigInteger NOT NULL | tenant del Administrador autenticado; nunca viene del cuerpo ni de filtros |
| nombre | varchar(100) NOT NULL | recortado, no vacío; se presenta tal como se escribió |
| nombre_clave | varchar(100) NOT NULL | NFC + recorte exterior + minúsculas; conserva tildes |
| nombre_busqueda | varchar(100) NOT NULL | minúsculas y sin tildes; solo para búsqueda parcial |
| estado | varchar(8) NOT NULL default `Activa` | `Activa` \| `Inactiva` (trigger/CHECK) |
| version | unsignedInteger NOT NULL default 1 | optimista |
| created_by / updated_by | unsignedBigInteger NOT NULL | actor |
| created_at / updated_at | timestamps | `updated_at` y `updated_by` solo cambian con cambios reales |

Restricciones e índices:
- `UNIQUE (tenant_id, nombre_clave)`, también para categorías inactivas.
- Índice `(tenant_id, estado, nombre_busqueda, id)` para listado, filtro y orden.
- Trigger SQLite (como en GP-03) que rechaza `estado` fuera de `Activa|Inactiva` en INSERT y UPDATE; `down()` los elimina.
- Sin FK hacia otras tablas ni columnas `padre_id`, `codigo` o «tipo».

Contrato hacia GP-05 (no se crea aquí): la tabla de documentos referenciará `categorias_documentales.id` con `restrictOnDelete`.

## Entidad de dominio

- `CategoriaDocumental` (inmutable): `id`, `tenantId`, `nombre`, `estado`, `version`, `createdBy`, `updatedBy`, `createdAt`, `updatedAt`.
- `EstadoDeCategoria` (enum de string): `Activa`, `Inactiva`, con `valores()`; la etiqueta de presentación vive en el frontend.

## Reglas (`ReglasDeCategoria`)

1. Recortar el nombre; vacío tras recortar = ausente; medir la longitud después del recorte.
2. Obligatorio; máximo 100 caracteres.
3. Estado válido en cambios de estado.
4. La unicidad no es una regla pura: la decide `UNIQUE (tenant_id, nombre_clave)`; el caso de uso hace una comprobación previa solo para dar un buen mensaje y traduce la violación de la restricción al mismo error.

## Transiciones y cambio efectivo

| Operación | Entrada | Efecto |
|---|---|---|
| Crear | `nombre` | Nace `Activa`, `version = 1`, auditoría de creación y última modificación iguales |
| Corregir nombre | `nombre`, `version` | Mismo nombre normalizado y mismo texto ⇒ 200 sin escribir; cambio de solo mayúsculas/espacios exteriores sobre la propia categoría ⇒ se acepta (no es duplicado de sí misma); cambio real ⇒ `version + 1` y auditoría |
| Cambiar estado | `estado`, `version` | Estado igual al actual con versión vigente ⇒ 200 sin escribir; cambio ⇒ `version + 1` y auditoría; no toca documentos |
| Confirmar activa (consumidor) | `categoriaId` | `UPDATE` condicional dentro de la transacción del consumidor; 0 filas ⇒ `CategoriaInactiva` |

`Activa → Inactiva` y `Inactiva → Activa` son las únicas transiciones; no existe estado final ni eliminación. Renombrar no modifica ningún documento: la referencia por id conserva la identidad.
