# Implementation Plan: Micro-learning

**Branch**: `016-micro-learning` | **Date**: 2026-10-16 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/016-micro-learning/spec.md`, con el insumo técnico `plan-microlearning.md` (carpeta `specify y plan`) y los tableros `design/MicroAdmin.dc.html`, `design/MicroColaborador.dc.html`, `design/MicroReproduccion.dc.html` y `design/MicroQuiz.dc.html`.

## Summary

Repositorio de cápsulas breves (Video, PDF, Artículo y Quiz) con ciclo de vida Borrador → Publicado → Archivado, consumo por el
colaborador con registro de progreso, quizzes de opción múltiple con retroalimentación inmediata, horas acumuladas y certificado
PDF bajo demanda. El Administrador gestiona una **cuadrícula de tarjetas** con formulario (con constructor de preguntas) y vista
previa; el Colaborador ve **Mi aprendizaje** (resumen, pestañas Asignado/Completado), la **reproducción** y el **quiz**.

Es la tercera feature de `Desarrollo` y **no parte de cero**: la feature 005 ya creó una tabla `microcontenidos` *mínima*
(`nombre`, `tipo` Video|Lectura|Infografia|Audio, `estado` Disponible|Archivado, `formador`, `url_destino`), el vínculo
`competencia_microcontenido` con su historial, y el Catálogo Formativo que las consume. Este plan **amplía esa tabla con
migraciones nuevas** (sin recrearla), conserva el vínculo como el mismo dato (FR-011) y adapta el Catálogo a los nuevos nombres y
estados. Las competencias, el nombre del colaborador y las brechas activas se obtienen **solo por los puertos del módulo** (ya
existentes), cuyos adaptadores llaman a las Facades de Estructura, Gestión de Personal y DNF. El módulo publica métodos nuevos en
`DesarrolloFacade` para el Catálogo y Reportes. Las pantallas siguen los cuatro tableros de micro-learning sobre `design/DESIGN.md`;
las diferencias entre tableros y spec (manda la spec) quedan en "Diferencias entre tableros y spec".

## Technical Context

**Language/Version**: PHP 8.3 (Laravel 13) en backend; TypeScript 6 estricto + React 19 en frontend

**Primary Dependencies**: Laravel Sanctum, dompdf (ya instalada: `dompdf/dompdf ^3.1`), React Router, TanStack Query, Axios, React
Hook Form, Zod, Tailwind CSS 4, Vitest, Testing Library, Playwright (todas ya instaladas y aprobadas). **Ninguna dependencia nueva.**

**Storage**: SQLite. Migraciones nuevas: ampliación de `microcontenidos` y las tablas `microcontenido_preguntas`,
`microcontenido_opciones`, `microcontenido_progresos`, `microcontenido_intentos`, `microcontenido_respuestas`,
`microcontenido_historial`. Se reutiliza `competencia_microcontenido` (sin tabla de vínculo nueva). Sin soft deletes ni
`cascade`: el borrado físico es explícito dentro de una transacción.

**Testing**: Pest (Unit y Feature); Vitest + Testing Library (jsdom); E2E con Playwright. Pint disponible; no hay análisis estático
instalado y no se añade (Constitución IV).

**Target Platform**: Navegadores modernos de escritorio y móvil (320–1440 px)

**Project Type**: Aplicación web (API Laravel + SPA React)

**Performance Goals**: listados del Administrador y del colaborador en menos de 2 s con 500 micro-contenidos (SC-010);
certificado en menos de 5 s (SC-009); publicar un Video en menos de 3 min y armar un quiz de 5 preguntas en menos de 8 min (SC-001/002)

**Constraints**: competencias solo por `CompetenciasInterface` (Estructura); nombre del colaborador solo por Gestión de Personal;
brechas solo por DNF; el colaborador **nunca** recibe la opción correcta antes de responder; el colaborador solo opera sobre sus
propios datos; solo cuentan los intentos **Finalizados** (los incompletos no bloquean ni puntúan); tipo y código inmutables;
eliminar solo sin registros de progreso; un usuario tiene un solo rol; texto visible en español, WCAG 2.2 AA

**Scale/Scope**: cientos de micro-contenidos, decenas de miles de progresos; 4 pantallas principales (repositorio, formulario con
constructor, Mi aprendizaje, reproducción/quiz), 14 operaciones HTTP y 3 métodos nuevos de Facade

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principio | Evaluación |
|---|---|
| I. Backend modular / Clean Architecture | PASA. Casos de uso nuevos en `Desarrollo/Application/UseCases`, reglas puras (transiciones, requisitos de publicación, validación del quiz, puntaje, asignación) en `Domain/Services` sin Laravel, repositorios por contrato `MicrocontenidoRepositoryInterface` y `ProgresoMicroRepositoryInterface`, controladores delgados. |
| I. Rutas bajo `/api/<modulo>/` | DESVIACIÓN JUSTIFICADA (patrón aceptado en 000–013). Prefijo `/api/microcontenidos` por contrato del insumo. Ver Complexity Tracking. |
| I. Admin = Sanctum + capacidad `admin` | PASA. Escritura, historial y vista administrativa usan `auth:sanctum` + `admin`; listar y consultar usan solo `auth:sanctum` y deciden la visibilidad por la capacidad del token; los endpoints del colaborador rechazan al administrador (403). |
| I. Un módulo no accede a clases/tablas de otro | PASA. Competencias: `CompetenciasInterface`. Brechas: `BrechasActivasInterface`. Cuenta del colaborador: `CuentaColaboradorInterface`. Nombre del colaborador: puerto nuevo `NombreDeColaboradorInterface` (adaptador por `PersonalFacade`). `competencia_id`, `colaborador_id` y `user_id` son enteros sin FK entre módulos. |
| I. Validación y autorización en los límites | PASA. Formas, longitudes, cantidades del quiz y URL en Form Requests; competencias activas, transiciones y bloqueo de preguntas en el caso de uso con reglas de dominio puras. |
| I. Auditoría en los límites | PASA. El actor sale del token en el controlador; el repositorio escribe contenido, vínculos, historial de estados y historial del Catálogo en una sola transacción. |
| I. Migraciones, factories y seeders | PASA. Migraciones nuevas, factories de preguntas y progreso, `MicroLearningSeeder` y ajuste de `CatalogoFormativoSeeder`. |
| I. Pest, Pint | PASA. Unit (reglas de dominio) y Feature (API, repositorios, facade, certificado). |
| II. Frontend modular, `shared` sin módulos | PASA. Código en `frontend/src/modules/desarrollo/micro-learning/`. Reutiliza `shared/ui`; ningún componente compartido nuevo salvo íconos SVG propios. |
| II. Una funcionalidad no importa internals de otra | PASA. No importa de `catalogo-formativo/*`; el Catálogo abre la reproducción por un `url_destino` (`/micro-learning/{id}`) que entrega el backend. El rol llega por el contrato público `modules/auth/login`. |
| II. Axios solo en cliente compartido; TanStack Query; escrituras sin reintento; filtros en URL | PASA. Búsqueda, filtros, página y pestaña en `useSearchParams`; mutaciones sin reintentos. |
| II. App Shell, estados, formularios, confirmación accesible, WCAG | PASA. Estados cargando/vacío/error/éxito en repositorio, Mi aprendizaje, reproducción y quiz; `ConfirmDialog` al archivar y eliminar; formulario y vista previa en `Modal` con foco atrapado. |
| II. E2E real por feature | PASA. `frontend/e2e/micro-learning.spec.ts` (Administrador y colaborador). |
| III. `design/` como fuente | **AJUSTE ANTES DE IMPLEMENTAR**. Los cuatro tableros difieren de la spec en varios puntos (umbral del 70 %, 4 opciones fijas, una sola competencia, panel de certificados, controles de prototipo). `design/DESIGN.md` §4, §5 y §9 se actualizan **primero** (primera tarea de frontend). |
| IV. Dependencias | PASA. Sin dependencias nuevas; el certificado reutiliza dompdf. |
| IV. Pruebas, seguridad | PASA. Validación en backend y frontend; errores sin detalles internos; el colaborador nunca recibe la respuesta correcta antes de responder ni datos ajenos. |

**Re-evaluación tras Fase 1**: sin cambios en los veredictos. El diseño añade migraciones sobre la tabla de la feature 005, un
puerto nuevo con adaptador definitivo y ajustes al Catálogo; todo queda en Complexity Tracking y en "Cambios respecto al insumo".

## Cambios respecto al insumo (`plan-microlearning.md`)

El insumo se respeta en tablas, endpoints, módulo y estructura. Estos puntos se ajustan por la clarificación de la spec o por la
realidad del repositorio:

| # | Insumo | Plan | Motivo |
|---|---|---|---|
| A-01 | Crea la tabla `microcontenidos` | La tabla **ya existe** (feature 005, mínima). Se **amplía** con migraciones nuevas: renombra `nombre`→`titulo` y `url_destino`→`url`, quita `formador`, añade `codigo`, `miniatura_url`, `created_by`, `updated_by`, cambia el default de `estado` a `Borrador` y convierte los datos (`Disponible`→`Publicado`; `Lectura`→`Articulo`, `Infografia`→`PDF`, `Audio`→`Video`) | Evitar dos tablas y conservar el vínculo y los ids que ya usa el Catálogo |
| A-02 | Intentos `EnCurso \| Finalizado` | Se conservan los dos estados (el servidor necesita el intento en curso para ocultar la opción correcta y dar retroalimentación inmediata), pero **solo un intento Finalizado** cuenta, bloquea las preguntas y suma al puntaje. Iniciar un intento descarta el `EnCurso` anterior del mismo progreso; editar las preguntas descarta los `EnCurso` | Clarificación de la spec (sesión 2026-10-16) y FR-015/FR-016 |
| A-03 | Eliminar: "vínculos, preguntas y opciones" | Se borran también las filas de `microcontenido_historial` del contenido, porque la FK no tiene `cascade` y el contenido sin progreso desaparece por completo | Coherencia con el borrado físico explícito |
| A-04 | Fila de progreso sin tenant | `microcontenido_progresos` añade `tenant_id` (copiado de la cuenta del colaborador) | La Facade para Reportes filtra por tenant, igual que DNF y Plan |
| A-05 | Vínculos escritos por esta feature, historial "del Catálogo" | La escritura de vínculos pasa por el repositorio del Catálogo (`sincronizarCompetenciasDeMicrocontenido`) que registra el historial del Catálogo y **incrementa `version`** de cada configuración afectada | FR-011/FR-043 y el bloqueo optimista del Catálogo (A-05 de la feature 005) |
| A-06 | Estados del recurso del Catálogo: `Disponible \| Archivado` | Para micro-contenidos, **Publicado** equivale a *disponible*; Borrador y Archivado no cuentan. Los cursos no cambian | Spec FR-023 y Catálogo FR-017/FR-020 |
| A-07 | Facade: "consultar publicados por competencia" y "completaciones, horas y certificados" | Se añaden `getMicrocontenidosPublicadosPorCompetencia`, `getResumenAprendizaje` y `getCompletaciones` a `DesarrolloFacade`; `getOfertaPorCompetencia` sigue vigente | FR-044 y el Catálogo |
| A-08 | Quiz: "Iniciar intento" siempre crea intento | `POST /intentos` es idempotente por progreso: devuelve el intento `EnCurso` vigente con las respuestas ya dadas o crea uno nuevo; así el colaborador puede retomar tras un refresco sin que el intento cuente si no lo termina | Experiencia sin pérdida de respuestas dentro de una sesión |
| A-09 | Vista previa del Administrador | Se resuelve **en el cliente** con los datos de `GET /{id}` (que ya incluyen la opción correcta para el Administrador); no llama a ningún endpoint de progreso ni de intentos | FR-038: no registra progreso ni intentos |
| A-10 | Competencias del certificado | El certificado lista las competencias **vinculadas al emitirlo** (nombre vigente, incluidas las inactivas) | Decisión de bajo impacto del cierre de la clarificación; ver research R-09 |

## Diferencias entre tableros y spec (manda la spec)

Se registran en `design/DESIGN.md` §9 al inicio de la implementación.

| Tablero | Spec vigente |
|---|---|
| `MicroColaborador` muestra «Aprueba un quiz con 70 % o más para obtener tu primer certificado» y un estado Aprobado/Reprobado | No hay puntaje mínimo (spec 3.2): completar un intento da el certificado. Se omite el panel «Mis certificados»; el certificado se descarga desde la pestaña Completado y el resumen cuenta los certificados |
| Cada pregunta tiene 4 opciones (A–D) fijas | Entre 2 y 5 opciones por pregunta, una correcta; el constructor permite agregar y quitar |
| `MicroAdmin` asocia **una** competencia (select simple) | Selección múltiple de competencias activas |
| Filtro de estado: Todos, Publicado, Archivado | Por defecto Borrador y Publicado; opciones: «Activos» (por defecto), Borrador, Publicado, Archivado, Todos |
| Botón «Ver como colaborador», menú «Empleados / Ajustes», tarjetas KPI y bloque «Úsalo para conocimientos» con cita larga | Un usuario tiene un solo rol: sin «Ver como…»; sin controles de prototipo; sin KPI; la nota de alcance en conocimientos es un `Alert` neutro breve (spec 4.3) |
| El modal de eliminar ofrece «Archivar en su lugar» incluso con completaciones | Con progreso el botón Eliminar **no se ofrece**; solo Archivar. El modal de eliminar aparece únicamente para contenido sin progreso |
| Tarjeta del colaborador con porcentaje de avance | Se muestra el estado, la duración y, en Quiz completado, el último puntaje «4 de 5» (spec 4.8); en Video en progreso, la posición guardada |
| Resultado del quiz con mensaje de aprobación | Puntaje «N de M» y mensaje neutro; opción «Reintentar quiz» |
| Inputs con borde `--border` | `--input-border` (regla de `DESIGN.md`) |

## Project Structure

### Documentation (this feature)

```text
specs/016-micro-learning/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── checklists/requirements.md
├── contracts/
│   ├── microcontenidos-api.md
│   └── micro-puertos-y-facades.md
└── tasks.md             # Lo genera /speckit-tasks
```

### Source Code (repository root)

```text
backend/
├── app/Modules/Desarrollo/
│   ├── Domain/
│   │   ├── Entities/                      # Microcontenido, PreguntaQuiz, ProgresoMicro, IntentoQuiz
│   │   ├── Exceptions/                    # MicroNoEncontrado, MicroNoPublicable, TransicionMicroInvalida, MicroConProgreso,
│   │   │                                  # QuizConIntentos, QuizInvalido, MicroNoDisponible, YaCompletado, RespuestaInvalida ...
│   │   ├── Repositories/                  # MicrocontenidoRepositoryInterface, ProgresoMicroRepositoryInterface
│   │   ├── Services/                      # ReglasDeMicrocontenido, ReglasDeQuiz, CalculadorDeAsignacion, CalculadorDeResumen
│   │   └── ValueObjects/                  # TipoMicro, EstadoMicro, EstadoProgresoMicro, EstadoIntento, FiltroMicro
│   ├── Application/
│   │   ├── DTO/                           # MicroDTO, FilaMicroDTO, PreguntaDTO, ProgresoDTO, IntentoDTO, MiAprendizajeDTO ...
│   │   ├── Ports/                         # NombreDeColaboradorInterface, CertificadoPdfInterface
│   │   └── UseCases/                      # Crear/Actualizar/Listar/Obtener/CambiarEstado/Eliminar/HistorialDeMicro,
│   │                                      # ObtenerMiAprendizaje, GuardarAvanceMicro, CompletarMicro, IniciarIntento,
│   │                                      # ResponderPregunta, GenerarCertificado
│   ├── Infrastructure/
│   │   ├── Adapters/                      # NombreDeColaboradorDesdePersonal, CertificadoPdfDompdf (+ plantilla)
│   │   └── Persistence/Eloquent/
│   │       ├── Models/                    # (MicrocontenidoModel ampliado), MicroPreguntaModel, MicroOpcionModel,
│   │       │                              # MicroProgresoModel, MicroIntentoModel, MicroRespuestaModel, MicroHistorialModel
│   │       └── Repositories/              # EloquentMicrocontenidoRepository, EloquentProgresoMicroRepository
│   │                                      # (+ ajustes en EloquentCatalogoFormativoRepository)
│   ├── Presentation/
│   │   ├── Controllers/                   # MicrocontenidoController, MiAprendizajeController, ProgresoMicroController,
│   │   │                                  # IntentoMicroController, CertificadoMicroController
│   │   ├── Requests/                      # GuardarMicrocontenidoRequest, CambiarEstadoMicroRequest, ListarMicroRequest,
│   │   │                                  # GuardarAvanceRequest, ResponderRequest
│   │   ├── Resources/                     # MicroResource, FilaMicroResource, MiAprendizajeResource, IntentoResource
│   │   └── Facades/DesarrolloFacade.php   # + 3 métodos
│   ├── Infrastructure/Providers/DesarrolloServiceProvider.php   # + bindings
│   └── Routes/api.php                     # + bloque /api/microcontenidos
├── database/migrations/                   # 2026_10_20_000001..000007
├── database/factories/                    # MicrocontenidoFactory (ajustada), MicroPreguntaFactory, MicroProgresoFactory
├── database/seeders/                      # MicroLearningSeeder (+ ajuste de CatalogoFormativoSeeder y DatabaseSeeder)
└── tests/{Unit,Feature}/Desarrollo/

frontend/
├── src/modules/desarrollo/micro-learning/
│   ├── api.ts  text.ts  validation.ts  validation.test.ts  estilosTipo.ts  nav.tsx  tiempo.ts
│   ├── hooks/        # useRepositorio, useMicro, useGuardarMicro, useCambiarEstado, useEliminarMicro, useMiAprendizaje,
│   │                 # useAvance, useCompletar, useIntentoQuiz, useListadoParams
│   ├── components/   # TarjetaMicro, FiltrosRepositorio, FormularioMicro, ConstructorDeQuiz, VistaPreviaMicro,
│   │                 # ResumenAprendizaje, TarjetaAprendizaje, ReproductorVideo, VisorDocumento, QuizPlayer,
│   │                 # ResultadoQuiz, AvisoConocimientos, EtiquetaTipo, RequireRole
│   └── pages/        # MicroLearningPage (según rol), RepositorioAdminPage, MiAprendizajePage, ReproduccionPage
├── src/App.tsx                            # + rutas /micro-learning y /micro-learning/:id
├── src/modules/desarrollo/catalogo-formativo/   # sin cambios de código: usa url_destino que ahora entrega el backend
└── e2e/micro-learning.spec.ts
design/DESIGN.md                           # §4 (componentes), §5 (patrón), §9 (diferencias) — primera tarea de frontend
```

**Structure Decision**: Se extiende el módulo `Desarrollo` ya existente en backend y en `frontend/src/modules/desarrollo/`, con
una funcionalidad nueva `micro-learning/` al lado de `catalogo-formativo/`, `dnf/` y `plan-formacion/`.

## Diseño de frontend

### Rutas

| Ruta | Pantalla | Acceso |
|---|---|---|
| `/micro-learning` | Repositorio (Administrador) o Mi aprendizaje (colaborador), según el rol | Autenticado |
| `/micro-learning/:id` | Reproducción de Video/PDF/Artículo o Quiz, según el tipo | Colaborador (el Administrador usa la vista previa en modal) |

El formulario de creación/edición, la vista previa y los diálogos de archivar y eliminar son `Modal`/`ConfirmDialog` sobre
`/micro-learning`, como en `MicroAdmin.dc.html`. Ítem de menú «Micro-learning» después de «Plan anual». Los filtros del
repositorio (`q`, `tipo`, `estado`, `competencia_id`, `page`) y la pestaña de Mi aprendizaje (`tab`) viven en la URL.

### Componentes (sobre los existentes de `shared/ui`)

- **Tarjeta de micro-contenido** (Card): miniatura (o imagen genérica por tipo), `EtiquetaTipo`, título (h3), competencia (+N),
  duración, `Badge` de estado, «Apto para conocimientos», completaciones y acciones por estado (Vista previa, Editar, Publicar,
  Archivar, Reactivar, Eliminar). Color por tipo: Video `--danger`/`--dangersoft`, PDF `--info`/`--infosoft`, Artículo
  `--accent`/`--soft`, Quiz `--violet`/`--violetsoft` (tokens existentes de la feature 013). Siempre con texto, nunca solo color.
- **Cuadrícula**: `grid-template-columns: repeat(auto-fill, minmax(280px, 1fr))` con `Pagination`.
- **Formulario** (`Modal` de 640 px): `TextField`, `SelectField` de tipo (deshabilitado al editar), selección múltiple de
  competencias activas con `Combobox` + chips con «Quitar», duración, URL, miniatura; «Guardar borrador» siempre visible y
  «Publicar» que muestra los datos faltantes.
- **Constructor de quiz**: lista de preguntas con enunciado, 2–5 opciones con radio «correcta», Subir/Bajar y Quitar (patrón
  *Reordenar* de `DESIGN.md`), contadores `n/20` preguntas y `n/5` opciones; bloqueado en solo lectura con un `Alert` neutro si el
  quiz tiene intentos terminados.
- **Mi aprendizaje**: cuatro `StatCard` (completados, en progreso, horas, certificados), `Tabs` Asignado/Completado y tarjetas de
  aprendizaje con botón de acción («Iniciar», «Continuar», «Ver») o «Descargar certificado».
- **Reproducción**: marcador de reproductor de Video (posición simulada que avanza y se guarda cada 10 s y al salir), visor de
  PDF/Artículo con enlace a la URL, botón «Marcar como completado» (oculto si ya completado) y nota de alcance.
- **Quiz**: «Pregunta N de M», opciones en `radiogroup`, retroalimentación inmediata con `role="status"`, «Siguiente» y resultado
  con «N de M», «Reintentar quiz» y «Volver a Mi aprendizaje».

### Datos y estado

TanStack Query con claves `['micro', ...]`; las mutaciones invalidan el repositorio, `mi-aprendizaje` y el detalle. El avance del
Video usa una mutación con *debounce* sin reintento. Los errores 422 muestran por campo (formulario) o en un `Alert` (acciones).

### Validación

Zod en `validation.ts`: título (1–120), tipo, duración entero ≥ 1, URL `http(s)` obligatoria al publicar (salvo Quiz),
miniatura URL opcional, 1–20 preguntas, enunciado obligatorio, 2–5 opciones con texto y exactamente una correcta. La validación
del servidor es la autoridad.

## Complexity Tracking

| Desviación | Por qué se necesita | Alternativa más simple descartada |
|---|---|---|
| Prefijo `/api/microcontenidos` en lugar de `/api/desarrollo/...` | Contrato del insumo y patrón ya aceptado en 005, 006 y 013 | Renombrar rutas: rompería la coherencia del módulo y el insumo |
| Ampliar `microcontenidos` renombrando columnas y convirtiendo datos | La tabla y sus ids ya están referenciados por `competencia_microcontenido` y por el Catálogo | Crear una tabla nueva y migrar vínculos: duplica el dato (FR-011) |
| Dos repositorios de contenido y de progreso | Separan el catálogo de contenidos del registro de aprendizaje del colaborador | Un repositorio único con más de 25 métodos |
| Intentos `EnCurso` persistidos aunque no cuenten | El servidor debe ocultar la opción correcta hasta que se responde | Enviar el quiz completo con las correctas al cliente: filtra las respuestas |
| Puerto `NombreDeColaboradorInterface` | El certificado necesita el nombre y `Desarrollo` no puede leer tablas de Personal | Reusar `NombresDeReferenciaInterface`, que resuelve otros ids |
| Modificar `EloquentCatalogoFormativoRepository` y su seeder | Los micro-contenidos cambian de columnas y de estados | Dejar el Catálogo leyendo columnas inexistentes |
