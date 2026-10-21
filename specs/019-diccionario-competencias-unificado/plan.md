# Implementation Plan: Diccionario de competencias unificado

**Branch**: `019-diccionario-competencias-unificado` | **Date**: 2026-10-20 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/019-diccionario-competencias-unificado/spec.md`. Base de partida: `plan-diccionario.md` del autor, adaptado a las aclaraciones de la spec (nombres de categoría propios, edición concurrente, competencias incompletas, sin filtro de estado, paginación).

**Nota**: este plan **reemplaza** a los de 001 (competencias), 002 (comportamientos) y 003 (preguntas), que ya están implementados. La sección [Cambios respecto de lo construido](#cambios-respecto-de-lo-construido) lista lo que hay que modificar, añadir y quitar.

## Summary

Un solo módulo y una sola API (`/api/competencias`) para el diccionario completo: cada competencia reúne sus cuatro grados, 16 comportamientos y cuatro preguntas, nombrados con una **escala** por competencia (Letras, Números o Categorías con nombres propios). El administrador crea la competencia completa en una operación atómica, edita por secciones (competencia y grados, comportamientos, preguntas), aplica otra escala y activa/desactiva; los demás usuarios solo consultan las activas. Las competencias nunca se eliminan.

Enfoque técnico:

- **Esquema**: catálogo de escalas (`tipo_grados`, `tipo_grado_valores`), `competencia_grados` apuntando a la escala por `tipo_grado_valor_id`, comportamientos y preguntas colgando directamente de grado/competencia (desaparecen las tablas "registro"), tabla nueva `competencia_categorias` para los nombres propios, y `puesto_competencias.competencia_grado_id` en lugar de `grado_requerido`. Todo mediante **migraciones nuevas con traslado de datos**, sin editar las existentes.
- **Concurrencia**: una sola `competencias.version` que sube con cualquier escritura; los `PUT` exigen la versión y responden `409` si cambió.
- **Datos heredados**: las competencias con comportamientos o preguntas incompletos pasan a Inactivo en la migración y no se pueden activar hasta completarse.
- **API**: endpoints de edición por sección, `PATCH …/escala`, `PATCH …/estado` (con validación de completitud) y `GET /api/tipo-grados`. Desaparecen `/api/comportamientos` y `/api/preguntas`.
- **Frontend**: pantalla única de tarjetas con pestañas Competencia / Comportamientos / Preguntas, formularios modales y avisos emergentes; se retiran los módulos `comportamientos` y `preguntas`.
- **Compatibilidad**: la `EstructuraFacade` conserva las firmas actuales y añade las escalas y la completitud; los puestos siguen exponiendo el grado requerido como clave posicional A–D.

## Technical Context

**Language/Version**: PHP 8.3 (Laravel 13) en backend; TypeScript 6 estricto + React 19 en frontend

**Primary Dependencies**: Laravel Sanctum, React Router, TanStack Query, Axios, React Hook Form, Zod, Tailwind CSS 4, Vitest, Testing Library, jsdom y Playwright (todos ya instalados y aprobados). Ninguna dependencia nueva.

**Storage**: SQLite. Tablas existentes que cambian: `competencias`, `competencia_grados`, `competencia_comportamientos`, `competencia_preguntas`, `puesto_competencias`. Tablas nuevas: `tipo_grados`, `tipo_grado_valores`, `competencia_categorias`, `competencia_escala_historial`. Tablas que desaparecen: `competencia_comportamiento_registros`, `competencia_pregunta_registros`. Se conserva `competencia_estado_historial`.

**Testing**: Pest (Feature y Unit) para backend, incluida la migración de datos; Vitest + Testing Library (jsdom) para frontend; E2E con Playwright (`frontend/e2e/competencias.spec.ts` absorbe `comportamientos.spec.ts` y `preguntas.spec.ts`).

**Target Platform**: Navegadores modernos de escritorio y móvil (320–1440 px), temas claro y oscuro

**Project Type**: Aplicación web (API Laravel + SPA React)

**Performance Goals**: filtro de búsqueda actualizado en menos de 1 s (SC-003); acceso a comportamientos o preguntas en 3 pasos o menos (SC-004); el detalle completo (grados, 16 comportamientos, preguntas) se obtiene con una sola petición

**Constraints**: nombre ≤ 50, descripciones y preguntas ≤ 500, comportamientos ≤ 300, nombres de categoría ≤ 20; creación atómica; código inmutable; ninguna competencia incompleta puede estar activa; versión única por competencia para la edición concurrente; rol verificado en el backend; los demás módulos solo acceden por la Facade; errores sin información sensible; interfaz en español, WCAG 2.2 AA

**Scale/Scope**: cientos de competencias como máximo; 9 endpoints (sin `DELETE`), 1 pantalla con 4 modales, 7 competencias sembradas (3 escalas, una inactiva)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principio | Evaluación |
|---|---|
| I. Backend modular / Clean Architecture | PASA. Todo en `backend/app/Modules/Estructura/` con Domain, Application, Infrastructure y Presentation; un caso de uso por acción (`CrearCompetencia`, `ActualizarCompetencia`, `ActualizarComportamientos`, `ActualizarPreguntas`, `AplicarEscala`, `CambiarEstadoCompetencia`, `ListarCompetencias`, `ObtenerCompetencia`, `ListarEscalas`); controladores delgados; persistencia por repositorio. |
| I. Rutas bajo `/api/<modulo>/` | DESVIACIÓN JUSTIFICADA (ya registrada en 001). Las rutas son `/api/competencias` y `/api/tipo-grados` por indicación explícita del insumo y por continuidad con los consumidores existentes. Ver Complexity Tracking. |
| I. Admin = Sanctum + capacidad `admin` | PASA. Todas las escrituras usan `auth:sanctum` + middleware `admin`; la lectura solo `auth:sanctum`. La visibilidad de inactivas se decide en el borde de entrada según la capacidad `admin` del token. |
| I. Un módulo no accede a clases o tablas de otro | PASA. `created_by`/`updated_by`/`user_id` son enteros sin FK a `users`. Los demás módulos usan solo `EstructuraFacade`; `puesto_competencias` es del propio módulo Estructura. |
| I. Migraciones, factories y seeders según convención | PASA. Migraciones nuevas con traslado de datos y prueba Pest de la migración; `CompetenciaFactory` y `CompetenciasSeeder` pasan a competencias completas; `ComportamientosSeeder` y `PreguntasSeeder` se fusionan en el de competencias. |
| I. Auditoría en los límites correspondientes | PASA. El actor sale del token en el controlador; el repositorio escribe `created_by`, `updated_by` y los historiales de estado y de escala en la misma transacción que el cambio. |
| II. Frontend modular, `shared` sin módulos | PASA. Todo en `frontend/src/modules/estructura/competencias/`; los módulos `comportamientos` y `preguntas` se eliminan. Los componentes de `shared/ui` (Tabs, Modal, ConfirmDialog, Badge, Paginación) no importan módulos. |
| II. Una funcionalidad no importa internals de otra | PASA. `estructura/puestos` deja de depender de nada de competencias salvo su contrato público; usa solo `modules/auth/login/index.ts` (`useCurrentUser`, `Role`). |
| II. Axios solo en cliente compartido; TanStack Query; escrituras sin reintento; filtros en URL | PASA. Búsqueda, tipo y página en `useSearchParams`; todas las mutaciones sin reintentos; la tarjeta abierta, la pestaña activa y la vista previa de escala son estado local de pantalla (no compartible). |
| II. App Shell, loading/empty/error/success, formularios, confirmación accesible, WCAG | PASA. Estados completos; desactivar usa `ConfirmDialog` (`alertdialog`); los formularios bloquean el envío pendiente, conservan valores y muestran errores por campo; tarjetas, pestañas y modales accesibles por teclado. |
| II. Texto visible en español y centralizado; sin `any` | PASA. Todos los textos en `competencias/text.ts`. |
| II. E2E real por feature | PASA. `frontend/e2e/competencias.spec.ts` cubre crear, consultar por pestañas, editar una sección, escala y desactivar. |
| III. `design/` como fuente | PASA (diseño ya actualizado). Se añadió `design/Diccionario.dc.html` (artboard del zip ajustado a esta spec: dos columnas, descripción en la pestaña, confirmación al desactivar, Tipo en la edición, filtro por rol, paginación, estados de carga y error, aviso de contenido incompleto), se registró en `design/canvas.json` y se documentó en `design/DESIGN.md` (§5 «Listado de tarjetas con pestañas» y §9). `Competencias.dc.html` se conserva: sigue siendo el patrón genérico «Listado (tabla de datos)» que referencian otras secciones. |
| IV. Dependencias | PASA. Sin dependencias nuevas. |
| IV. Pruebas, seguridad | PASA. Pest, Vitest y E2E; validación en backend y frontend con los mismos mensajes; el rol se verifica en cada petición. |

**Re-evaluación tras Fase 1**: sin cambios; el diseño ya está actualizado. La eliminación de los módulos `comportamientos` y `preguntas` no es una enmienda constitucional: son funcionalidades, no principios.

## Cambios respecto de lo construido

Las funcionalidades 001, 002 y 003 ya existen. Para ajustarlas a este plan:

### Backend (`backend/app/Modules/Estructura/`, `backend/database/`, `backend/tests/`)

| Qué | Hoy | Debe quedar |
|---|---|---|
| Grados | `competencia_grados.grado` ('A'–'D') | `tipo_grado_valor_id` (FK a la escala); etiqueta calculada; se actualizan las mismas filas al cambiar de escala |
| Escalas | no existen | `tipo_grados` (Letras activa, Números, Categorías) y `tipo_grado_valores`; `GET /api/tipo-grados` |
| Nombres de categoría | no existen | tabla `competencia_categorias` (4 filas o ninguna por competencia) |
| Comportamientos | cabecera `competencia_comportamiento_registros` + `competencia_comportamientos` | solo `competencia_comportamientos` (siempre 4 por grado, exactamente 16) |
| Preguntas | cabecera `competencia_pregunta_registros` + `competencia_preguntas.registro_id` | `competencia_preguntas.competencia_id` (siempre 4) |
| Versión | `version` en competencias y en cada cabecera; el cambio de estado no la altera | una sola `competencias.version`, que sube con **cualquier** escritura (incluidos estado y escala) |
| Puestos | `puesto_competencias.grado_requerido` ('A'–'D') | `competencia_grado_id` (FK); el API de puestos conserva `grado_requerido` como clave posicional A–D |
| Estado | cualquier competencia se puede activar | activar exige contenido completo; las incompletas heredadas quedan Inactivas por la migración |
| Historial | `competencia_estado_historial` | se conserva; se añade `competencia_escala_historial` |
| Endpoints | `/api/competencias` (CRUD de datos+grados, estado), `/api/comportamientos` y `/api/preguntas` (con `competencias-disponibles`) | `/api/competencias` con edición por sección, `PATCH …/escala`, `PATCH …/estado` y `GET /api/tipo-grados`; **se eliminan** `/api/comportamientos` y `/api/preguntas` |
| Casos de uso | `Crear/Listar/Obtener/ActualizarComportamientos`, `…Preguntas`, `ListarCompetenciasDisponibles*` | se eliminan; se añaden `ActualizarComportamientos`/`ActualizarPreguntas` sobre la competencia, `AplicarEscala`, `ListarEscalas` |
| Dominio | entidades `RegistroComportamientos`, `RegistroPreguntas` y sus repositorios, modelos, requests, resources, DTO y excepciones (`CompetenciaYaTieneRegistro`, `CompetenciaYaTienePreguntas`, `PreguntasNoEncontradas`, `RegistroNoEncontrado`, `RegistroModificado`) | se eliminan; `Competencia` pasa a agregar grados, comportamientos, preguntas y categorías |
| Facade | `getComportamientos*`, `getPreguntas*`, `tieneComportamientosActivos`, `tienePreguntasActivas` por separado | firmas conservadas, ahora derivadas de la competencia; se añaden escala, etiquetas y completitud (ver `contracts/estructura-facade.md`) |
| Semillas | `CompetenciasSeeder`, `ComportamientosSeeder`, `PreguntasSeeder` | un `CompetenciasSeeder` con las 7 competencias completas, las tres escalas y una inactiva; `TiposGradoSeeder` para el catálogo |
| Pruebas | Pest de los tres módulos | reemplazar por pruebas del módulo unificado y de la migración de datos |

### Frontend (`frontend/src/`, `frontend/e2e/`, `design/`)

| Qué | Hoy | Debe quedar |
|---|---|---|
| Diseño | `design/Competencias.dc.html` en tabla; comportamientos y preguntas como filas desplegables | **Hecho**: `design/Diccionario.dc.html` (tarjetas con pestañas) y `design/DESIGN.md` actualizado; `Competencias.dc.html` queda como patrón de tabla |
| Módulos | `estructura/competencias`, `estructura/comportamientos`, `estructura/preguntas` | solo `estructura/competencias`; se eliminan los otros dos, sus rutas en `App.tsx`, `nav.tsx`, fixtures y pruebas |
| Listado | tabla con paginación, filtro de estado | cuadrícula de dos columnas de tarjetas desplegables; sin filtro de estado; "Limpiar filtros" |
| Detalle | `GradosPanel` | pestañas Competencia, Comportamientos y Preguntas con vista previa de escala |
| Formularios | página `CompetenciaFormPage` (grados A–D) | modal "Nueva competencia" completo y tres modales de edición por sección |
| Avisos | mensajes en la página | aviso emergente centrado con Aceptar para cada acción |
| Puestos | muestra el grado como letra | consume el grado con su etiqueta desde la Facade (la clave A–D sigue siendo la del contrato) |
| Pruebas | Vitest y E2E por módulo | un conjunto unificado |

## Project Structure

### Documentation (this feature)

```text
specs/019-diccionario-competencias-unificado/
├── plan.md              # Este archivo
├── research.md          # Fase 0
├── data-model.md        # Fase 1
├── quickstart.md        # Fase 1
├── contracts/           # Fase 1
│   ├── competencias-api.md
│   └── estructura-facade.md
├── checklists/
│   └── requirements.md
└── tasks.md             # Fase 2 (/speckit-tasks; NO lo crea /speckit-plan)
```

### Source Code (repository root)

```text
backend/
├── app/Modules/Estructura/
│   ├── Domain/
│   │   ├── Entities/            # Competencia (agrega grados, comportamientos, preguntas, categorías)
│   │   ├── ValueObjects/        # EscalaDeGrados, GradoDeCompetencia, EstadoCompetencia, TipoCompetencia
│   │   ├── Services/            # ReglasDeCompletitud, ReglasDeCategorias
│   │   ├── Repositories/        # CompetenciaRepositoryInterface, EscalaRepositoryInterface
│   │   └── Exceptions/          # ConflictoDeVersion, EstadoSinCambio, NombreDuplicado, CompetenciaIncompleta, EscalaSinCambio
│   ├── Application/
│   │   ├── UseCases/            # Crear, Actualizar, ActualizarComportamientos, ActualizarPreguntas,
│   │   │                        # AplicarEscala, CambiarEstado, Listar, Obtener, ListarEscalas
│   │   └── DTO/
│   ├── Infrastructure/Persistence/Eloquent/
│   │   ├── Models/              # CompetenciaModel, CompetenciaGradoModel, ComportamientoModel, PreguntaModel,
│   │   │                        # CategoriaModel, TipoGradoModel, TipoGradoValorModel, escala historial
│   │   └── Repositories/
│   ├── Presentation/
│   │   ├── Controllers/         # CompetenciaController, EscalaController
│   │   ├── Requests/            # GuardarCompetenciaRequest, GuardarComportamientosRequest, GuardarPreguntasRequest, …
│   │   ├── Resources/
│   │   └── Facades/             # EstructuraFacade (firmas conservadas)
│   └── Routes/api.php
├── database/
│   ├── migrations/              # 2026_10_23_* nuevas, con traslado de datos
│   ├── factories/CompetenciaFactory.php
│   └── seeders/                 # TiposGradoSeeder, CompetenciasSeeder
└── tests/{Feature,Unit}/Estructura/Competencias/

