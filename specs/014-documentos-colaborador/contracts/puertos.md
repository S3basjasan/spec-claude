# Contrato: puertos internos de la gestión documental

Ningún controlador ni caso de uso toca el disco, `Storage` ni un validador concreto; todo pasa por estos puertos. Los puertos viven en `GP/Application/Ports` y sus adaptadores en `GP/Infrastructure`.

## `AlmacenDeDocumentosPort`

| Método | Comportamiento |
|---|---|
| `guardar(string $rutaTemporal, string $clave): void` | Escribe el objeto privado en `clave`; falla si ya existe (nunca sobrescribe) y lanza `AlmacenamientoNoDisponible` ante un fallo del almacén |
| `leerComoFlujo(string $clave)` | Devuelve un flujo de lectura; `AlmacenamientoNoDisponible` si no se puede abrir (antes de enviar un solo byte) |
| `eliminar(string $clave): bool` | Elimina solo ese objeto; devuelve `false` si falló (la compensación lo registra y continúa) |
| `listar(string $prefijo): iterable<{clave, modificadoEn}>` | Lista objetos del prefijo técnico, para el barrido |

Clave de objeto: `personal/{tenant_id}/colaboradores/{colaborador_id}/{uuid-aleatorio}.pdf`. No deriva del nombre del cliente y no se reutiliza entre intentos, ni siquiera con la misma `Idempotency-Key`. El adaptador de `Infrastructure` usa el disco `hrms.gestion_personal.documentos.disco` (privado). No existe ningún método que genere URL, firmada o no.

## `ValidadorDePdfPort`

| Método | Comportamiento |
|---|---|
| `validar(string $rutaTemporal): ResultadoDeValidacion` | `valido` o `invalido(motivo)` con motivos: `no_es_pdf` (tipo real por contenido), `estructura_invalida` (cabecera, `%%EOF`, `startxref`, catálogo/objetos), `vacio`; lanza `ValidacionNoDisponible` (⇒ 503) si no puede leer el archivo por una limitación técnica |

No renderiza páginas, no interpreta JavaScript ni acciones, no abre enlaces externos. No es un antivirus.

## `DocumentoRepositoryInterface` (dominio)

`porId(tenantId, colaboradorId, id)`, `listar(tenantId, colaboradorId, filtro, soloVisiblesDisponibles)`, `porOperacion(tenantId, operacionId)`, `publicar(…)` (inserta metadatos completos; la unicidad de `(tenant, operacion)` y de `object_key` la decide la base de datos), `cambiarVisibilidad(tenantId, colaboradorId, id, version, actorId, visible)`, `archivar(tenantId, colaboradorId, id, version, actorId)`, `claveReferenciada(tenantId?, clave)` y `claves(prefijo)` para el barrido. Sin ningún método de eliminación. Las actualizaciones usan `WHERE id, colaborador_id, tenant_id, version` y distinguen inexistente (404) de obsoleta o archivada (409).

## Coordinación con la categoría

`CargarDocumento` llama a `CatalogoDeCategoriasPort::confirmarActiva(tenantId, categoriaId)` **dentro de la transacción de publicación** (ya implementado en la feature 013); `CategoriaInactiva` se traduce a 409 `CATEGORIA_INACTIVA` y el objeto del intento se elimina. El nombre vigente de las categorías de un listado se obtiene con `CatalogoDeCategoriasPort::porIds` en una sola lectura (activas o inactivas); si esa lectura falla, el listado responde 503 sin ocultar el error ni inventar etiquetas.

## Facade (para GP-06)

La exportación individual de ficha integral **no incrusta adjuntos**: este incremento no expone documentos a otros módulos y no añade métodos a `PersonalFacade`.
