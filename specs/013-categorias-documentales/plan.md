# Implementation Plan: Catálogo de categorías documentales

**Branch**: `013-categorias-documentales` | **Date**: 2026-10-16 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/013-categorias-documentales/spec.md` y el insumo técnico «Catálogo de categorías documentales» aportado por el usuario. El insumo declara que tablas y rutas son una *propuesta* que debe contrastarse con el repositorio; donde difiere de la realidad del repositorio o de la constitución, prevalecen estas últimas (ver [research.md](research.md) §Divergencias con el insumo).

## Summary

Instalar un catálogo plano de categorías documentales por tenant, administrado por el Administrador de Talento Humano: crear, listar (búsqueda, filtro de estado, paginación), corregir el nombre, desactivar y reactivar; sin eliminación. Vive en el módulo `GestionPersonal` junto a fichas, ingresos y desarrollo. Los documentos (GP-05, aún inexistente) referenciarán la categoría por id y leerán el nombre vigente; el catálogo funciona sin tabla documental.

Enfoque técnico: una tabla `categorias_documentales` con `tenant_id`, `nombre_clave` único por tenant (NFC + recorte + minúsculas, conserva tildes), `nombre_busqueda` sin tildes, `estado` y `version` optimista, más auditoría en la propia fila. Tres casos de uso de escritura (`CrearCategoria`, `EditarCategoria`, `CambiarEstadoCategoria`) y dos de lectura (listar, consultar), repositorio Eloquent tras una interfaz de dominio. Un puerto/fachada de lectura (`CatalogoDeCategoriasPort` / `PersonalFacade`) entrega a GP-05 las categorías activas, la resolución de nombres vigentes y una confirmación de «categoría activa» que toma el mismo bloqueo de fila que el cambio de estado, dentro de la transacción de publicación del documento.

## Technical Context

**Language/Version**: PHP 8.3 / Laravel 13 (backend); TypeScript estricto, React 19 (frontend)

**Primary Dependencies**: las existentes (Sanctum, Eloquent; Vite, Tailwind 4, React Router, TanStack Query, Axios, React Hook Form, Zod). **Sin dependencias nuevas.**

**Storage**: SQLite (predeterminado de la constitución) con aislamiento por columna `tenant_id`, igual que las fichas. El insumo propone PostgreSQL con esquema por tenant: no existe en el repositorio y no se introduce; el diseño de la tabla y de las consultas es portable (ver research D1).

**Testing**: Pest (Feature/Unit; concurrencia con el patrón determinista de GP-03), Pint; Vitest + Testing Library; Playwright (E2E ya adoptado)

**Target Platform**: Web (SPA + API REST), 320–1440 px, WCAG 2.2 AA

**Project Type**: web-service + SPA (monolito modular)

**Performance Goals**: listado con búsqueda y filtro en una consulta indexada; catálogo de decenas de categorías por tenant, resultados percibidos al instante (SC-008). Sin SLA inventados.

**Constraints**: sin eliminación; sin transacciones largas (el bloqueo de categoría nunca se mantiene durante la transferencia de archivos); escrituras no reintentables automáticamente; errores públicos seguros; la desactivación no consulta documentos ni los actualiza en masa.

**Scale/Scope**: 4 historias, 30 FR, 1 entidad, 5 endpoints, 1 pantalla con 2 diálogos, 1 puerto de lectura para GP-05.

## Constitution Check

*GATE: debe pasar antes de Phase 0 research. Reevaluado tras Phase 1.*

| Principio | Evaluación |
|---|---|
| I. Clean Architecture modular | Código en `backend/app/Modules/GestionPersonal/{Domain,Application,Infrastructure,Presentation}` (el insumo nombra `Modules/Personal`, que no existe; se conserva la ubicación equivalente real). Casos de uso explícitos (`CrearCategoria`, `EditarCategoria`, `CambiarEstadoCategoria`, `ListarCategorias`, `ObtenerCategoria`); controlador delgado sin consultas; persistencia tras `CategoriaRepositoryInterface`; Eloquent solo en Infrastructure. **Pasa.** |
| Rutas `/api/<modulo>/` | El insumo fija `/api/categorias-documentales`. Es la misma excepción por precedente ya aprobada para `/api/colaboradores` y `/api/mi-ficha` (GP-01..03): el prefijo es el recurso del módulo. **Pasa (precedente; registrado aquí).** |
| Endpoints admin con Sanctum + `admin` | Todas las rutas bajo `auth:sanctum` + `admin`. El insumo menciona la capacidad `personal.categorias.gestionar`; el repositorio no tiene un sistema de capacidades por permiso (solo los tokens `admin` / `user`) y la constitución exige la capacidad `admin`. Se implementa con `admin` y `personal.categorias.gestionar` queda como nombre documental equivalente. **Pasa.** |
| Módulo no accede a tablas de otro módulo | Solo tabla propia. GP-05 consume por `PersonalFacade`/puerto; no importa modelos. La FK `restrictOnDelete` desde documentos la crea la migración de GP-05 (esta feature no consulta ni referencia una tabla documental). **Pasa.** |
| II. Frontend | `frontend/src/modules/gestionPersonal/categorias/` (api, hooks, components, pages, validation, text); páginas sin Axios; TanStack Query, escrituras sin reintento; filtros/página en la URL; loading/empty/error/success; formularios RHF+Zod con errores por campo y botón bloqueado; confirmación accesible. **Pasa.** |
| III. Diseño | Sigue `design/GP-05_Documentos-del-colaborador.html` (pantalla Categorías) y `DESIGN.md`. Diferencias (sin columna «Documentos»; paginación; «Editar nombre»→«Editar»; reactivar con confirmación) se registran en `DESIGN.md` §9 como tarea. **Pasa.** |
| IV. Calidad / dependencias | Pest + Pint, ESLint + tsc + Vitest + build, E2E del flujo principal; sin dependencias nuevas; sin secretos ni datos reales. **Pasa.** |

Sin violaciones: no se completa «Complexity Tracking».

## Project Structure

### Documentation (this feature)

```text
specs/013-categorias-documentales/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── api-categorias.md
│   └── lectores.md
├── checklists/requirements.md
└── tasks.md            # /speckit-tasks
```

### Source Code (repository root)

```text
backend/
├── app/Modules/GestionPersonal/
│   ├── Domain/
│   │   ├── Entities/CategoriaDocumental.php
│   │   ├── ValueObjects/EstadoDeCategoria.php
│   │   ├── Repositories/CategoriaRepositoryInterface.php
│   │   ├── Services/ReglasDeCategoria.php
│   │   └── Exceptions/{CategoriaNoEncontrada,CategoriaModificada,NombreDeCategoriaDuplicado,CategoriaInactiva}.php   # DatosInvalidos y EscrituraDeshabilitada se reutilizan
│   ├── Application/
│   │   ├── DTO/{FiltroCategoriasDTO,ListadoCategoriasDTO}.php
│   │   ├── Ports/CatalogoDeCategoriasPort.php
│   │   └── UseCases/{CrearCategoria,EditarCategoria,CambiarEstadoCategoria,ListarCategorias,ObtenerCategoria}.php
│   ├── Infrastructure/
│   │   ├── Adapters/CatalogoDeCategoriasEloquent.php
│   │   └── Persistence/Eloquent/{Models/CategoriaDocumentalModel.php,Repositories/EloquentCategoriaRepository.php}
│   └── Presentation/
│       ├── Controllers/CategoriaDocumentalController.php
│       ├── Requests/{GuardarCategoriaRequest,CambiarEstadoCategoriaRequest,ListarCategoriasRequest}.php
│       ├── Resources/CategoriaDocumentalResource.php
│       └── (Routes/api.php ampliado; PersonalFacade y PersonalService ampliados; Providers: bindings)
├── database/migrations/2026_10_18_000001_create_categorias_documentales_table.php
├── database/{factories/CategoriaDocumentalFactory.php,seeders/PersonalSeeder.php}   # Contratos, Certificados e Inactiva
└── tests/{Feature,Unit}/GestionPersonal/ (Categoria*Test.php)

