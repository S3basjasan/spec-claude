# Contratos: Ajustes técnicos del modelo de categorías (01b.5)

Esta feature **no cambia ningún contrato**: mismos endpoints bajo `/api/compensaciones/categorias`, mismos cuerpos de petición y respuesta, mismos códigos y mensajes de error (401, 403, 404, 409, 422) y misma forma de `data.indicadores` (`[{ "orden": 1, "texto": "…" }]`, omitido en el listado).

Los contratos vigentes son los de inc-01 (`specs/016-categorias-reconocimiento/contracts/`). Cualquier diferencia de respuesta entre antes y después del cambio es un defecto de esta feature.

Contratos internos que tampoco cambian: `CategoriaRepository` (interfaz de Dominio), `CompetenciasPort` y `CategoriasFacade`.
