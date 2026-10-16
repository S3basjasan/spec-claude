# Implementation Plan: GP-03 · Información de desarrollo profesional

**Branch**: `012-desarrollo-profesional` | **Date**: 2026-10-15 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/012-desarrollo-profesional/spec.md` (con la sesión de clarificación 2026-10-15) y el insumo «Plan de implementación · GP-03» aportado por el usuario. Cuando difieren, **prevalece la spec clarificada** y la constitución (ver [research.md](research.md) §Divergencias con el insumo).

## Summary

Instalar la sección «Desarrollo» reservada por GP-01: formación académica y experiencia anterior como filas independientes (versión propia, sin eliminación) y la disponibilidad para movilidad como bloque único por ficha. El Administrador agrega y corrige; el Colaborador consulta lo propio con el permiso de consulta de ficha. Todo vive en el módulo `GestionPersonal` (misma ficha, mismo `tenant_id`), sin cálculos de brechas, sin vínculos y sin tocar el catálogo formativo.

Enfoque técnico: dos tablas nuevas (`formaciones_academicas`, `experiencias_anteriores`) y columnas aditivas de movilidad en `colaboradores` con **su propia versión** (no consume la versión de la ficha, para que movilidad y datos personales no se bloqueen entre sí); casos de uso explícitos por acción; reglas de fecha en un servicio de dominio que recibe el «hoy» del tenant; un lector por lote para GP-06; un adaptador de capacidades que declara `desarrollo` instalada; pestaña «Desarrollo» en `FichaContenido` con diálogos de alta y edición.

## Technical Context

**Language/Version**: PHP 8.3 / Laravel 13 (backend); TypeScript estricto, React 19 (frontend)

**Primary Dependencies**: las existentes (Sanctum, Eloquent; Vite, Tailwind 4, React Router, TanStack Query, Axios, React Hook Form, Zod). **Sin dependencias nuevas.**

**Storage**: SQLite (predeterminado de la constitución); aislamiento por columna `tenant_id`. Restricciones adicionales mediante triggers SQLite como en las features previas.

**Testing**: Pest (Feature/Unit; una prueba con procesos reales para concurrencia), Pint; Vitest + Testing Library; Playwright (E2E ya adoptado por GP-01)

**Target Platform**: Web (SPA + API REST), 320–1440 px, WCAG 2.2 AA

**Project Type**: web-service + SPA (monolito modular)

**Performance Goals**: la sección carga en una sola petición por ficha (3 consultas: formaciones, experiencias, bloque de movilidad); decenas de filas sin paginación (spec). Sin SLA inventados.

**Constraints**: sin eliminación de filas; sin transacciones largas; escrituras no reintentables automáticamente; errores públicos seguros; sin contenido de formación ni observaciones en logs.

**Scale/Scope**: 4 historias, 28 FR, 3 grupos de datos, 6 endpoints de escritura/lectura admin + 1 de lectura propia; ~10 componentes frontend.

## Constitution Check

*GATE: debe pasar antes de Phase 0. Reevaluado tras Phase 1.*

| Principio | Evaluación |
|---|---|
| I. Clean Architecture modular | Código en `backend/app/Modules/GestionPersonal/{Domain,Application,Infrastructure,Presentation}`; casos de uso explícitos (`AgregarFormacion`, `CorregirFormacion`, `AgregarExperiencia`, `CorregirExperiencia`, `ActualizarMovilidad`, `ObtenerDesarrollo`, `ObtenerMiDesarrollo`); controladores delgados; persistencia tras `DesarrolloRepositoryInterface`; Eloquent solo en Infrastructure. **Pasa.** |
| Rutas `/api/<modulo>/` | Se mantiene la excepción ya aprobada por precedente de GP-01 (`/api/colaboradores`, `/api/mi-ficha`); las nuevas rutas cuelgan de esos prefijos. **Pasa (precedente).** |
| Endpoints admin con Sanctum + `admin` | Escrituras y lectura administrativa bajo `auth:sanctum` + `admin`; la lectura propia solo `auth:sanctum` y el caso de uso exige asociación y permiso (precedente `mi-ficha`). **Pasa.** |
| Módulo no accede a tablas de otro módulo | Solo tablas de GestionPersonal; el módulo `Desarrollo` (catálogo formativo) no se toca. **Pasa.** |
| II. Frontend | `frontend/src/modules/gestionPersonal/desarrollo/` (api, hooks, components, validation, text); páginas sin Axios; TanStack Query, escrituras sin reintento; estados loading/empty/error/success; formularios RHF+Zod con errores por campo y botón bloqueado al enviar. **Pasa.** |
| III. Diseño | Sigue `design/GP-03_Desarrollo-profesional.html` y `DESIGN.md`; diferencias a registrar en `DESIGN.md` §9 (tarea de implementación). **Pasa.** |
| IV. Calidad / dependencias | Pest + Pint, ESLint + tsc + Vitest + build, E2E del flujo principal; sin dependencias nuevas. **Pasa.** |

Sin violaciones: no se completa «Complexity Tracking».

## Project Structure

### Documentation (this feature)

```text
specs/012-desarrollo-profesional/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── api-desarrollo.md
│   └── lectores.md
├── checklists/requirements.md
└── tasks.md            # /speckit-tasks
```

### Source Code (repository root)

```text
backend/
├── app/Modules/GestionPersonal/
│   ├── Domain/
│   │   ├── Entities/{FormacionAcademica,ExperienciaAnterior,Movilidad}.php
│   │   ├── ValueObjects/{EstadoDeFormacion,EstadoDeMovilidad}.php
│   │   ├── Repositories/DesarrolloRepositoryInterface.php
│   │   ├── Services/ReglasDeDesarrollo.php
│   │   └── Exceptions/{RegistroNoEncontrado,RegistroModificado}.php   # DatosInvalidos y FichaModificada se reutilizan
│   ├── Application/
│   │   ├── DTO/DesarrolloDTO.php
│   │   ├── Ports/LectorDeDesarrolloPort.php        # lector por lote para GP-06
│   │   ├── Services/ArmadorDeDesarrollo.php
│   │   └── UseCases/{ObtenerDesarrollo,ObtenerMiDesarrollo,AgregarFormacion,CorregirFormacion,AgregarExperiencia,CorregirExperiencia,ActualizarMovilidad}.php
│   ├── Infrastructure/
│   │   ├── Adapters/{LectorDeCapacidadesDesarrollo,LectorDeDesarrolloEloquent}.php
│   │   └── Persistence/Eloquent/{Models/{FormacionAcademicaModel,ExperienciaAnteriorModel}.php,Repositories/EloquentDesarrolloRepository.php}
│   └── Presentation/
│       ├── Controllers/DesarrolloController.php, MiDesarrolloController.php
│       ├── Requests/{GuardarFormacionRequest,GuardarExperienciaRequest,GuardarMovilidadRequest}.php
│       ├── Resources/DesarrolloResource.php
│       └── (Routes/api.php ampliado; Providers: binding + capacidad)
├── database/migrations/2026_10_17_*  (formaciones, experiencias, movilidad en colaboradores)
├── database/seeders/PersonalSeeder.php (ejemplos de los tres estados)
└── tests/{Feature,Unit}/GestionPersonal/ (Desarrollo*Test.php)

