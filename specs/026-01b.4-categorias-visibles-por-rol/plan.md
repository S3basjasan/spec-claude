# Implementation Plan: Categorías por unidad y visibles por rol (01b.4)

**Branch**: `026-01b.4-categorias-visibles-por-rol` | **Date**: 2026-11-03 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/026-01b.4-categorias-visibles-por-rol/spec.md` y plan de origen [plan-origen-01b.4-categorias-visibles-por-rol.md](plan-origen-01b.4-categorias-visibles-por-rol.md) (inc-01b), con `categorias_reconocimiento` de `modelo-de-datos.dbml` (columna nueva `unidades_ids`, JSON, mínimo 1).

## Summary

Cada categoría pasa a tener una o varias **unidades** asignadas (`unidades_ids`, JSON). El Gestor las elige al crear o editar (unidades activas o «Todas las unidades», que se resuelve en el servidor a las unidades activas de ese momento); sin unidades no se guarda (422). La lectura depende del rol: el Gestor (`admin`) ve todas con sus unidades; el líder ve todas las activas sin gestionar; el colaborador ve solo las activas de su unidad actual, sin ver unidades (el detalle de otra unidad responde 404). `CategoriasFacade::seleccionablesParaUnidad(unidadId)` ofrece a desafíos y reconocimientos las categorías activas, con competencia activa y asignadas a la unidad, que son las únicas que podrán aceptar (422 el resto). Los cambios de unidades se auditan con el campo `unidades`. Para leer unidades, unidad actual de la persona, tenant y líder se crean cuatro **puertos** (`EmpresaActualPort`, `ColaboradoresPort`, `UnidadesPort`, `EquipoPort`) con adaptadores sobre las Facades de Estructura, Gestión de Personal y Shared; `EquipoPort` es **provisional** (configuración de prueba). Un comando idempotente asigna todas las unidades activas a las categorías existentes. Frontend: selector múltiple de unidades en el formulario, columna «Unidades» y vista filtrada por servidor para el colaborador.

## Technical Context

**Language/Version**: PHP 8.3 / Laravel (versión del repositorio); TypeScript estricto, React

**Primary Dependencies**: las existentes (Sanctum, React Hook Form, Zod, TanStack Query, Axios); no se añade ninguna

**Storage**: SQLite en desarrollo. Una migración agrega `categorias_reconocimiento.unidades_ids` (JSON, nullable en BD, obligatorio al guardar). Las unidades, colaboradores y tenant de otros módulos solo se leen por su Facade, sin FK ni lectura de tablas ajenas

**Testing**: Pest (backend; los cuatro puertos se reemplazan por falsos); Vitest + Testing Library (frontend); E2E Playwright existente `frontend/e2e/categorias-reconocimiento.spec.ts` ampliado

**Target Platform**: Web (SPA + API)

**Project Type**: web-application (backend + frontend)

**Performance Goals**: el listado sigue en una página de 15 con **una** llamada a `UnidadesPort::porIds` por página (más una a `activas()` para «Todas»); `RendimientoListadoTest` se mantiene verde

**Constraints**: la visibilidad la decide el servidor (el cliente no filtra); asignación directa, sin herencia por jerarquía (FR-017); cambiar unidades no toca reconocimientos ni desafíos ya registrados; una categoría sin unidades válidas nunca es seleccionable; el resto de reglas de inc-01/01b.1/01b.2 no cambia

**Scale/Scope**: 1 migración, 1 comando, 4 puertos con 4 adaptadores, 1 objeto de valor, 1 caso de uso nuevo (unidades disponibles) y 5 modificados, 1 método de Facade, 1 campo auditado; 1 selector, 1 columna y 1 estado vacío en frontend

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principio | Evaluación |
|---|---|
| I. Backend modular / Clean Architecture | Pasa. Dominio: objeto de valor `UnidadesAsignadas` (≥1, enteros distintos, ordenados) y regla de visibilidad en la entidad, sin Laravel. Aplicación: casos de uso y puertos. Infraestructura: adaptadores que llaman **solo** a `EstructuraFacade`, `PersonalFacade` y `SharedFacade`; ninguna lectura de tablas de otros módulos. Controladores delgados (traducen excepciones; el único cambio de lógica es pasar el contexto del usuario). Rutas bajo `/api/compensaciones/`. Gestión sigue protegida por `auth:sanctum` + `admin` y `PoliticaDeAcceso` (403). |
| II. Frontend modular, accesible y tipado | Pasa. Cambios en `frontend/src/modules/compensaciones/categorias/` (`api.ts`, `hooks/`, `validation.ts`, `text.ts`, componentes). Sin `any`; todo texto en `text.ts`; errores por campo con valores conservados; envío bloqueado mientras se guarda; vistas con loading/empty/error/success; Axios solo en la capa compartida; selector accesible (casillas con etiqueta, teclado, foco visible). |
| III. Diseño como referencia | **Desviación registrada** (ver «Desviaciones»). `design/CategoriasPorRol.dc.html` ya existe; falta reflejar el selector dentro del formulario en `design/CategoriasReconocimiento.dc.html` y la sección en `design/DESIGN.md` **antes** de implementar la interfaz. |
| IV. Calidad y seguridad | Pasa. Cobertura Pest/Vitest/E2E en `quickstart.md`; validación en servidor (ids enteros distintos, existentes y activos); 404 (no 403) para no filtrar existencia a quien no ve la categoría; sin dependencias nuevas. |

**Complexity Tracking**: ver tabla al final (puertos con más métodos de los que usa esta spec; adaptador provisional). Re-evaluado tras Phase 1: sin cambios.

## Project Structure

### Documentation (this feature)

```text
specs/026-01b.4-categorias-visibles-por-rol/
├── 01b.4-categorias-visibles-por-rol.md             # Fuente de la spec (copiada)
├── plan-origen-01b.4-categorias-visibles-por-rol.md # Fuente del plan (copiada)
├── spec.md
├── plan.md                                          # Este archivo
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── categorias-unidades-api.md
├── checklists/requirements.md
└── tasks.md                                         # Lo crea /speckit-tasks
```

### Source Code (repository root)

```text
backend/
├── config/compensaciones_prueba.php                 # NUEVO · solo repo de prueba: equipos (líder → colaboradores), usuarios, tenant_id
├── database/migrations/2026_11_04_000001_add_unidades_ids_to_categorias_reconocimiento.php  # NUEVO
├── app/Modules/Compensaciones/
│   ├── Domain/
│   │   ├── Entities/Categoria.php                   # + unidades; diferenciasCon() incluye 'unidades'; esSeleccionable… sin cambios
│   │   ├── ValueObjects/UnidadesAsignadas.php       # NUEVO · ≥1 ids enteros positivos distintos, ordenados
│   │   ├── ValueObjects/FiltroCategorias.php        # + unidadId (filtra por asignación directa)
│   │   └── Exceptions/DatosDeCategoriaInvalidos.php # reutilizada (campo 'unidades_ids')
│   ├── Application/
│   │   ├── Ports/EmpresaActualPort.php              # NUEVO · tenantId()
│   │   ├── Ports/ColaboradoresPort.php              # NUEVO · cuentaDeUsuario, nombres, vigentes, unidadActual
│   │   ├── Ports/UnidadesPort.php                   # NUEVO · activas, porIds
│   │   ├── Ports/EquipoPort.php                     # NUEVO · esLider, equipoDe, liderDe, usuariosDe
│   │   ├── DTOs/DatosCategoriaDTO.php               # + unidadesIds | todasLasUnidades
│   │   ├── DTOs/CategoriaDTO.php                    # + unidades resueltas {id, nombre, activa}, aplicaATodas
│   │   ├── Services/ArmadorDeCategoriaDTO.php       # resuelve unidades con una llamada por lote
│   │   ├── Services/AuditoriaDeCategoria.php        # + campo 'unidades' al crear
│   │   ├── Services/CategoriasService.php           # + seleccionablesParaUnidad(int)
│   │   ├── Services/VisibilidadDeCategorias.php     # NUEVO · decide alcance por usuario: gestor | lider | unidad X | ninguno
│   │   ├── UseCases/CrearCategoria.php              # resuelve y exige unidades
│   │   ├── UseCases/ActualizarCategoria.php         # ídem; audita 'unidades'
│   │   ├── UseCases/ListarCategorias.php            # aplica el alcance por rol
│   │   ├── UseCases/ObtenerCategoria.php            # 404 si el alcance no la incluye
│   │   └── UseCases/ListarUnidadesDisponibles.php   # NUEVO
│   ├── Infrastructure/
│   │   ├── Adapters/EmpresaActualPorSesionOConfiguracion.php  # NUEVO
│   │   ├── Adapters/ColaboradoresDesdePersonal.php            # NUEVO
│   │   ├── Adapters/UnidadesDesdeEstructura.php               # NUEVO
│   │   ├── Adapters/EquipoDesdeConfiguracion.php              # NUEVO · PROVISIONAL
│   │   ├── Console/AsignarUnidadesIniciales.php               # NUEVO · compensaciones:asignar-unidades-iniciales
│   │   ├── Persistence/Eloquent/Models/CategoriaReconocimientoModel.php        # cast unidades_ids => array
│   │   ├── Persistence/Eloquent/Repositories/EloquentCategoriaRepository.php   # guarda/lee y filtra por unidad
│   │   └── Providers/CompensacionesServiceProvider.php        # enlaces de puertos y comando
│   ├── Presentation/
│   │   ├── Controllers/UnidadesController.php       # NUEVO · unidades-disponibles
│   │   ├── Controllers/CategoriaController.php      # pasa el usuario al listado/detalle
│   │   ├── Requests/GuardarCategoriaRequest.php     # unidades_ids | todas_las_unidades
│   │   ├── Resources/CategoriaResource.php          # unidades solo para gestor y líder
│   │   └── Facades/CategoriasFacade.php             # + @method seleccionablesParaUnidad
│   └── Routes/api.php                               # + GET /unidades-disponibles (admin)
└── tests/Feature/Compensaciones/
    ├── UnidadesDeCategoriaTest.php                  # crear/editar: ≥1, activas, todas, auditoría
    ├── VisibilidadPorRolTest.php                    # gestor / líder / colaborador / sin unidad / 404
    ├── SeleccionablesParaUnidadTest.php             # facade: inactivas, otra unidad, competencia inactiva, unidad inactiva
    ├── AsignarUnidadesInicialesTest.php             # idempotencia y auditoría
    ├── UnidadesDisponiblesTest.php                  # admin 200 / no admin 403
    └── (CrearCategoriaTest, ActualizarCategoriaTest, ListarCategoriasTest, ObtenerCategoriaTest, CategoriasFacadeTest, RendimientoListadoTest: ajustes por el campo obligatorio y el alcance)
    tests/Unit/Compensaciones/UnidadesAsignadasTest.php

