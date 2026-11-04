# Implementation Plan: Puntaje atado al parámetro (01b.2)

**Branch**: `025-01b.2-puntaje-atado-al-parametro` | **Date**: 2026-11-03 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/025-01b.2-puntaje-atado-al-parametro/spec.md` y plan de origen [plan-origen-01b.2-puntaje-atado-al-parametro.md](plan-origen-01b.2-puntaje-atado-al-parametro.md) (inc-01b), con `categorias_reconocimiento` y `parametros_modulo` de `modelo-de-datos.dbml`.

## Summary

La puntuación base de una categoría deja de tener un tope fijo de 1000 y pasa a estar acotada por el **máximo permitido** = el menor entre los puntos por colaborador vigentes hoy y el valor programado para el mes siguiente (si existe). Backend: el servicio `ParametrosDelModulo` (01b.1) expone ese máximo; `CrearCategoria`, `ActualizarCategoria` y `CambiarEstadoCategoria` (al activar) lo aplican y responden 422 con el mensaje exacto; `GET /api/compensaciones/parametros` agrega `meta.puntuacion_maxima_categoria`. Frontend: el formulario de categorías lee ese máximo, lo muestra junto al campo («Máximo: {n} puntos») y valida con un esquema Zod parametrizado; el listado muestra el rechazo de «Activar». No hay migraciones ni tablas nuevas.

## Technical Context

**Language/Version**: PHP 8.3 / Laravel (versión del repositorio); TypeScript estricto, React

**Primary Dependencies**: las existentes (Sanctum, React Hook Form, Zod, TanStack Query, Axios); no se añade ninguna

**Storage**: SQLite en desarrollo. Sin cambios de estructura: `categorias_reconocimiento` y `parametros_modulo` (solo lectura)

**Testing**: Pest (backend); Vitest + Testing Library (frontend); E2E Playwright existente `frontend/e2e/categorias-reconocimiento.spec.ts` ampliado

**Target Platform**: Web (SPA + API)

**Project Type**: web-application (backend + frontend)

**Performance Goals**: sin objetivos adicionales; el cálculo del máximo son dos lecturas por índice `(clave, vigente_desde)` ya existentes

**Constraints**: la validación manda el servidor (el cliente solo replica); el máximo se calcula en el momento de guardar o activar, no con el valor mostrado al abrir; no se tocan puntos copiados en reconocimientos ni desafíos; el resto de reglas de inc-01 no cambia salvo la activación (FR-011)

**Scale/Scope**: 3 casos de uso modificados, 1 servicio ampliado, 1 objeto de valor, 1 respuesta ampliada, 1 formulario y 1 acción del listado en frontend

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principio | Evaluación |
|---|---|
| I. Backend modular / Clean Architecture | Pasa. La regla vive en Dominio (`PuntuacionBase` recibe el máximo) y se orquesta en casos de uso; el cálculo del máximo está en el servicio de Aplicación `ParametrosDelModulo`, sin Eloquent ni HTTP. Controladores sin cambios de lógica (la excepción de dominio existente ya se traduce a 422). Rutas bajo `/api/compensaciones/`. |
| II. Frontend modular, accesible y tipado | Pasa. Cambios dentro de `frontend/src/modules/compensaciones/categorias/` (`api.ts`, `hooks/`, `validation.ts`, `text.ts`, `CategoriaFormModal`). Sin `any`; todo texto nuevo en `text.ts`; errores por campo con valores conservados y envío bloqueado mientras se guarda; Axios solo en la capa compartida. |
| III. Diseño como referencia | Pasa. Se sigue `design/CategoriasReconocimiento.dc.html`; solo se añade el texto auxiliar del máximo bajo el campo (mismo estilo que `pointsHint`). Si `design/DESIGN.md` o el `.dc.html` no muestran ese texto, se actualizan antes de implementar la interfaz y se registra aquí. |
| IV. Calidad y seguridad | Pasa. Cobertura Pest/Vitest/E2E definida en `quickstart.md`; validación en servidor y cliente; sin dependencias nuevas; errores públicos seguros. |

Sin violaciones; no se requiere Complexity Tracking. Re-evaluado tras Phase 1: sin cambios.

## Project Structure

### Documentation (this feature)

```text
specs/025-01b.2-puntaje-atado-al-parametro/
├── 01b.2-puntaje-atado-al-parametro.md          # Fuente de la spec (copiada)
├── plan-origen-01b.2-puntaje-atado-al-parametro.md  # Fuente del plan (copiada)
├── spec.md
├── plan.md                                      # Este archivo
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── categorias-puntaje-api.md
├── checklists/requirements.md
└── tasks.md                                     # Lo crea /speckit-tasks
```

### Source Code (repository root)

```text
backend/
├── app/Modules/Compensaciones/
│   ├── Domain/
│   │   └── ValueObjects/PuntuacionBase.php          # recibe el máximo; MAXIMA (1000) queda como límite exterior
│   ├── Application/
│   │   ├── DTOs/DatosCategoriaDTO.php               # comoPropuesta(estado, maximo)
│   │   ├── Services/ParametrosDelModulo.php         # + puntuacionMaximaDeCategoria()
│   │   ├── UseCases/CrearCategoria.php              # pasa el máximo al construir la propuesta
│   │   ├── UseCases/ActualizarCategoria.php         # ídem, también si solo cambia otro campo
│   │   ├── UseCases/CambiarEstadoCategoria.php      # al activar, rechaza si puntuación > máximo
│   │   └── UseCases/ConsultarParametros.php         # + máximo para la respuesta
│   └── Presentation/
│       ├── Controllers/CategoriaController.php      # sin cambios (DatosDeCategoriaInvalidos ya mapea a 422)
│       └── Resources/ParametrosResource.php         # + meta.puntuacion_maxima_categoria
└── tests/Feature/Compensaciones/
    ├── PuntajeAtadoAlParametroTest.php              # máximo permitido, crear / editar / programado
    ├── PuntajeAtadoValidacionServidorTest.php       # 422 por API directa (US3)
    ├── PuntajeAtadoSinEfectosTest.php               # puntos registrados intactos (US4; cobertura parcial, ver research §7)
    ├── PuntajeAtadoActivacionTest.php               # activar con puntuación sobre el tope (US5)
    └── (ParametrosPuntosTest.php, CrearCategoriaTest.php, ActualizarCategoriaTest.php, CambiarEstadoCategoriaTest.php: ajustes si dependían del tope 1000)

