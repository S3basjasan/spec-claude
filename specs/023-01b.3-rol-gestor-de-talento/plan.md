# Implementation Plan: Nombre del rol «Gestor de Talento» (01b.3)

**Branch**: `023-01b.3-rol-gestor-de-talento` | **Date**: 2026-11-03 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/023-01b.3-rol-gestor-de-talento/spec.md` y plan de origen `planes/01b.3-rol-gestor-de-talento.md` (inc-01b). Solo aplica al repositorio de prueba: en el proyecto real inc-01 ya nace con este nombre.

## Summary

Refactorización de nombre y de punto de decisión de acceso, sin cambios de datos ni de API. (1) Los textos visibles del módulo de categorías de reconocimiento dejan de hablar de «Talento Humano» o de «Administrador» como quien gestiona y pasan a decir «Gestor de Talento». (2) `PoliticaDeAcceso` reemplaza `puedeGestionar()` por `puede($usuario, $permiso)` con la constante `categorias.gestionar`; en el repositorio de prueba se cumple cuando el token tiene la capacidad `admin`. No se crea ni se renombra ningún rol, ni se renombra `admin`. Ver categorías no lleva permiso.

## Technical Context

**Language/Version**: PHP 8.3 / Laravel (backend); TypeScript estricto, React (frontend)

**Primary Dependencies**: las existentes; no se añade ninguna

**Storage**: N/A (ninguna tabla ni migración)

**Testing**: Pest (backend), Vitest + Testing Library (frontend), Playwright E2E existente `frontend/e2e/categorias-reconocimiento.spec.ts`

**Target Platform**: Web (SPA + API)

**Project Type**: web-application (backend + frontend)

**Performance Goals**: N/A (sin cambio de comportamiento)

**Constraints**: API y respuestas idénticas; pruebas existentes de inc-01 siguen pasando; no tocar roles ni textos de otros módulos (posiciones, fichas, categorías documentales)

**Scale/Scope**: 1 servicio de acceso, 3 puntos de uso en backend, 1 archivo de textos y 1 prueba de frontend, 1 maqueta de diseño

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principio | Evaluación |
|---|---|
| I. Backend modular / Clean Architecture | Pasa. `PoliticaDeAcceso` sigue en `Application/Services` del módulo; los controladores siguen delgados y la autorización en el límite de entrada. No hay acceso a otros módulos. Las rutas de gestión mantienen `auth:sanctum` + `admin` (admin sigue siendo la capacidad del repositorio de prueba). |
| II. Frontend modular, accesible, tipado | Pasa. Texto centralizado en `text.ts`, en español; sin `any`; sin nuevas dependencias. |
| III. Diseño como referencia | Pasa con acción: el texto cambia, así que se actualiza primero `design/CategoriasReconocimiento.dc.html` (selector de rol, datos de ejemplo y estado vacío) y se registra aquí. |
| IV. Calidad y seguridad | Pasa. Cambio de comportamiento interno cubierto por prueba Pest nueva de `puede()`; el resto lo cubren las pruebas existentes. Sin secretos ni dependencias nuevas. |

Sin violaciones; no se requiere Complexity Tracking. Re-evaluado tras el diseño: sin cambios.

## Project Structure

### Documentation (this feature)

```text
specs/023-01b.3-rol-gestor-de-talento/
├── 01b.3-rol-gestor-de-talento.md   # Fuente (copiada)
├── spec.md
├── plan.md                          # Este archivo
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── politica-de-acceso.md
├── checklists/requirements.md
└── tasks.md                         # Lo crea /speckit-tasks
```

### Source Code (repository root)

```text
backend/
├── app/Modules/Compensaciones/
│   ├── Application/Services/PoliticaDeAcceso.php        # puede($usuario, $permiso) + constantes; se retiran puedeGestionar() y puedeVer()
│   ├── Presentation/Controllers/CategoriaController.php # usa puede($usuario, CATEGORIAS_GESTIONAR)
│   ├── Presentation/Controllers/CompetenciasController.php
│   └── Presentation/Resources/CategoriaResource.php     # auditoría solo con categorias.gestionar
└── tests/Feature/Compensaciones/                        # + PoliticaDeAccesoTest.php; las demás pruebas no cambian

frontend/
├── src/modules/compensaciones/categorias/
│   ├── text.ts                                          # «Gestor de Talento» en los textos visibles
│   └── pages/CategoriasReconocimientoPage.dom.test.tsx  # texto esperado del estado vacío
└── e2e/categorias-reconocimiento.spec.ts                # solo si comprueba el texto cambiado

design/CategoriasReconocimiento.dc.html                  # selector de rol, avatar de ejemplo, estado vacío
```

**Structure Decision**: Aplicación web existente (backend + frontend). Todo el cambio vive en el módulo `Compensaciones` / `compensaciones` y en una maqueta de `design/`. No se crean carpetas ni archivos de producción nuevos; solo una prueba Pest.

## Decisiones de diseño

1. **`puede($usuario, $permiso)`**: devuelve verdadero solo para permisos conocidos; hoy el único es `categorias.gestionar`, resuelto con `tokenCan('admin')`. Un permiso desconocido devuelve falso (denegar por defecto). `parametros.gestionar` lo agrega 01b.1.
2. **Retiro de `puedeGestionar()`**: los 3 usos (listado, filtro de competencias, auditoría en el recurso) pasan a `puede($usuario, PoliticaDeAcceso::CATEGORIAS_GESTIONAR)`. `puedeVer()` también se retira: ver categorías no lleva permiso y nada lo usa. Sin método duplicado.
3. **Middleware `admin` en rutas**: se conserva; la comprobación fina vive en `PoliticaDeAcceso` (plan de origen).
4. **Textos**: «Cuando Talento Humano cree categorías…» → «Cuando el Gestor de Talento cree categorías…». Solo se ajustan los comentarios de `PoliticaDeAcceso.php`; los demás comentarios no se tocan. Los identificadores (`esAdmin`, `role === 'admin'`) no se renombran porque `admin` sigue siendo la capacidad.
5. **Fuera de alcance**: «Administrador de Talento Humano» en posiciones, fichas y categorías documentales; textos de otros módulos.

## Diseño de frontend

### Rutas
| Ruta | Pantalla (del spec) | Roles con acceso |
|---|---|---|
| Existente: categorías de reconocimiento | Avisos y textos del rol en categorías | Todos consultan; gestiona el Gestor de Talento |

### Componentes
- **Nuevos**: ninguno.
- **Del design system**: los existentes de la página (estado vacío, tabla, detalle).

### Datos y estado
- Endpoints sin cambios; no hay caché ni estado en URL nuevos.

### Validación
- Sin reglas nuevas; los errores 422 se mapean igual que antes.
