# Implementation Plan: Rutas y planes de carrera

**Branch**: `019-rutas-carrera` | **Date**: 2026-10-20 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/019-rutas-carrera/spec.md`, con el insumo técnico `plan-rutas.md` (carpeta `specify y plan`) y dos tableros como referente visual primordial: **`design/RutasAdmin.dc.html`** (administrador) y **`design/RutasColaborador.dc.html`** (colaborador). Se siguen tal cual; las diferencias con la spec están registradas abajo.

**Referencia única del administrador**: `RutasAdmin.dc.html`, `RutasEditor.dc.html` y `RutasParticipantes.dc.html` son el mismo prototipo (593 líneas, mismo código; solo cambian la vista inicial —lista, editor en Escalera o editor en Participantes— y la altura del lienzo). Se toma `RutasAdmin.dc.html` por ser el más completo: contiene la lista de rutas, el modal «Nueva ruta», el editor con la pestaña Escalera de niveles (detalle en tres columnas) y la pestaña Participantes (inscribir, retirar y cantera). Los otros dos se consultan solo como capturas de esos estados.

## Summary

Séptima feature de `Desarrollo`. El Administrador define **rutas de carrera** (Especialista, Gerencial, Interáreas, Jóvenes profesionales) como escaleras de niveles ligados a puestos de Estructura Empresarial, con experiencia mínima por nivel y un **diferencial calculado** contra el nivel anterior; las publica, archiva, **inscribe** colaboradores con nivel actual y objetivo, registra el nivel alcanzado, retira inscripciones y ve la **cantera** por nivel y el **historial**. El Colaborador ve **Mi ruta de carrera** (escalera con actual y objetivo, detalle de lo exigido, «Ver mi adecuación», rutas Vigentes). La ruta es una guía: nunca modifica el puesto del colaborador.

Decisiones de arquitectura:

- **Clean Architecture dentro de `Desarrollo`**, como Actividades y Reportes. Reglas (transiciones de estado, publicación, inscripción, nivel alcanzado, diferencial, cantera) en `Domain` puro; casos de uso explícitos en `Application`; repositorio por contrato; controladores delgados.
- **Cuatro tablas nuevas** (`rutas`, `ruta_niveles`, `ruta_inscripciones`, `ruta_historial`) con `tenant_id`, sin soft deletes, sin `cascade`, sin DELETE. Competencias y diferencial **no se guardan**: se leen del puesto en cada consulta mediante un puerto cuyo adaptador usa `EstructuraFacade`.
- **Facades de otros módulos solo por puertos**: `EstructuraFacade` (puestos, perfil, departamentos = unidades) y `PersonalFacade` (colaboradores vigentes, nombres). Esta feature **amplía `DesarrolloFacade`** con `inscripcionesActivas` y `obtenerNivel` (aditivo, solo lectura) para Adecuación, PDI y Reportes.
- **Baja de colaborador (FR-021a)**: no existe evento de baja en Personal. Se resuelve con un caso de uso idempotente `ConciliarBajasDeInscripciones` que consulta `PersonalFacade::vigentesPorIds` y retira las Activas de quien ya no tiene vínculo vigente (motivo «Baja del colaborador», historial con usuario del sistema). Se ejecuta (a) al listar inscripciones, ver una ruta con cantera y mostrar «Mi ruta», y (b) por un comando Artisan programable. Ver R5.
- **Permisos `desarrollo.rutas.*` sin tabla de permisos**: el proyecto solo tiene roles `admin`/`user` por capacidad de token. Se crea un **catálogo de claves en código** con la asignación por defecto a los roles (Administrador: todas; Colaborador: las `_propio`), un **único punto de autorización** (middleware `permiso:<clave>`) y un endpoint de lectura para que el frontend decida qué mostrar. Ver R4 y la desviación registrada.
- **Reutilización de puertos (sin duplicar)**: los colaboradores se leen con los puertos que ya existen en `Desarrollo` (`ColaboradoresVigentesInterface` para búsqueda y vigencia, `CuentaColaboradorInterface` para usuario→colaborador, `NombresDeReferenciaInterface` para nombres de colaboradores y puestos, `DepartamentoDeColaboradorInterface` para el departamento actual y `NombresDeUsuarioInterface` para creador y último modificador). Solo se crea `PuestosParaRutasInterface`, porque ningún puerto actual entrega los requisitos de texto del puesto.
- **Estados degradados**: si Estructura o Personal fallan, las lecturas devuelven la parte disponible con `fuentes_no_disponibles` y la interfaz muestra un aviso con «Reintentar»; un nivel cuyo puesto se desactivó se marca con advertencia (`puesto.activo = false`) y bloquea volver a publicar la ruta.
- **Siembra y conciliación**: la conciliación de bajas retira a quien no tenga vínculo vigente, así que `RutasSeeder` y los E2E inscriben solo a colaboradores con vínculo vigente (o preparan ese vínculo); no dependen de `IngresosSeeder`.
- **Competencias con grado vs. conocimientos informativos (R3)**: solo las **competencias** del puesto llevan grado A–D, valor requerido y diferencial contra el nivel anterior. Los «conocimientos con grado» **no existen** en Estructura y quedan fuera de alcance. La tarjeta «Conocimientos» del detalle muestra, solo como **información**, los requisitos de texto de **Formación y Capacitación** del puesto tal como los entrega la Facade de Estructura: sin grado, sin comparación y sin diferencial. La columna «Experiencia» conserva los años mínimos guardados en `ruta_niveles`. Una **bandera de configuración** `desarrollo.conocimientos_con_grado` (por defecto `false`) deja documentado el punto de extensión por si Estructura incorpora conocimientos con grado; **la rama `true` no se implementa**.

## Technical Context

**Language/Version**: PHP 8.3 (Laravel 13) en backend; TypeScript estricto + React 19 en frontend (versiones de `composer.json` / `package.json`)

**Primary Dependencies**: Laravel Sanctum, React Router, TanStack Query, Axios, React Hook Form, Zod, Tailwind CSS 4, Vitest, Testing Library, Playwright (ya instaladas y aprobadas). **Ninguna dependencia nueva.**

**Storage**: SQLite. Migraciones nuevas: `rutas`, `ruta_niveles`, `ruta_inscripciones`, `ruta_historial` (todas con `tenant_id`). Índice único parcial `(ruta_id, colaborador_id)` donde `estado = 'Activa'`; únicos `(ruta_id, orden)` y `(ruta_id, puesto_id)`. Historial inmutable (sin actualizar ni borrar).

**Testing**: Pest (Unit y Feature); Vitest + Testing Library (jsdom); E2E con Playwright. Pint disponible; no hay análisis estático instalado y no se añade (Constitución IV).

**Target Platform**: Navegadores modernos de escritorio y móvil (320–1440 px)

**Project Type**: Aplicación web (API Laravel + SPA React)

**Performance Goals**: lista, escalera e inscripciones en menos de 2 s con 100 rutas y 1 000 inscripciones (SC-007); el diferencial y los requisitos se resuelven con una sola lectura por lote de puestos (sin N+1 contra Estructura).

**Constraints**: nunca DELETE; nombre único normalizado (sin mayúsculas ni espacios extremos); departamento no aplica a Interáreas; publicar exige ≥ 2 niveles con puesto activo; solo rutas Vigentes admiten inscripción; una inscripción Activa por colaborador y ruta; objetivo posterior al actual; no quitar ni cambiar el puesto de un nivel en uso por una inscripción Activa; solo colaboradores activos son inscribibles; un usuario tiene un solo rol; todo texto visible en español y centralizado; WCAG 2.2 AA; filtros en la URL.

**Scale/Scope**: decenas de rutas, 2–10 niveles por ruta, cientos de inscripciones; 4 pantallas de la spec (lista, editor con pestañas Escalera/Participantes e historial, Mi ruta) y 13 operaciones HTTP; 4 tablas nuevas.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principio | Evaluación |
|---|---|
| I. Backend modular / Clean Architecture | PASA. Casos de uso en `Desarrollo/Application/UseCases`; reglas en `Domain/Services` y entidades sin Laravel; `RutasRepositoryInterface` en `Domain/Repositories`; controladores delgados sin consultas. |
| I. Rutas bajo `/api/<modulo>/` | DESVIACIÓN JUSTIFICADA (patrón 000–018). Prefijos `/api/rutas` e `/api/inscripciones-ruta` por contrato del insumo. Ver Complexity Tracking. |
| I. Un módulo no accede a tablas de otro módulo | PASA. Estructura y Personal solo por puertos con adaptadores sobre sus Facades; `puesto_id`, `departamento_id` y `colaborador_id` son referencias lógicas sin FK. |
| I. Endpoints admin con Sanctum + `admin` | DESVIACIÓN JUSTIFICADA. La spec exige autorización por clave de permiso, no por rol. Todos los endpoints exigen `auth:sanctum`; la autorización la hace un único middleware `permiso:<clave>` cuyo catálogo asigna por defecto las claves de gestión al rol `admin` y las `_propio` al rol `user`. Equivale a la regla de la constitución para el estado actual y la generaliza. Ver Complexity Tracking. |
| I. Pest, Pint y análisis estático | PASA. Pest + Pint; no hay análisis estático instalado (se documenta, no se añade). |
| II. Frontend modular, tipado, accesible | PASA. Código en `frontend/src/modules/desarrollo/rutas/`; Axios solo en el cliente compartido; TanStack Query sin reintentos en escrituras; filtros en la URL; sin `any`. |
| II. Estados, formularios, confirmaciones | PASA. Cada vista cubre loading, empty, error con «Reintentar» y success; formularios con errores por campo; archivar con inscritos y retirar usan `ConfirmDialog`/modal accesible. |
| III. Diseño como fuente visual | PASA con diferencias registradas (tabla abajo, manda la spec). No se añade código a `design/`. |
| IV. Calidad, seguridad, dependencias | PASA. Sin dependencias nuevas; entradas validadas en `FormRequest` y dominio; errores públicos seguros; historiales inmutables. |

**Re-evaluación tras el diseño (Fase 1)**: sin cambios; las dos desviaciones quedan en Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/019-rutas-carrera/
├── plan.md              # Este archivo
├── research.md          # Fase 0
├── data-model.md        # Fase 1
├── quickstart.md        # Fase 1
├── contracts/
│   ├── rutas-api.md
│   └── rutas-puertos-y-facade.md
├── checklists/requirements.md
└── tasks.md             # Fase 2 (/speckit-tasks — no se crea aquí)
```

