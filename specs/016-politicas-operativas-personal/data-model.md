# Data Model: Configuración de políticas operativas de personal

**Fecha**: 2026-10-20 | **Spec**: [spec.md](spec.md) | **Research**: [research.md](research.md)

Tres tablas nuevas en SQLite, todas con `tenant_id` (hoy hay un solo tenant). Ninguna referencia tablas de otros módulos. No se crean tablas de solicitudes: los consumidores guardan su propia referencia a `politica_versiones.id` (ver [contracts/consumo-de-politicas.md](contracts/consumo-de-politicas.md)). Todas las FK son `ON DELETE RESTRICT`.

## Entidades

### `politicas_personal` (cabecera por tipo)

| Campo | Tipo | Reglas |
|---|---|---|
| `id` | bigint PK | |
| `tenant_id` | bigint, no nulo | |
| `tipo` | varchar(12), no nulo | `Permisos` \| `Ausencias` \| `Licencias` \| `Vacaciones` \| `Ascensos` \| `Traslados` \| `Offboarding`; fijado por el sistema |
| `ultimo_numero` | int sin signo, defecto 0 | contador monótono de publicaciones del tipo; nunca disminuye |
| `version` | int sin signo, defecto 1 | marca de la cabecera (para el bloqueo; no se expone) |
| `created_at`, `updated_at` | timestamp | |

Único `(tenant_id, tipo)`. Se crea de forma idempotente en la primera escritura de ese tipo y por el seeder. Su ausencia en lecturas equivale a «sin configurar».

### `politica_versiones`

| Campo | Tipo | Reglas |
|---|---|---|
| `id` | bigint PK | |
| `tenant_id` | bigint, no nulo | |
| `politica_id` | FK `politicas_personal`, no nulo | |
| `numero` | int sin signo, nulo | `NULL` mientras sea borrador; se asigna al publicar (`ultimo_numero + 1`); único `(politica_id, numero)` donde no es nulo |
| `descripcion` | varchar(1000), no nulo | trimmed; obligatorio para publicar |
| `parametros` | text JSON, no nulo | validado según el tipo; claves desconocidas rechazadas; tareas con orden |
| `base_version_id` | FK `politica_versiones`, nulo | versión a partir de la cual se creó el borrador (informativo) |
| `version` | int sin signo, defecto 1 | control de edición del borrador (409 si no coincide) |
| `created_by`, `updated_by` | bigint, no nulo | |
| `created_at`, `updated_at` | timestamp | |
| `descartada_en`, `descartada_por` | timestamp, bigint, nulos | solo borradores |
| `publicada_en`, `publicada_por` | timestamp, bigint, nulos | se fijan una sola vez |
| `inicio_vigencia` | timestamp UTC, nulo | no nulo al publicar; ≥ `publicada_en` |
| `motivo_cambio` | varchar(500), nulo | obligatorio al publicar |
| `revisor_nombre` | varchar(150), nulo | obligatorio al publicar |
| `revision_observaciones` | varchar(1000), nulo | opcional |
| `revision_en` | timestamp, nulo | momento de la confirmación de la revisión (servidor) |
| `condiciones` | text JSON, nulo | representación sellada al publicar (ver abajo) |
| `condiciones_hash` | char(64), nulo | `sha256` de `condiciones` canónicas |
| `retirada_en`, `retirada_por` | timestamp, bigint, nulos | solo versiones programadas antes de su inicio |
| `retiro_motivo` | varchar(500), nulo | obligatorio al retirar |

Índices: único parcial `(politica_id) WHERE publicada_en IS NULL AND descartada_en IS NULL` (un borrador activo); único `(politica_id, numero) WHERE numero IS NOT NULL`; `(politica_id, inicio_vigencia, numero)` para la consulta de la aplicable; `(tenant_id, politica_id, publicada_en)` para el historial.

