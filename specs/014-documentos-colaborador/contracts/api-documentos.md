# Contrato HTTP: Documentos del colaborador

Prefijos de GP-01 (`/api/colaboradores`, `/api/mi-ficha`). Las rutas de administración van bajo `auth:sanctum` + `admin`; las propias, solo `auth:sanctum` (el caso de uso exige asociación válida y permiso documental). **No existe ningún `DELETE`, ni restauración, ni edición de título, categoría o archivo.** Instantes ISO 8601 UTC; ids enteros. Errores en el formato actual: `{message, code, errors?}`. El tenant y el actor salen siempre de la sesión.

## Representaciones

Administrador (`documento`):

```json
{
  "data": {
    "id": 31, "titulo": "Contrato firmado", "estado": "Disponible", "visible_colaborador": false,
    "categoria": { "id": 4, "nombre": "Contratos", "estado": "Activa" },
    "formato": "application/pdf", "tamano_bytes": 482113,
    "version": 1, "created_by": 1, "created_at": "2026-10-16T14:00:00Z",
    "updated_by": 1, "updated_at": "2026-10-16T14:00:00Z",
    "archivado_por": null, "archivado_en": null
  }
}
```

Colaborador (`mi_documento`): solo `id`, `titulo`, `categoria {id, nombre, estado}`, `formato`, `tamano_bytes`, `created_at`. Nunca incluye `visible_colaborador`, `estado`, auditoría ni `object_key`. Ninguna respuesta incluye `object_key`, rutas, URL ni credenciales de almacenamiento.

## Endpoints

| Método y ruta | Quién | Entrada | Respuestas |
|---|---|---|---|
| `POST /api/colaboradores/{colaborador}/documentos` | Admin | `multipart/form-data`: `titulo`, `categoria_id`, `archivo`, `visible_colaborador` (por defecto `false`); cabecera `Idempotency-Key` (UUID) | 201 `documento`; 200 `documento` en repetición válida; 422; 409; 413; 404; 503 |
| `GET /api/colaboradores/{colaborador}/documentos` | Admin | `q` (≤ 50), `categoria_id`, `estado` = `Disponible` \| `Archivado` (por defecto `Disponible`), `page`, `per_page` (1–50, 20 por defecto) | 200 paginado `{data, meta}`, más recientes primero (`created_at`, `id`); 422; 404 |
| `GET /api/colaboradores/{colaborador}/documentos/{documento}/contenido` | Admin | `descarga=1` opcional | 200 `application/pdf` en streaming (cualquier estado); 404; 503 |
| `PATCH /api/colaboradores/{colaborador}/documentos/{documento}/visibilidad` | Admin | `visible_colaborador` (booleano), `version` | 200 `documento`; 409 (versión obsoleta o documento archivado); 422; 404; 503 |
| `PATCH /api/colaboradores/{colaborador}/documentos/{documento}/archivo` | Admin | `version` | 200 `documento`; 409 (versión obsoleta o ya archivado); 422; 404; 503 |
| `GET /api/mi-ficha/documentos` | Cuenta con permiso documental | `q`, `categoria_id`, `page`, `per_page`; no admite `estado` | 200 paginado de `mi_documento` (solo visibles y Disponibles); 403 `CUENTA_SIN_FICHA` / `SIN_PERMISO_DOCUMENTOS` |
| `GET /api/mi-ficha/documentos/{documento}/contenido` | Cuenta con permiso documental | `descarga=1` opcional | 200 `application/pdf`; 403 como arriba; 404 |

`{colaborador}` y `{documento}` solo admiten dígitos. Un documento de otra ficha de la ruta responde 404.

## Cabeceras de contenido

`Content-Type: application/pdf`, `X-Content-Type-Options: nosniff`, `Cache-Control: private, no-store`, `Content-Disposition: inline; filename="Documento_{id}.pdf"` (`attachment` con `descarga=1`). El nombre nunca se toma del cliente ni del título.

## Reglas de respuesta

- **401** sin sesión. **403** al Colaborador en cualquier ruta de administración (middleware `admin`), sin revelar si el recurso existe; **403** `CUENTA_SIN_FICHA` o `SIN_PERMISO_DOCUMENTOS` en las rutas propias (sin permiso documental, aunque tenga el de ficha o el de exportación).
- **404** idéntico (`DOCUMENTO_NO_ENCONTRADO` / `FICHA_NO_ENCONTRADA`) para inexistente, de otra ficha o de otro tenant, y para el Colaborador ante un documento privado, archivado o ajeno; sin bytes.
- **422** `VALIDACION`: `errors.titulo` (obligatorio, más de 200), `errors.categoria_id` (obligatoria o inexistente), `errors.archivo` con un motivo: `ARCHIVO_REQUERIDO`, vacío, formato no PDF o PDF no válido, demasiado grande (más de 10 000 000 bytes); `errors.visible_colaborador` no booleano; `CLAVE_DE_OPERACION_REQUERIDA` si falta o no es UUID la cabecera.
- **409**: `CLAVE_DE_OPERACION_REUTILIZADA` (misma clave con otro contenido, ficha o actor); `DOCUMENTO_MODIFICADO` (versión obsoleta o documento archivado en visibilidad/archivo); `CATEGORIA_INACTIVA` (la categoría se desactivó o no existe al publicar; no se reasigna ni se registra el documento).
- **413** `ARCHIVO_DEMASIADO_GRANDE` cuando PHP o el proxy descartan un cuerpo mayor que el permitido; el cliente lo presenta como tamaño excedido.
- **503** `ESCRITURA_DESHABILITADA` (`hrms.gestion_personal.documentos.habilitado`), `ALMACENAMIENTO_NO_DISPONIBLE` o validación técnica no disponible (distinto de «archivo inválido»); nunca se envían bytes parciales ni un documento sustituto.
- Los metadatos (`formato`, `tamano_bytes`, actor, fechas, clave de almacenamiento) los fija el servidor; los campos desconocidos del cuerpo se ignoran.
- Ninguna operación elimina ni restaura documentos.