frontend/
├── src/modules/compensaciones/categorias/
│   ├── api.ts                                       # + obtenerPuntuacionMaxima()
│   ├── hooks/usePuntuacionMaxima.ts                 # consulta propia; se refresca al abrir el formulario
│   ├── validation.ts (+ validation.test.ts)         # categoriaSchema -> crearCategoriaSchema(maximo)
│   ├── text.ts                                      # «Máximo: {n} puntos», nota de cambio programado, mensajes
│   ├── components/CategoriaFormModal.tsx (+ .dom.test.tsx)
│   └── pages/CategoriasReconocimientoPage.tsx (+ .dom.test.tsx)  # muestra el rechazo de «Activar»
└── e2e/categorias-reconocimiento.spec.ts            # + escenario de puntuación fuera de tope y activación rechazada

design/CategoriasReconocimiento.dc.html              # solo si falta el texto del máximo (ver Constitution Check III)
```

**Structure Decision**: Aplicación web existente; todo en el módulo `Compensaciones` / `compensaciones`, sobre los archivos que ya implementan categorías y el servicio de 01b.1. No hay archivos de backend nuevos fuera de las pruebas.

## Decisiones de diseño

1. **Un solo origen del máximo**: `ParametrosDelModulo::puntuacionMaximaDeCategoria()` devuelve `min(valorVigente(PuntosPorColaborador, hoy), programado?->valor ?? PHP_INT_MAX)`. Lo usan los tres casos de uso y `ConsultarParametros`, por lo que servidor y pantalla nunca divergen.
2. **Dominio**: `PuntuacionBase` acepta el máximo en el constructor (por defecto 1000, el límite exterior) y falla con `DatosDeCategoriaInvalidos('puntuacion_base', 'La puntuación base debe estar entre 1 y {máximo}.')`. `Categoria::propuesta` y `DatosCategoriaDTO::comoPropuesta` propagan el máximo. La regla `between:1,1000` del `FormRequest` se mantiene como límite exterior (cubre no enteros y absurdos antes de consultar el parámetro).
3. **Se valida al guardar, siempre**: `ActualizarCategoria` construye la propuesta con el máximo antes de comparar diferencias, así que una categoría ya sobre el tope se rechaza aunque solo cambie otro campo (FR-010). La verificación va después del chequeo de versión (409 antes que 422), igual que hoy.
4. **Activación (FR-011)**: en `CambiarEstadoCategoria`, tras el chequeo de versión y del «mismo estado» (que sigue devolviendo sin cambios), si el estado pedido es `Activo` y `puntuacion > máximo` se lanza `DatosDeCategoriaInvalidos('puntuacion_base', 'Baja primero la puntuación a {máximo} o menos.')`, que el controlador ya traduce a 422 con el error en `puntuacion_base`. Desactivar no se toca. No se agrega excepción nueva.
5. **Máximo en el formulario**: `GET /api/compensaciones/parametros` agrega `meta.puntuacion_maxima_categoria` (así lo pide el plan de origen). El formulario lo consulta con una query propia en `categorias/` (misma URL, sin importar código interno de `parametros/`) y la refresca cada vez que se abre. Hoy el endpoint exige `parametros.gestionar` y el formulario `categorias.gestionar`; ambos pertenecen al Gestor/Administrador, así que no hay brecha. Si en el futuro se separan, el máximo debería moverse al detalle de categorías (riesgo anotado en `research.md`).
6. **Cambio de mes**: no hace falta job; al pasar al día 1, `valorVigente` ya devuelve el programado y el máximo se actualiza solo (spec, Edge Cases).
7. **Sin efectos colaterales (FR-007)**: ningún caso de uso toca `reconocimientos` ni `desafios`; los puntos se copian al registrarse (`modelo-de-datos.dbml`). La prueba lo verifica contando filas y puntos antes y después.

## Diseño de frontend

### Rutas
| Ruta | Pantalla (del spec) | Roles con acceso |
|---|---|---|
| `/compensaciones/categorias` (existente) | Listado: acción «Activar» con rechazo por tope | Todos ven; solo Administrador activa |
| Modal crear / editar (existente) | Formulario de categoría con «Máximo: {n} puntos» | Administrador (Gestor) |

Sin rutas nuevas.

### Componentes
- **Nuevos**: ninguno; el máximo se muestra con el texto auxiliar que ya existe bajo el campo (`<p className="text-[13px] text-muted">`).
- **Del design system**: `Modal`, `TextField`, `Alert`, `Button`, `ConfirmDialog` (sin cambios).

### Datos y estado
- `usePuntuacionMaxima()` consume `GET /api/compensaciones/parametros` (ver [contrato](contracts/categorias-puntaje-api.md)) y devuelve `{ maximo, bajaPorProgramado }`: `maximo` sale de `meta.puntuacion_maxima_categoria` y `bajaPorProgramado` es verdadero cuando ese máximo es menor que el `valor` vigente de la entrada `puntos_por_colaborador` de `data` (así se decide si mostrar la nota del cambio del mes siguiente). Con `refetchOnMount: 'always'` y sin reintento automático de escrituras.
- Si la consulta responde 403 (el permiso `parametros.gestionar` es distinto de `categorias.gestionar`) o falla, el formulario sigue usable sin número y decide el servidor.
- Mientras carga, el campo se muestra y el envío queda permitido solo si el valor pasa el límite exterior; el servidor decide. Si la consulta falla, se muestra el texto genérico sin número y el servidor sigue siendo la autoridad.
- Tras crear, editar o activar se invalida la familia `categorias` (ya existente); tras guardar parámetros ya se invalida `parametros-modulo`, y el máximo se vuelve a leer al abrir el formulario.

### Validación
- `crearCategoriaSchema(maximo)`: entero ≥ 1 y ≤ máximo; mensaje «La puntuación base debe estar entre 1 y {máximo}.» Sin máximo conocido, límite exterior 1000.
- 422 del servidor en `puntuacion_base` se mapea al campo (ya soportado por `problemaDeGuardado`); se conservan los valores.
- Activar con 422: el listado muestra «Baja primero la puntuación a {máximo} o menos.» en su alerta de error existente y la categoría sigue Inactiva.