`CHECK`/*trigger* de coherencia: `numero`, `publicada_en`, `inicio_vigencia`, `motivo_cambio`, `revisor_nombre`, `condiciones` y `condiciones_hash` son todos nulos o todos no nulos; `retirada_en` solo con `publicada_en`; `retirada_en`, `retirada_por` y `retiro_motivo` van juntos; `descartada_en` excluye `publicada_en`.

### `politica_acciones` (auditoría, solo inserción)

| Campo | Tipo | Reglas |
|---|---|---|
| `id` | bigint PK | |
| `tenant_id` | bigint, no nulo | |
| `politica_id` | FK `politicas_personal`, no nulo | |
| `version_id` | FK `politica_versiones`, no nulo | |
| `accion` | varchar(20), no nulo | `BorradorCreado` \| `Publicacion` \| `Retiro` \| `BorradorDescartado` |
| `actor_id` | bigint, no nulo | sesión autenticada |
| `motivo` | varchar(500), nulo | motivo del cambio o justificación del retiro |
| `operacion_id` | char(36), nulo | solo `Publicacion` y `Retiro` |
| `operacion_hash` | char(64), nulo | `sha256` del contenido de la operación |
| `created_at` | timestamp | |

Único parcial `(tenant_id, operacion_id) WHERE operacion_id IS NOT NULL`; índice `(politica_id, created_at)`. *Triggers*: `UPDATE` y `DELETE` abortan siempre.

## Representación sellada (`condiciones`)

JSON canónico (claves ordenadas, sin espacios, UTF-8) construido al publicar:

```json
{
  "esquema": 1,
  "tipo": "Permisos",
  "numero": 3,
  "inicio_vigencia": "2026-11-01T13:00:00Z",
  "parametros": { "disponible": true, "justificacion_obligatoria": true, "documento_obligatorio": false, "anticipacion_dias": 2 },
  "reglas_fijas": [ { "clave": "…", "texto": "…" } ]
}
```

Offboarding: `parametros.tareas` = `[{ "orden": 1, "nombre": "…", "descripcion": null, "exige_evidencia": true }]`. Los consumidores copian este objeto (con su hash) a sus solicitudes. No contiene descripción interna, motivo ni revisión.

## Estados (derivados con el instante `ahora`)

| Estado | Condición |
|---|---|
| Borrador | `publicada_en IS NULL AND descartada_en IS NULL` |
| Descartado | `descartada_en IS NOT NULL` (no se lista como versión) |
| Retirada | `retirada_en IS NOT NULL` |
| Programada | `publicada_en IS NOT NULL AND retirada_en IS NULL AND inicio_vigencia > ahora` |
| Vigente | entre las publicadas no retiradas con `inicio_vigencia <= ahora`, la de mayor `inicio_vigencia` (desempate: mayor `numero`) |
| Sustituida | publicada no retirada con `inicio_vigencia <= ahora` que no es la vigente |

Por construcción hay como máximo una Vigente y una Programada por tipo y tenant (una publicación se rechaza si existe una programada; la programada futura nunca compite con la vigente).

## Transiciones

| Desde | Acción | Hacia | Condición |
|---|---|---|---|
| (nada) | crear borrador | Borrador | no existe otro borrador activo (409 `BORRADOR_EXISTENTE`); registra `BorradorCreado` |
| Borrador | editar | Borrador | `version` coincide (409 `BORRADOR_MODIFICADO`) |
| Borrador | descartar | Descartado | registra `BorradorDescartado` |
| Borrador | publicar inmediato | Vigente | sin programada (409 `PROGRAMADA_EXISTENTE`); válido; con motivo y revisión; registra `Publicacion` |
| Borrador | publicar programado | Programada | ídem, con inicio local futuro |
| Programada | llega el inicio | Vigente (la anterior → Sustituida) | derivado del reloj |
| Programada | retirar | Retirada | `ahora < inicio_vigencia` dentro de la transacción (409 `VIGENCIA_INICIADA` si no); con justificación; registra `Retiro` |
| Vigente / Sustituida / Retirada | cualquier edición o eliminación | — | prohibido (409 y *trigger*) |

## Reglas de integridad

- La numeración se asigna solo al publicar, con `ultimo_numero` bloqueado; los números no se reutilizan y no se asignan a borradores.
- El bloqueo de cabecera serializa: crear borrador, publicar, retirar y la selección para presentar una solicitud.
- Las columnas selladas de una versión publicada no cambian; solo `retirada_*` y `updated_*`.
- La eliminación de una versión publicada o de una acción está prohibida (*trigger*); las FK son restrictivas.
- `base_version_id` es informativo y no afecta a las comparaciones (que se hacen contra la vigente real).
- El tenant sale siempre de la sesión; ningún identificador de otro tenant se resuelve (404 uniforme).

## Equivalencias para otro motor

Los índices únicos parciales y *triggers* se escriben en la migración con la sintaxis de SQLite; en PostgreSQL se usarían los mismos índices parciales y *triggers* `BEFORE UPDATE/DELETE` que lanzan excepción. El bloqueo usa `UPDATE … SET version = version`, que bloquea la fila en ambos motores.
