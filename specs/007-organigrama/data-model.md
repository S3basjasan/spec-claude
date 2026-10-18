# Data Model: Organigrama de posiciones

Esta feature **no crea tablas, columnas ni datos**: solo lee lo que implementan 004, 005 y 006. No escribe en ninguna tabla ni en `auditoria_cambios`.

## Tablas que se leen

| Tabla | Para qué |
|---|---|
| `posiciones` | Posiciones vigentes (`estado` en `Ocupada`, `Vacante`): código, estado, modalidad, supervisor (`posicion_jefe_id`), puesto, unidad y ocupante (`user_id`) |
| `puestos` | Código, nombre y descripción del puesto |
| `unidades_organizacionales` | Unidad de cada posición y árbol de unidades para el filtro |
| `tipos_unidad` | Tipo de la unidad (panel de detalle) |
| `users` (solo por el puerto de nombres) | Nombre del ocupante; el módulo no lee la tabla |

Las posiciones Suprimidas se excluyen en la consulta.

## Vista en memoria (no se almacena)

### Nodo del organigrama

`id`, `codigo`, `estado` (Ocupada | Vacante), `modalidad`, `posicion_jefe_id`, `puesto {id, codigo, nombre, descripcion}`, `unidad {id, codigo, nombre, tipo, estado}`, `ocupante {id, nombre}|null`. Los hijos se obtienen por `posicion_jefe_id` y se ordenan por la parte numérica del código.

### Unidad para el filtro

`id`, `codigo`, `nombre`, `nivel_arbol`, `unidad_padre_id` (solo unidades Activas, en orden de árbol).

### Estado de la pantalla (solo cliente)

Ramas contraídas por la persona (ids), posición seleccionada, zoom (0,5–1,5), búsqueda y unidad (estas dos en la URL). Se pierde al salir de la pantalla.

## Invariantes de las que depende (las garantiza 006)

1. La unidad raíz tiene como máximo una posición vigente; es la única sin supervisor.
2. La supervisora de toda posición vigente es vigente y está en la misma unidad o en una superior.
3. No hay ciclos.

Por tanto las posiciones vigentes forman un único árbol cuya raíz es la máxima autoridad, o no hay ninguna. El backend lo comprueba antes de responder (`ComprobadorDeArbol`); si no se cumple, 500 sin corregir datos.

## Datos iniciales

Sin seeders propios: usa los de usuarios, puestos, unidades y posiciones (ramas de varias profundidades, Ocupadas y Vacantes, supervisión dentro de una unidad y hacia una unidad superior no inmediata, y Suprimidas que no deben aparecer).