### Source Code (repository root)

```text
backend/
├── database/
│   ├── migrations/2026_10_23_00000{1,2,3,4}_create_{rutas,ruta_niveles,ruta_inscripciones,ruta_historial}_table.php
│   └── seeders/RutasSeeder.php                  # registrado en DatabaseSeeder tras ReportesSeeder (depende de PuestosSeeder y PersonalSeeder)
├── app/Modules/Desarrollo/
│   ├── Domain/
│   │   ├── Entities/                            # Ruta, NivelDeRuta, InscripcionARuta, EventoDeRuta
│   │   ├── ValueObjects/                        # CodigoDeRuta, TipoDeRuta, EstadoDeRuta, EstadoDeInscripcion, NombreDeRuta, ClaveDePermisoRutas
│   │   ├── Services/                            # ReglasDePublicacionDeRuta, ReglasDeInscripcion, CalculadorDeDiferencial, CalculadorDeCantera
│   │   ├── Exceptions/                          # RutaNoEncontrada, RutaNoPermitida, InscripcionNoPermitida, NivelEnUso, …
│   │   └── Repositories/RutasRepositoryInterface.php
│   ├── Application/
│   │   ├── Ports/                               # PuestosParaRutasInterface (nuevo). Se reutilizan los existentes: ColaboradoresVigentesInterface, CuentaColaboradorInterface, NombresDeReferenciaInterface, NombresDeUsuarioInterface, DepartamentoDeColaboradorInterface, DepartamentosInterface y RelojInterface
│   │   ├── Services/AutorizadorDeRutas.php      # catálogo de claves + asignación por defecto a los roles
│   │   └── UseCases/                            # (carpeta plana) ListarRutas, ObtenerRuta, CrearRuta, ActualizarRuta, CambiarEstadoDeRuta,
│   │                                            # ListarHistorialDeRuta, ListarInscripcionesDeRuta, InscribirEnRuta, CambiarNivelesDeInscripcion,
│   │                                            # RegistrarNivelAlcanzado, RetirarInscripcion, ObtenerMiRuta, ConciliarBajasDeInscripciones, ObtenerPermisosDeRutas
│   ├── Infrastructure/
│   │   ├── Adapters/                            # PuestosParaRutasDesdeEstructura (los demás adaptadores ya existen)
│   │   ├── Persistence/Eloquent/Models/         # RutaModel, RutaNivelModel, RutaInscripcionModel, RutaHistorialModel
│   │   ├── Persistence/Eloquent/Repositories/EloquentRutasRepository.php
│   │   ├── Console/ConciliarBajasRutasCommand.php
│   │   └── Providers/DesarrolloServiceProvider.php   # bindings nuevos
│   ├── Presentation/
│   │   ├── Controllers/RutasController.php, InscripcionesRutaController.php, MiRutaController.php
│   │   ├── Requests/                            # GuardarRutaRequest, CambiarEstadoRutaRequest, InscribirRequest, CambiarNivelesRequest, RetirarRequest, FiltroRutasRequest
│   │   ├── Resources/                           # RutaResource, NivelResource, InscripcionResource, EventoResource
│   │   ├── Middleware/EnsurePermiso.php         # alias `permiso`
│   │   └── Facades/DesarrolloFacade.php         # + inscripcionesActivas, obtenerNivel (aditivo)
│   └── Routes/api.php                           # rutas /api/rutas e /api/inscripciones-ruta
├── config/desarrollo.php                        # bandera `conocimientos_con_grado` (false; solo punto de extensión, R3)
├── bootstrap/app.php                            # alias `permiso`
└── tests/{Unit,Feature}/Desarrollo/Rutas/       # Pest

frontend/
├── src/modules/desarrollo/rutas/
│   ├── api.ts  text.ts  validation.ts  nav.tsx  estilosEstado.ts  permisos.ts
│   ├── hooks/        # useRutas, useRuta, useGuardarRuta, useCambiarEstadoRuta, useHistorialRuta, useInscripciones, useInscribir,
│   │                 # useCambiarNiveles, useAlcanzarNivel, useRetirar, useMiRuta, usePermisosRutas, useFiltrosRutas, claves.ts
│   ├── components/   # TarjetaDeRuta, BarraDeFiltrosRutas, EscaleraDeNiveles, DetalleDeNivel, SelectorDePuesto, TablaDeParticipantes,
│   │                 # CanteraPorNivel, FormularioRutaModal, FormularioInscripcionModal, CambiarNivelesModal, RetirarModal,
│   │                 # HistorialDeRuta, AvisoGuia, EscaleraColaborador, RequireRole.tsx, RequirePermiso.tsx
│   └── pages/        # RutasPage (lista), RutaEditorPage (pestañas Escalera / Participantes / Historial), MiRutaPage
├── src/App.tsx                                  # rutas y navItems
└── e2e/rutas.spec.ts
```

