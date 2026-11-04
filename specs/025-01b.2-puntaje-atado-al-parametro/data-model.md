# Data Model: Puntaje atado al parámetro (01b.2)

Fuente: `modelo-de-datos.dbml`. **Sin migraciones ni cambios de estructura.**

## Entidades que participan

### `categorias_reconocimiento` (existente, sin cambios)
- `puntuacion_base` smallint: pasa de «1 a 1000» a «1 a máximo permitido».
- `estado` (`Activo` / `Inactivo`): la activación depende del máximo (FR-011).
- `updated_at` (microsegundos): token de concurrencia; el 409 por versión se evalúa antes que el 422 por puntuación.

### `parametros_modulo` (existente, solo lectura)
- Clave `puntos_por_colaborador`: fila vigente (`vigente_desde` ≤ hoy, la más reciente) y fila programada (`vigente_desde` > hoy).
- Esta función no inserta ni modifica filas.

### `reconocimientos` y `desafios` (existentes, intactos)
- `puntos` se copia de `puntuacion_base` al registrarse; cambiar la puntuación de la categoría no los altera (FR-007).

## Valor derivado (no se almacena)

**Máximo permitido** = `min(valor vigente hoy, valor programado si existe)` de `puntos_por_colaborador`.

| Vigente hoy | Programado | Máximo permitido |
|---|---|---|
| 100 | — | 100 |
| 100 | 60 | 60 |
| 100 | 150 | 100 |
| 100 | 100 | 100 |

Cambio de mes: la fila programada pasa a ser vigente por fecha y el máximo se recalcula solo.

## Reglas de validación

| Operación | Regla | Resultado si falla |
|---|---|---|
| Crear | entero, `1 ≤ puntuacion_base ≤ máximo` | 422 `errors.puntuacion_base` «La puntuación base debe estar entre 1 y {máximo}.» |
| Editar | igual, aunque solo cambie otro campo (FR-010) | igual |
| Activar (Inactivo → Activo) | `puntuacion_base ≤ máximo` | 422 `errors.puntuacion_base` «Baja primero la puntuación a {máximo} o menos.»; sigue Inactiva |
| Activar una ya activa | sin cambios | 200, sin auditoría |
| Desactivar | sin regla nueva | como hoy |
| Cualquier otra regla de inc-01 | sin cambios | como hoy |

El límite exterior `between:1,1000` del `FormRequest` se mantiene; un valor no entero o fuera de ese rango se rechaza antes de consultar el parámetro.

## Transiciones de estado

Sin estados nuevos. La única transición afectada es `Inactivo → Activo`, que ahora puede rechazarse con 422.