frontend/
├── src/modules/gestionPersonal/desarrollo/
│   ├── api.ts, text.ts, validation.ts (+ test)
│   ├── hooks/{useDesarrollo,useGuardarDesarrollo}.ts
│   └── components/{SeccionDesarrollo,ListaFormacion,ListaExperiencia,BloqueMovilidad,FormularioFormacion,FormularioExperiencia,FormularioMovilidad}.tsx
├── src/modules/gestionPersonal/fichas/components/FichaContenido.tsx   # panel «Desarrollo»
└── e2e/desarrollo.spec.ts
design/DESIGN.md (§9 diferencias, si las hay)
```

**Structure Decision**: aplicación web existente (backend + frontend); la funcionalidad es una carpeta nueva por capa dentro del módulo `GestionPersonal`/`gestionPersonal`, y la única edición de código de otra funcionalidad es el panel de `FichaContenido` y el binding de capacidades.

## Decisiones clave (detalle en research.md)

1. **Versión de movilidad propia** (`movilidad_version`), no la de la ficha: cumple FR-012 (bloques independientes) y FR-018 sin pisar `updated_by` de la ficha. Diverge del insumo, que reutilizaba `version`.
2. **Fechas**: inicio nunca futuro; finalización futura solo en formación; finalización ≥ inicio; «hoy» = `ContextoDeTenant::hoy()`. Diverge del insumo (que no restringía fechas futuras) por la clarificación 3.
3. **Capacidad `desarrollo`**: nuevo adaptador `LectorDeCapacidadesDesarrollo`, independiente de GP-02, que pasa `desarrollo` a `true`; se compone con las demás capacidades al integrarse GP-02 (ver research §Integración con GP-02).
4. **Sin idempotencia en POST de filas** (no hay identidad natural); doble envío evitado en UI con botón bloqueado; el error de red no reintenta.
5. **Desacoplamiento de la ficha**: la pestaña consulta su propio endpoint (`/desarrollo`), de modo que un fallo muestra error con Reintentar sin ocultar el resto de la ficha (FR-027).
6. **Sin eliminación**: no hay rutas DELETE ni funciones de borrado en el repositorio; FKs `restrictOnDelete`.
7. **404 uniforme** para ficha inexistente, de otro tenant o fila de otra ficha (FR-001).

## Diseño de frontend

### Rutas
| Ruta | Pantalla (del spec) | Roles con acceso |
|---|---|---|
| `/colaboradores/:colaboradorId` (pestaña «Desarrollo») | Desarrollo (detalle) | Administrador (con controles) |
| `/mi-ficha` (pestaña «Desarrollo») | Desarrollo (propia) | Colaborador con permiso de ficha (solo lectura) |

No se crean rutas nuevas: es una pestaña de `FichaContenido`. Los formularios son diálogos dentro de la sección.

### Componentes
- **Nuevos**: `SeccionDesarrollo` (compone los tres bloques y su estado de carga/error), `ListaFormacion`, `ListaExperiencia`, `BloqueMovilidad`, `FormularioFormacion`, `FormularioExperiencia`, `FormularioMovilidad` (diálogos).
- **Del design system**: `Button`, `Badge`, `Skeleton`, `ErrorState`, `Dialog`/campos de formulario de `shared/ui` y el patrón Pares etiqueta-valor (`DatoOpcional`) de fichas.

### Datos y estado
- `GET /api/colaboradores/{id}/desarrollo` y `GET /api/mi-ficha/desarrollo` (ver contracts/); clave `['colaboradores','desarrollo',id]` / `['mi-ficha','desarrollo']`.
- Mutaciones (POST/PATCH) sin reintento; al éxito se invalida la clave de desarrollo; en 409 se muestra «La información cambió» con «Actualizar datos» que refetch y conserva lo escrito.
- Estado de la pestaña activa local; sin parámetros de URL (no hay filtros ni paginación).

### Validación
- Zod replica límites y obligatoriedad (100/200/200/2000), recorte de espacios, fecha de inicio no futura, finalización futura solo en formación y finalización ≥ inicio; el «hoy» del tenant llega con la respuesta (`hoy`) para no depender del reloj del navegador.
- Mapeo de errores 422 (`errors.campo`) a los campos del diálogo correspondiente.
