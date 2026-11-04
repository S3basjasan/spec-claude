# Plan · 01b.2 Puntaje atado al parámetro

## 1. Contexto de la feature
- Stack y arquitectura según `constitution.md`.
- Backend: `backend/app/Modules/Compensaciones/` (casos de uso `CrearCategoria` y `ActualizarCategoria`, objeto de valor `PuntuacionBase`, `GuardarCategoriaRequest`).
- Frontend: `frontend/src/modules/compensaciones/categorias/` (`CategoriaFormModal`, `validation.ts`, `text.ts`).
- Depende de: 01b.1 (servicio `ParametrosDelModulo`).

## 2. Modelo de datos
- `categorias_reconocimiento` (sin cambios de estructura).
- `parametros_modulo` (solo lectura).

## 3. Backend
Los endpoints ya existen; cambia su validación.

| Operación | Endpoint | Notas | Requisito |
|---|---|---|---|
| Crear categoría | POST /api/compensaciones/categorias | La puntuación base debe estar entre 1 y el máximo permitido. Máximo permitido = el menor entre el valor de puntos por colaborador vigente hoy y el programado para el mes siguiente, si lo hay (así ninguna categoría queda por encima del parámetro cuando el cambio entre en vigencia). Fuera de rango: 422 en el campo `puntuacion_base`. | CA-01, CA-03 |
| Editar categoría | PUT /api/compensaciones/categorias/{id} | Misma regla que crear, aunque solo cambie otro campo: toda categoría guardada queda dentro del máximo. | CA-01, CA-03 |
| Activar categoría | Endpoint existente de cambio de estado (inc-01) | Si la puntuación supera el máximo permitido: 422 y no se activa. La regla de 01b.1 solo revisa las categorías activas, así que sin esto una categoría reactivada quedaría por encima del tope. | CA-06 |
| Consultar parámetros | GET /api/compensaciones/parametros (01b.1) | El formulario lee de aquí el máximo permitido; se agrega el campo `puntuacion_maxima_categoria` a la respuesta. | CA-02 |

- `PuntuacionBase` deja de tener el 1000 fijo: recibe el máximo desde Aplicación. La regla `between:1,1000` del request se mantiene como límite exterior.
- No se modifican puntos ya registrados en reconocimientos o desafíos: se copian al registrarse (ver modelo de datos). (CA-04)
- El resto de reglas de inc-01 no se toca. (CA-05)

Mensajes que muestra el frontend:
- 422: «La puntuación base debe estar entre 1 y {máximo}.»
- 422 al activar: «Baja primero la puntuación a {máximo} o menos.»

Pruebas (Pest):
- Crear y editar con puntuación igual al máximo: 201/200. Con máximo + 1: 422.
- Con un cambio programado menor para el mes siguiente, el máximo es el programado.
- Activar una inactiva con puntuación mayor que el máximo: 422 y sigue Inactiva; con puntuación igual al máximo: se activa.
- Editar la puntuación de una categoría no cambia nada fuera de esa categoría.
- Las pruebas existentes de categorías siguen pasando.

## 4. Frontend
- Referencia visual: formulario de categorías actual (`design/CategoriasReconocimiento.dc.html`).
- Junto al campo de puntuación se muestra «Máximo: {n} puntos». El esquema de Zod usa ese máximo.
- Si el servidor responde 422 en la puntuación, el error aparece junto al campo y se conservan los valores.
- Pruebas: Vitest del esquema con máximo dinámico.

## 5. Relación con otros módulos
- Ninguna nueva.
