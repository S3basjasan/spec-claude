# Data Model: Categorías e indicadores cualitativos de reconocimiento

## categorias_reconocimiento

| Campo | Tipo | Reglas |
|---|---|---|
| id | bigint PK | |
| codigo | string(20), único | `CAT-` + número ≥ 3 dígitos; inmutable; lo genera el backend |
| nombre | string(100) | 3–100 tras recortar; texto plano |
| nombre_normalizado | string(100), único | minúsculas, sin tildes, espacios recortados y colapsados; lo calcula el backend |
| descripcion | text | 10–500 tras recortar; texto plano |
| competencia_id | FK `competencias`, `restrictOnDelete`, índice | activa al crear o al cambiarla |
| puntuacion_base | unsignedSmallInteger | entero 1–1000 |
| icono | string(50) | uno de los 12 identificadores cerrados |
| estado | string(10), índice | `Activo` (defecto) \| `Inactivo`; nunca viene del cliente |
| created_by, updated_by | unsignedBigInteger nullable, sin FK | id de usuario |
| created_at, updated_at | timestamp con microsegundos | `updated_at` = token de concurrencia |

Sin soft delete ni DELETE físico.

**Derivados (no almacenados)**: `competencia_activa` (la competencia asociada está `Activo`) y `seleccionable` (`estado = Activo` y `competencia_activa`).

**Transiciones de estado**: `Activo ⇄ Inactivo` solo con Activar/Desactivar. Pedir el estado actual con token vigente es una operación sin efecto.

## indicadores_reconocimiento

| Campo | Tipo | Reglas |
|---|---|---|
| id | bigint PK | |
| categoria_reconocimiento_id | FK, `restrictOnDelete`, índice | |
| orden | unsignedSmallInteger | 1–5, consecutivo, asignado por posición |
| texto | string(250) | 5–250 tras recortar; texto plano |
| texto_normalizado | string(250) | misma normalización que el nombre |
| created_at, updated_at | timestamps | |

Únicos: `(categoria_reconocimiento_id, orden)` y `(categoria_reconocimiento_id, texto_normalizado)`. Cada categoría tiene de 1 a 5 filas; la lista se reemplaza completa en cada guardado.

## auditoria_categorias_reconocimiento (tabla propia, solo inserción)

| Campo | Tipo | Reglas |
|---|---|---|
| id | bigint PK | |
| categoria_reconocimiento_id | FK, `restrictOnDelete`, índice | |
| campo | string(30) | uno de: nombre, descripcion, competencia_id, puntuacion_base, icono, indicadores, estado |
| valor_anterior | text nullable | nulo al crear; indicadores como JSON de la lista de textos en orden; competencia como su id |
| valor_nuevo | text nullable | ídem |
| user_id | unsignedBigInteger nullable, sin FK | |
| created_at | timestamp | sin `updated_at`; filas inmutables |

## Relaciones

- Categoría 1—N Indicador.
- Categoría N—1 Competencia (módulo Estructura; solo lectura vía `EstructuraFacade`).
- Categoría 1—N Auditoría (`auditoria_categorias_reconocimiento`).

## Seeders

`CategoriasReconocimientoSeeder` (depende de `CompetenciasSeeder` y `UsuariosInicialesSeeder`): ≥ 18 categorías con nombres que mezclan mayúsculas y tildes e íconos variados; ≥ 3 Inactivas; ≥ 1 Activa con competencia inactiva; categorías con 1 y con 5 indicadores. Idempotente.