**Structure Decision**: aplicación web con el módulo existente `Desarrollo` (backend) y `desarrollo` (frontend). Se crea la funcionalidad `rutas/` junto a `actividades/` y `reportes/`, con las mismas capas; no se crean carpetas vacías.

## Diferencias entre tableros y spec (manda la spec, salvo el aspecto visual)

| Tablero | Diferencia | Resolución |
|---|---|---|
| Los dos de referencia | Menú lateral de prototipo (Empleados, Ajustes) y botón «Ver como colaborador». | **Se omiten**: el App Shell real ya existe y cada persona tiene un solo rol. |
| RutasAdmin / RutasEditor | Filtro y campo «Área» con lista fija (Tecnología, Comercial…). | Se llama **Departamento** y se alimenta de las unidades de Estructura; «Todas las áreas» se muestra como «Sin departamento» solo en rutas Interáreas, donde el campo no aplica (FR-004). |
| RutasAdmin / RutasEditor | Campo «Duración de la ruta (meses)» y texto de duración en la tarjeta. | **Se omite**: la spec no modela tiempo por ruta (Supuestos). La tarjeta muestra niveles e inscritos. |
| RutasAdmin | Las tarjetas dicen «participantes». | Se conserva el rótulo visual «participantes» para el recuento; la entidad y los permisos siguen llamándose inscripciones. |
| RutasEditor / RutasParticipantes | Pestañas «Escalera de niveles» y «Participantes (n)». | Se conservan; se **añade** la pestaña «Historial» (FR-038/039, permiso `desarrollo.rutas.historial`) con la tabla estándar de historial del design system. |
| RutasEditor | «Agregar nivel arriba» con un selector de puestos fijo; el nivel se agrega solo por el puesto. | Se conserva; el selector lista **puestos activos** de Estructura que no estén ya en la ruta (FR-012). Se añaden los campos nombre del nivel y experiencia mínima (años) en el detalle del nivel. |
| RutasEditor | Reordenar y quitar niveles no aparecen como controles. | Se agregan «Subir», «Bajar» y «Quitar» por nivel con los botones del design system; «Quitar» y el cambio de puesto se bloquean con motivo si el nivel está en uso (FR-017). |
| RutasAdmin (pestaña Escalera) y RutasColaborador | Detalle en tres columnas Conocimientos, Competencias y Experiencia, todas con grados A–D y diferencial. La spec (FR-015, FR-016, FR-029) dice lo mismo de los conocimientos. | Solo las **competencias** muestran grado requerido y diferencial («+1 (antes C)», «Nuevo requisito», «Sin cambio»). La tarjeta **Conocimientos** se conserva pero es **informativa**: lista los requisitos de Formación y Capacitación del puesto como texto, sin grado, sin comparación y sin diferencial, con la etiqueta «Informativo». **Experiencia** muestra los años mínimos del nivel (editable) y su diferencial en años. Competencias y conocimientos son de solo lectura. Los conocimientos con grado A–D no existen en Estructura y están fuera de alcance (R3, bandera `desarrollo.conocimientos_con_grado` en `false`). La spec queda desalineada en estas frases hasta corregirla con `/speckit-clarify`; manda esta decisión. |
| RutasParticipantes | Inscribir selecciona el colaborador de una lista fija y lo ubica en el nivel de ingreso. | El selector busca solo **colaboradores activos** (FR-018) y el formulario añade nivel actual (opcional) y objetivo; el texto de ayuda «Ingresa en el nivel…» se adapta. Acciones por fila: cambiar niveles, registrar nivel alcanzado y retirar (con motivo). |
| RutasColaborador | «Registrar mi interés», «Mis propuestas de interés» y el panel de permisos simulados (`desarrollo.objetivo.proponer/resolver`, `desarrollo.adecuacion.ver`). | **Se omiten** (FR-033: proponer interés queda para una etapa posterior; los permisos simulados son del prototipo). |
| RutasColaborador | «Ver mi adecuación» depende del permiso simulado `desarrollo.adecuacion.ver`. | El botón aparece solo si hay nivel objetivo **y** la vista de adecuación existe (Supuestos); mientras no exista, no se muestra. |
| RutasColaborador | Lista de rutas Vigentes disponibles no aparece. | Se agrega la sección «Rutas disponibles» (solo lectura, FR-031) con las tarjetas del design system. |

Estas diferencias se registran aquí (Constitución III) y en `design/DESIGN.md` (tarea T002).

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|---|---|---|
| Prefijos `/api/rutas` e `/api/inscripciones-ruta` en vez de `/api/desarrollo/` | Contrato del insumo y patrón consolidado en las features 000–018 (`/api/dnf`, `/api/reportes`…) | Cambiar solo esta feature rompería la convención ya establecida y el contrato dado. Precedente aprobado en 000–018. |
| Autorización por clave de permiso (`permiso:<clave>`) en lugar de `admin` | La spec define seis claves estables y su asignación futura por rol sin cambiar la feature | Usar `admin` mezclaría dos reglas distintas (gestión y consulta propia) y obligaría a reescribir cada ruta al introducir roles configurables. El catálogo en código mantiene la regla actual (admin = todas, user = `_propio`). **Aprobada por el usuario (2026-10-20).** |
| Catálogo de permisos en código y no en tabla/seeder | No existe infraestructura de roles y permisos; crearla excede esta feature | Tabla de permisos + administración de roles es una feature propia («etapa posterior» en la spec). El catálogo queda detrás de `AutorizadorDeRutas`, listo para leerse de una tabla. **Aprobado por el usuario (2026-10-20).** |