frontend/
├── src/modules/compensaciones/categorias/
│   ├── api.ts                                       # + unidades en tipos y body; obtenerUnidadesDisponibles()
│   ├── hooks/useUnidadesDisponibles.ts              # NUEVO
│   ├── validation.ts (+ validation.test.ts)         # unidades_ids ≥ 1 (Zod)
│   ├── text.ts                                      # mensajes, «Aplica a», «Todas las unidades», vacío, aviso
│   ├── components/SelectorDeUnidades.tsx (+ .dom.test.tsx)  # NUEVO · «Todas» + casilla por unidad + alcance
│   ├── components/CategoriaFormModal.tsx (+ .dom.test.tsx)  # integra el selector
│   ├── components/CategoriasTabla.tsx                       # columna «Unidades» (solo gestor y líder)
│   ├── components/DetalleCategoria.tsx                      # lista de unidades
│   └── pages/CategoriasReconocimientoPage.tsx (+ .dom.test.tsx)  # banner por rol, estado vacío colaborador
├── e2e/categorias-reconocimiento.spec.ts            # + escenario: el Gestor asigna a una unidad; el colaborador de otra no la ve
design/
├── CategoriasReconocimiento.dc.html                 # + selector de unidades en el formulario
├── CategoriasPorRol.dc.html                         # ya existe (referencia de listado por rol)
└── DESIGN.md                                        # + sección «Categorías por unidad y rol»
```

**Structure Decision**: Aplicación web existente; todo en el módulo `Compensaciones` / `compensaciones`, sobre los archivos que ya implementan categorías. Los puertos y adaptadores siguen la convención de `CompetenciasPort` / `CompetenciasDesdeEstructura`. `config/compensaciones_prueba.php` vive en `backend/config/` porque es configuración de Laravel y lleva un comentario que indica que solo sirve al repositorio de prueba.

## Decisiones de diseño

1. **Datos**: `unidades_ids` JSON con enteros ordenados y sin repetir; asignación directa (FR-017). La relación con unidades es lógica (sin FK). Se escribe con el mismo `UPDATE` condicional por versión que ya protege las ediciones concurrentes.
2. **Un solo punto de verdad de visibilidad**: `VisibilidadDeCategorias` recibe el usuario y devuelve el alcance: `Gestor` (todas, con inactivas), `Lider` (todas las activas) o `Unidad(id)` / `Ninguno` (activas de esa unidad). `ListarCategorias` y `ObtenerCategoria` lo usan; el repositorio solo ejecuta el filtro (`FiltroCategorias::unidadId`). El alcance de líder se decide con `EquipoPort::esLider(colaboradorId)`; el de colaborador, con `ColaboradoresPort::cuentaDeUsuario` + `unidadActual`. Sin ficha o sin unidad → lista vacía; detalle → 404.
3. **Detalle**: sin alcance de gestor, una categoría fuera del alcance responde 404 igual que una inexistente (no revela existencia). La resolución histórica (reconocimientos ya registrados) usa `CategoriasFacade::obtener`, que no filtra por unidad.
4. **Resolución de «Todas»**: `todas_las_unidades: true` se resuelve en el caso de uso con `UnidadesPort::activas()` al momento de guardar, y se guardan los ids individuales (FR-011). Si llegan ambos campos, gana `todas_las_unidades` (el selector del cliente nunca los envía juntos). Cada id debe existir y estar activo (`UnidadesPort::porIds`); si no, 422 con «La unidad seleccionada ya no está activa.»; lista vacía o ausente, 422 con «Elige al menos una unidad.».
5. **Auditoría**: campo `unidades` con el JSON de ids ordenados (anterior → nuevo), igual que `indicadores`; `diferenciasCon` lo compara, de modo que guardar sin cambiar unidades no genera fila (FR-009). Al crear se registra con anterior nulo. El comando inicial también audita (actor nulo).
6. **`aplica_a_todas`**: derivado en la respuesta cuando las unidades asignadas incluyen todas las activas del momento (una sola lectura a `activas()` por petición); permite mostrar «Todas» sin guardar un indicador aparte. Las unidades inactivas asignadas se muestran con `activa: false`.
7. **`seleccionablesParaUnidad(unidadId)`**: devuelve `[]` si la unidad no existe o está inactiva; si no, categorías activas, con competencia activa y que contienen la unidad. Misma forma que `listarSeleccionables()`. El método existente sin parámetro se conserva sin cambios (lo siguen usando las features previas).
8. **Categorías sin unidades** (datos previos al comando): solo las ve el Gestor, con el aviso «Sin unidades asignadas», y nunca son seleccionables ni visibles para líder y colaborador. El comando `compensaciones:asignar-unidades-iniciales` es idempotente (solo toca categorías con `unidades_ids` nulo o vacío) y se ejecuta una vez después de migrar (queda en `quickstart.md`).
9. **Gerencia**: en el repo de prueba es el Administrador, así que hoy ve y puede gestionar todo; el modo «solo consulta» real de Gerencia queda fuera de esta spec (registrado en Assumptions de la spec y en `research.md`); SC-007 se verifica con el líder.
10. **Frontend**: el formulario de crear/editar incluye `SelectorDeUnidades` (casilla «Todas las unidades» + una por unidad activa, y el texto de alcance en unidades, sin conteo de personas). No se implementa el panel lateral independiente del mockup (ver «Desviaciones»).

## Desviaciones y decisiones aceptadas

Resueltas en el análisis de consistencia (`/speckit-analyze`) y reflejadas en la spec:

- **Panel de asignación del mockup (C5)**: `design/CategoriasPorRol.dc.html` muestra un panel lateral con «Guardar» y «Descartar» propios que guarda solo unidades, el mensaje «Asignación guardada. «{categoría}» aplica a {unidades}.» y «N personas» por unidad. Diferencias con lo que se implementa: (1) el selector vive dentro del formulario (modal) de crear y editar, que ya tiene control de versión y un solo «Guardar»; (2) se reutiliza el mensaje de éxito existente al guardar una categoría; (3) no hay conteo de personas. Se actualizan `design/CategoriasReconocimiento.dc.html` y `design/DESIGN.md` antes de la interfaz (T002). Alternativa no incluida: `PATCH /categorias/{id}/unidades`.
- **Personas por unidad (C1, opción A)**: el selector muestra el alcance en unidades y «Nadie la verá»; sin conteo de personas porque no existe un método publicado para contarlas (FR-014 y Assumptions de la spec ya lo dicen). Seguimiento: pedir a Gestión de Personal un conteo por unidad.
- **Gerencia (C2)**: en el repositorio de prueba es el Administrador, que también gestiona; SC-007 y FR-005 se verifican con el líder. Un modo de solo consulta llegará con el módulo de autorización.
- **Reconocer y asignar desafíos (C3)**: FR-007, SC-002 y US3 se reformularon como «consulta disponible» (`seleccionablesParaUnidad`). El 422 y la lista vacía con mensaje los implementan 02.3 y 03.1.
- **Consulta sin control de acceso por usuario (C6)**: es un método de Facade interno; el AC4 de US4 se quitó de la spec.
- **Unidad inactiva (C4)**: si la unidad del colaborador está inactiva, su alcance es `Ninguno` (no ve categorías); `VisibilidadDeCategorias` consulta el estado de la unidad con `UnidadesPort::porIds`, y al reactivarla vuelve a ver las suyas.


## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| Puertos `EmpresaActualPort`, `ColaboradoresPort` y `EquipoPort` con métodos que esta spec no usa (`nombres`, `vigentes`, `equipoDe`, `liderDe`, `usuariosDe`, `tenantId` fuera del adaptador de Personal) | El plan de origen (§3) y `00-decisiones-compartidas.md` los fijan como contrato único para las specs 02.x y 03.x; crearlos hoy evita rehacer enlaces | Crear solo los métodos usados obligaría a cada spec posterior a ampliar y re-probar los puertos; el costo extra aquí es de firmas y adaptadores triviales |
| `EquipoDesdeConfiguracion` provisional con `config/compensaciones_prueba.php` | Gestión de Personal aún no publica líder/equipo; hace falta saber si un usuario es líder | Adivinar el líder por rol rompería la regla «su alcance es su equipo»; el adaptador se reemplaza cambiando solo el enlace en el `ServiceProvider` |

## Diseño de frontend *(si la feature tiene UI)*

### Rutas
| Ruta | Pantalla (del spec) | Roles con acceso |
|---|---|---|
| Existente de categorías de reconocimiento (módulo Compensaciones, pestaña «Categorías») | Categorías de reconocimiento (listado por rol) | Todos los autenticados; el contenido y las acciones dependen del rol |

### Componentes
- **Nuevos**: `SelectorDeUnidades` (casilla «Todas las unidades», una casilla por unidad activa, texto de alcance y error junto al campo); `useUnidadesDisponibles`.
- **Modificados**: `CategoriaFormModal` (integra el selector), `CategoriasTabla` (columna «Unidades» solo para Gestor y líder), `DetalleCategoria` (lista de unidades), `CategoriasReconocimientoPage` (banner por rol y estado vacío del colaborador).
- **Del design system**: patrón Listado y Formulario de `design/DESIGN.md`; casillas, banner y estados existentes.

### Datos y estado
- `GET /categorias` (alcance por rol, sin filtrar en el cliente), `GET /categorias/{id}`, `GET /unidades-disponibles` (solo Gestor), `POST`/`PUT /categorias` (ver `contracts/categorias-unidades-api.md`).
- Tras crear o editar se invalida la lista y el detalle de categorías; la consulta de unidades disponibles se refresca al abrir el formulario.
- Estado en URL: filtros y paginación existentes (`q`, `estado`, `competencia_id`, `page`); el selector de unidades no se persiste en la URL.

### Validación
- `unidades_ids`: al menos una, o «Todas las unidades» marcada; «Todas» se desmarca al quitar cualquier unidad.
- Mapeo de errores 422: `unidades_ids` → error bajo el selector («Elige al menos una unidad.» / «La unidad seleccionada ya no está activa.»); el resto de campos como hoy; lo escrito se conserva.
- Estados: loading, vacío del colaborador («Todavía no tienes categorías asignadas»), error con reintento, sin permisos, aviso del Gestor «Sin unidades asignadas» / «sin unidades activas».