frontend/
├── src/modules/gestionPersonal/categorias/
│   ├── api.ts, text.ts, validation.ts (+ test), nav.tsx, index.ts   # index: key y hook de activas para GP-05
│   ├── hooks/{useCategorias,useCategoriasParams,useGuardarCategoria}.ts
│   ├── components/{CategoriasTabla,FormularioCategoria,ConfirmarEstadoCategoria}.tsx
│   └── pages/CategoriasPage.tsx
├── src/App.tsx            # ruta /categorias-documentales (admin) y entrada de navegación
├── src/shared/ui/icons.tsx  # icono de carpeta si el sistema no lo tiene
└── e2e/categorias-documentales.spec.ts
design/DESIGN.md (§9 diferencias)
```

**Structure Decision**: aplicación web existente (backend + frontend); la funcionalidad es una carpeta nueva por capa dentro de `GestionPersonal`/`gestionPersonal`. Las únicas ediciones de código de otras funcionalidades son el registro de rutas, el binding en el proveedor, la fachada del módulo, el semillero y `App.tsx`.

## Decisiones clave (detalle en research.md)

1. **Unicidad** por `(tenant_id, nombre_clave)` con clave NFC + recorte + minúsculas que conserva tildes; vale también para categorías inactivas. Dos creaciones simultáneas: la restricción UNIQUE decide y la perdedora recibe 422 de `nombre`.
2. **Búsqueda** sobre `nombre_busqueda` (sin tildes, minúsculas), distinta de la clave de unicidad. Índice `(tenant_id, estado, nombre_busqueda, id)`.
3. **Concurrencia** por `version`: toda actualización lleva `WHERE id, tenant_id, version`; 0 filas ⇒ 404 si no existe o 409 si cambió. Una versión obsoleta nunca se «equipara» por resultado visual; un cambio sin efecto con versión vigente devuelve 200 sin escribir.
4. **Coordinación con la carga documental** sin bloqueo largo: `confirmarCategoriaActiva` ejecuta, dentro de la transacción de publicación del consumidor, un `UPDATE … SET version = version WHERE id AND tenant_id AND estado = 'Activa'` (toma el bloqueo de fila en cualquier motor; 0 filas ⇒ `CategoriaInactiva`); el cambio de estado compite por el mismo bloqueo con su propio `UPDATE`. No se mantiene bloqueo durante la transferencia del archivo.
5. **Sin consulta de documentos**: desactivar no cuenta ni modifica documentos; el catálogo no referencia tablas documentales. La FK `restrictOnDelete` la añade GP-05.
6. **Sin eliminación** ni rutas DELETE ni método de borrado en el repositorio.
7. **404 uniforme** para categoría inexistente o de otro tenant; **403** para el Colaborador (middleware `admin`) sin revelar existencia.
8. **Auditoría en la propia fila** (`created_by`, `updated_by`, timestamps; `updated_*` solo cambian con cambios reales); sin tabla ni pantalla de historial.
9. **Lectura para consumidores** por puerto y `PersonalFacade` (`getCategoriasActivas`, `getCategoriasPorIds`, `confirmarCategoriaActiva`); cada contexto aplica su propia regla de estado: selector de cargas = solo activas; lista documental = nombre vigente de cualquiera.

## Diseño de frontend

### Rutas
| Ruta | Pantalla (del spec) | Roles con acceso |
|---|---|---|
| `/categorias-documentales` | Categorías documentales (listado + formulario modal + confirmación) | Administrador |

La navegación lateral muestra «Categorías documentales» solo al Administrador; el backend sigue siendo la autoridad.

### Componentes
- **Nuevos**: `CategoriasPage`, `CategoriasTabla`, `FormularioCategoria` (modal único de crear/editar), `ConfirmarEstadoCategoria` (desactivar con aviso «Sus documentos se conservarán»; reactivar con confirmación neutra).
- **Del design system**: `Button`, `Badge`, `Modal`, `ConfirmDialog` (tono `primary` para reactivar), `TextField`, `SearchField`, `SelectField`, `Pagination`, `EmptyState`, `ErrorState`, `Skeleton`, `Alert`.

### Datos y estado
- `GET /api/categorias-documentales` (listado y selector con `estado=Activa`), `POST`, `PATCH /{id}` y `PATCH /{id}/estado` (ver [contracts/api-categorias.md](contracts/api-categorias.md)).
- Clave de caché `['categorias-documentales', …]`; tras cualquier mutación se invalida toda la familia, de modo que listados y selectores abiertos se refrescan. Las escrituras no se reintentan.
- Estado en URL: `q`, `estado` (`Activa` por defecto, `Inactiva`, `Todas`) y `page`.
- 409 ⇒ «La información cambió» con «Actualizar datos» (refetch conservando lo escrito); la versión enviada es siempre la de la fila vigente en el momento de enviar.

### Validación
- Zod replica: nombre obligatorio tras recortar, máximo 100; la unicidad la decide el servidor (422 `nombre`) y se muestra junto al campo.
- Mapeo de errores 422 al campo `nombre`; 404 «La categoría ya no existe»; 503/red con mensaje general que conserva lo escrito.