frontend/
├── src/modules/estructura/competencias/
│   ├── api.ts, text.ts, validation.ts, nav.tsx
│   ├── components/              # CompetenciaCard, PestanasCompetencia, VistaPreviaEscala, modales de formulario, AvisoEmergente
│   ├── hooks/                   # useCompetencias, useCompetencia, useGuardarCompetencia, useGuardarSeccion,
│   │                            # useAplicarEscala, useCambiarEstadoCompetencia, useEscalas, useListadoParams
│   └── pages/CompetenciasPage.tsx
├── e2e/competencias.spec.ts
└── (se eliminan modules/estructura/comportamientos y modules/estructura/preguntas)

design/
├── Diccionario.dc.html          # artboard de tarjetas con pestañas (nuevo)
├── canvas.json                  # registra el artboard nuevo
└── DESIGN.md                    # actualizado (§5 y §9)
```

**Structure Decision**: aplicación web con backend Laravel modular y SPA React, como el resto del repositorio. La unificación se resuelve dentro del módulo `Estructura` existente: un agregado `Competencia` y una sola funcionalidad de frontend `competencias`, sin nuevos módulos.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| Rutas `/api/competencias` y `/api/tipo-grados` fuera del patrón `/api/<modulo>/` | Continuidad con las rutas de 001 y con el contrato que ya consumen puestos y otros módulos; el insumo lo indica explícitamente | Mover todo a `/api/estructura/...` obliga a cambiar todos los consumidores sin beneficio para esta feature |
| Tabla adicional `competencia_categorias` fuera del DBML | La spec exige nombres de categoría propios por competencia que se conserven al cambiar de escala; el catálogo `tipo_grado_valores` es global y no puede guardarlos | Crear un `tipo_grado` por competencia contamina el catálogo (el nombre es único) y rompe la regla de un solo tipo activo |
| Columnas de auditoría y `version` fuera del DBML | La spec exige auditoría de autor/fecha y control de concurrencia, y las tablas existentes ya los usan | Omitirlos incumple FR-031 y FR-035 |

## Diseño de frontend

### Rutas
| Ruta | Pantalla (del spec) | Roles con acceso |
|---|---|---|
| `/competencias` | Listado de competencias (con detalle por pestañas, formularios y avisos como modales) | Administrador y Usuario; las acciones de gestión solo Administrador |

Se eliminan las rutas `/comportamientos` y `/preguntas`. Los parámetros de URL son `q`, `tipo` y `page`.

### Componentes
- **Nuevos**: `CompetenciaCard` (cabecera clicable con código, nombre, tipo y Activar/Desactivar), `PestanasCompetencia` (tablist de tres pestañas con edición por sección), `VistaPreviaEscala` (chips "Ver con escala:", Aplicar y Descartar), `FormularioCompetencia` (modal de creación), `FormularioSeccion` (modales de edición de competencia/grados, comportamientos y preguntas), `SelectorDeEscala` (radiogroup con campos de categorías), `AvisoEmergente`.
- **Del design system**: `Modal`, `ConfirmDialog` (alertdialog), `Badge`/etiqueta de tipo, `Button`, `IconButton`, `TextField`/`TextArea` con contador, `SelectField`, `SearchField`, `Paginación`, `Alert` neutro, `Skeleton`, `EmptyState` y `Tabs` ya usados en otros módulos.

### Datos y estado
- `GET /api/competencias` para el listado; `GET /api/competencias/{id}` al desplegar una tarjeta o abrir un formulario; `GET /api/tipo-grados` para formularios y vista previa (caché larga, es un catálogo).
- Mutaciones sin reintento: `POST /api/competencias`, `PUT /api/competencias/{id}`, `PUT …/comportamientos`, `PUT …/preguntas`, `PATCH …/escala`, `PATCH …/estado`.
- Tras cada mutación se invalida el listado y el detalle de esa competencia; la pestaña activa se conserva. Un `409` mantiene el formulario abierto con el aviso "recarga" y un botón para recargar los datos.
- Estado en URL: búsqueda, tipo y página. Estado local: tarjetas abiertas, pestaña por tarjeta y vista previa de escala por tarjeta.
- Paginación: el listado conserva el paginador del design system (12 por página, máximo 50), con el recuento de resultados totales bajo las tarjetas.

### Validación
- Zod replica las reglas del backend: nombre (1–50), descripción (1–500), tipo, escala, 4 grados (1–500 cada uno), 16 comportamientos (1–300), 4 preguntas (1–500) y, con Categorías, 4 nombres (1–20, sin repetir sin distinguir mayúsculas, tildes ni espacios). Texto solo con espacios cuenta como vacío.
- Errores `422` se asignan al campo por la ruta del error (`grados.2`, `comportamientos.1.3`, `categorias.0`, `preguntas.3`, `nombre`); los de nombre duplicado muestran "Ya existe una competencia con este nombre." junto al campo.
- Los errores solo se muestran tras el primer intento de guardar; los formularios conservan lo escrito ante cualquier error.
