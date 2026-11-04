# Contrato: puntaje de categorías atado al parámetro (01b.2)

Base: `/api/compensaciones`. Los endpoints ya existen (inc-01 y 01b.1); este contrato solo describe lo que cambia. Autenticación y permisos no cambian: crear, editar y cambiar el estado siguen reservados al Gestor (`categorias.gestionar`).

## Máximo permitido

`máximo = min(puntos_por_colaborador vigente hoy, valor programado para el mes siguiente si existe)`. Se calcula en el servidor en el momento de cada operación.

## GET /parametros (01b.1, ampliado)

Se agrega `meta` sin tocar `data`:

```json
{
  "data": [ /* seis claves, sin cambios */ ],
  "meta": { "puntuacion_maxima_categoria": 60 }
}
```

- `puntuacion_maxima_categoria`: entero ≥ 1. Con vigente 100 y programado 60 vale 60; con programado 150 vale 100.
- Mismo permiso que el endpoint (`parametros.gestionar`); con otro usuario responde 403 y el formulario de categorías queda sin número de máximo (el servidor sigue validando).
- Para la nota «el tope baja por el cambio programado», el cliente compara `meta.puntuacion_maxima_categoria` con `data[clave = puntos_por_colaborador].valor`: si es menor, el máximo proviene del valor programado.

## POST /categorias y PUT /categorias/{id}

Cuerpo sin cambios. La regla `puntuacion_base`: entero, `1 ≤ valor ≤ máximo`.

| Estado | Cuándo | Cuerpo |
|---|---|---|
| 201 / 200 | Puntuación entre 1 y el máximo (incluidos los extremos) | categoría, como hoy |
| 422 | Puntuación mayor que el máximo | `{"message": "La puntuación base debe estar entre 1 y {máximo}.", "errors": {"puntuacion_base": ["La puntuación base debe estar entre 1 y {máximo}."]}}` |
| 422 | Menor que 1, decimal, texto o vacío | `errors.puntuacion_base` con el mensaje de siempre (entero / obligatorio / entre 1 y 1000 como límite exterior) |
| 409 | `updated_at` desactualizado (solo PUT) | como hoy; se evalúa antes que el 422 por puntuación |

- En PUT la regla se aplica **aunque solo cambie otro campo** (FR-010): una categoría que ya supera el máximo no se puede guardar hasta bajarla.
- El resto de errores (nombre repetido, competencia no disponible, indicadores, etc.) no cambia.

## PATCH /categorias/{id}/estado

Cuerpo sin cambios (`estado`, `updated_at`).

| Estado | Cuándo | Cuerpo |
|---|---|---|
| 200 | Desactivar; activar con puntuación ≤ máximo; pedir el estado actual | como hoy |
| 422 | Activar una categoría `Inactivo` con puntuación > máximo | `{"message": "Baja primero la puntuación a {máximo} o menos.", "errors": {"puntuacion_base": ["Baja primero la puntuación a {máximo} o menos."]}}`; la categoría sigue `Inactivo`, sin auditoría |
| 409 | `updated_at` desactualizado | como hoy; se evalúa antes que el 422 |

## Sin efecto sobre registros existentes

Ninguna de estas operaciones modifica `reconocimientos.puntos` ni `desafios.puntos`.
