# Data Model: Gestión de documentos del colaborador

Todo pertenece a `GestionPersonal`. Aislamiento por `tenant_id`. Sin eliminación (sin SoftDeletes, sin rutas DELETE, sin método de borrado en el repositorio; las FK usan `restrictOnDelete`).

## `documentos_colaborador` (nueva)

| Columna | Tipo | Reglas |
|---|---|---|
| id | bigint PK autoincremental | |
| tenant_id | unsignedBigInteger NOT NULL | tenant del actor; nunca viene del cliente |
| colaborador_id | FK `colaboradores.id`, restrict, NOT NULL | inmutable |
| categoria_id | FK `categorias_documentales.id`, restrict, NOT NULL | inmutable; el nombre vigente se lee del catálogo, incluso inactiva |
| titulo | varchar(200) NOT NULL | recortado, no vacío; admite repetidos |
| object_key | varchar(512) NOT NULL UNIQUE | clave interna opaca `personal/{tenant_id}/colaboradores/{colaborador_id}/{uuid}.pdf`; nunca sale en una respuesta |
| mime_type | varchar(64) NOT NULL | `application/pdf` |
| size_bytes | unsignedBigInteger NOT NULL | `> 0` y `<= 10000000` (trigger) |
| sha256 | char(64) NOT NULL | huella de los bytes recibidos |
| visible_colaborador | boolean NOT NULL default false | |
| estado | varchar(10) NOT NULL default `Disponible` | `Disponible` \| `Archivado` (trigger) |
| version | unsignedInteger NOT NULL default 1 | optimista |
| created_by / updated_by | unsignedBigInteger NOT NULL | actor |
| archived_by / archived_at | unsignedBigInteger / timestamp NULL | completos si y solo si `estado = 'Archivado'` (trigger) |
| operacion_id | char(36) NOT NULL | `Idempotency-Key` de la carga; `UNIQUE (tenant_id, operacion_id)` |
| operacion_hash | char(64) NOT NULL | SHA-256 de ficha, actor, título normalizado, categoría, visibilidad y `sha256` de los bytes |
| created_at / updated_at | timestamps | `updated_at` solo cambia con cambios reales |

Índices: `(tenant_id, colaborador_id, estado, created_at, id)` (listado por ficha y estado, más reciente primero); `categoria_id`; `UNIQUE (tenant_id, operacion_id)`; `UNIQUE (object_key)`.

Triggers SQLite BEFORE INSERT/UPDATE (como en GP-03 y 013): `estado` en el conjunto; `size_bytes` entre 1 y 10 000 000; `Archivado` ⇒ `archived_by` y `archived_at` no nulos; `Disponible` ⇒ ambos nulos. `down()` elimina triggers y tabla.

No existen: `tipo_documento`, carpetas, `revision`, `deleted_at`, nombre original del archivo ni estado «Subiendo». Un objeto sin fila no es un documento.

## Entidad de dominio

- `DocumentoColaborador` (inmutable): `id`, `tenantId`, `colaboradorId`, `categoriaId`, `titulo`, `objectKey` (solo dentro del módulo), `mimeType`, `sizeBytes`, `sha256`, `visibleColaborador`, `estado`, `version`, `createdBy`, `updatedBy`, `archivedBy`, `archivedAt`, `createdAt`, `updatedAt`.
- `EstadoDeDocumento` (enum de string): `Disponible`, `Archivado`.

## Reglas (`ReglasDeDocumento`)

1. Título: recortado, obligatorio, máximo 200 caracteres medidos tras recortar.
2. Categoría: obligatoria, entero positivo; debe estar activa al publicar (se comprueba en la transacción con bloqueo).
3. Archivo: obligatorio, no vacío, `<= 10 000 000` bytes, PDF válido por contenido.
4. `visible_colaborador`: booleano (por defecto falso).
5. Acceso del Colaborador: pertenece a su ficha, permiso documental vigente, `visible_colaborador = true` y `estado = 'Disponible'`, comprobado en cada petición.

## Transiciones

| Operación | Entrada | Efecto |
|---|---|---|
| Cargar | título, categoría, archivo, visibilidad, `Idempotency-Key` | Publica `Disponible` con `version` 1; repetición válida ⇒ el mismo documento; misma clave con otro contenido ⇒ conflicto |
| Cambiar visibilidad | `visible_colaborador`, `version` | Solo `Disponible`; mismo valor ⇒ sin escritura; cambio ⇒ `version + 1` y `updated_by/at` |
| Archivar | `version` | Solo `Disponible`; `Archivado`, `archived_by/at`, `version + 1`, `updated_by/at`; irreversible en este incremento |

`Disponible → Archivado` es la única transición de estado; no existe restauración ni eliminación. Desactivar o renombrar la categoría no modifica ningún documento.

## Archivo almacenado (no es tabla)

Objeto privado `object_key` en el disco de documentos. Solo se crea antes de publicar y solo se elimina si pertenece a un intento no publicado (compensación inmediata) o si lleva más de 24 horas sin ser referenciado (barrido). Nunca se expone una URL del objeto.
