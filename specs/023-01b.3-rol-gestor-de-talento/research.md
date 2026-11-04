# Research: Nombre del rol «Gestor de Talento» (01b.3)

No quedaron `NEEDS CLARIFICATION`. Hallazgos que fijan el alcance real:

## 1. Dónde aparece el nombre anterior en el módulo

- **Decisión**: el módulo es `Compensaciones` (backend) / `compensaciones/categorias` (frontend). Allí el único texto visible que nombra al rol es «Cuando Talento Humano cree categorías, aparecerán aquí.» ([text.ts](../../frontend/src/modules/compensaciones/categorias/text.ts) línea 48) y su aserción en `CategoriasReconocimientoPage.dom.test.tsx`. «Administrador de Talento Humano» solo aparece en la maqueta `design/CategoriasReconocimiento.dc.html` (líneas 84, 900 y 1101).
- **Rationale**: lo demás que dice «Administrador» son comentarios, nombres de pruebas y el indicador `esAdmin`, no texto visible.
- **Alternativas**: incluir `estructura/posiciones` y `gestionPersonal/fichas` (que sí dicen «Administrador de Talento Humano»); descartado por la spec (otros módulos fuera de alcance).

## 2. Permisos recurso.acción

- **Decisión**: `PoliticaDeAcceso` hoy solo tiene `puedeGestionar()` sobre `tokenCan('admin')`. Pasa a `puede($usuario, $permiso)` con la constante `categorias.gestionar`; permiso desconocido ⇒ falso.
- **Rationale**: respuesta de la clarificación y plan de origen; mantiene el acceso idéntico.
- **Alternativas**: permiso por acción (rechazado: granularidad no pedida); renombrar `admin` (rechazado: prohibido por CA-02).

## 3. Convención ya usada en otro módulo

- `Desarrollo` autoriza con middleware `permiso:desarrollo.adecuacion.*`. **Decisión**: no adoptarlo aquí; el plan de origen conserva el middleware `admin` en las rutas de Compensaciones y deja la comprobación fina en `PoliticaDeAcceso`. Unificar mecanismos queda para el proyecto real.

## 4. Pruebas existentes

- Las pruebas Pest de `tests/Feature/Compensaciones` ejercen la API por token, no llaman a `puedeGestionar()` directamente, así que siguen pasando sin cambios. Solo se agrega `PoliticaDeAccesoTest`.
