# Implementation Plan: Fichas de colaboradores (GP-01)

**Branch**: `005-fichas-colaboradores` | **Date**: 2026-10-15 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/005-fichas-colaboradores/spec.md`, con el insumo técnico «Plan de
implementación · GP-01» (15-10-2026) y los prototipos de `design/` (`GP-01_Fichas-de-colaboradores.html`).

## Summary

El Administrador de Talento Humano registra, edita, lista, busca y consulta fichas de colaboradores de su tenant; el
Colaborador consulta solo la propia (`Mi ficha`). La ficha tiene código `EMP-###` generado por el sistema, identificación
única por tenant (tipo + número normalizado), datos personales, contacto y contacto de emergencia; no tiene estado,
puesto, unidad ni cuenta propios: el estado «Sin vínculo vigente», el puesto, la unidad y la trayectoria se leen por
puertos de capacidad (GP-02 y Estructura), que hoy no están instalados. Se rechaza la duplicidad, se garantiza el alta
idempotente con `Idempotency-Key` y se evita la sobrescritura con `version`.

El backend crea el módulo `GestionPersonal` (`/api/colaboradores`, `/api/mi-ficha`) y amplía `Shared` con el tenant
(`tenants`, `users.tenant_id`). El frontend añade `modules/gestionPersonal/fichas/`. Como el insumo asume PostgreSQL con un
esquema por tenant y permisos con nombre propio que **no existen en el repositorio**, el plan los resuelve con una columna
`tenant_id` sobre SQLite y con datos iniciales (ver [research.md](research.md) y Complexity Tracking).

## Technical Context

**Language/Version**: PHP 8.3+ (Laravel 13) en backend; TypeScript estricto + React 19 en frontend

**Primary Dependencies**: Laravel Sanctum, React Router, TanStack Query, Axios, React Hook Form, Zod, Tailwind CSS 4,
Vitest, Testing Library, Playwright (todos ya instalados y aprobados). Ninguna dependencia nueva; `Normalizer` (NFC) llega
por `symfony/polyfill-intl-normalizer`, ya presente en `composer.lock` (el PHP local no carga `ext-intl`).

**Storage**: SQLite. Tablas nuevas `tenants`, `colaboradores` y `colaborador_cuentas`; columna nueva `users.tenant_id`.
Aislamiento por columna `tenant_id` con unicidades compuestas. No se toca ninguna tabla de Estructura.

**Testing**: Pest (Feature y Unit); Vitest + Testing Library; E2E con Playwright. Pint disponible; no hay análisis
estático instalado y no se añade (Constitución IV).

**Target Platform**: Navegadores modernos de escritorio y móvil (320–1440 px)

**Project Type**: Aplicación web (API Laravel + SPA React)

**Performance Goals**: Listado paginado (20 por página, máximo 50) con una sola consulta a `colaboradores` más una
consulta por lote por lector; encontrar una ficha en menos de 15 s (SC-002); ficha mínima en menos de 1 min (SC-001)

**Constraints**: aislamiento por tenant resuelto en servidor desde la cuenta autenticada, nunca desde la petición;
identificación única `(tenant_id, tipo, clave)`; alta idempotente por `Idempotency-Key`; edición con `version`; sin
eliminación ni desactivación; ninguna columna laboral ni financiera en la ficha; lectores de capacidad con tres
resultados (disponible, no instalado, no disponible); respuesta idéntica para ficha inexistente, ajena o de otro tenant;
interfaz en español, WCAG 2.2 AA

**Scale/Scope**: Cientos a pocos miles de fichas por tenant; 5 operaciones HTTP + 1 de filtros (sin `DELETE`); 4
pantallas (listado, formulario, detalle, Mi ficha); 2 tenants y 7 fichas sembradas

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principio | Evaluación |
|---|---|
| I. Backend modular / Clean Architecture | PASA. Módulo nuevo `backend/app/Modules/GestionPersonal/` con Domain, Application, Infrastructure y Presentation; casos de uso explícitos; controladores delgados; persistencia por `ColaboradorRepositoryInterface`; reglas puras (normalización, contacto de emergencia, validación de fecha) en Domain sin Laravel. |
| I. Rutas bajo `/api/<modulo>/` | DESVIACIÓN JUSTIFICADA (precedente 000–004). Rutas `/api/colaboradores` y `/api/mi-ficha` por contrato del insumo. Ver Complexity Tracking. |
| I. Admin = Sanctum + capacidad `admin` | PASA. Listar, consultar, crear, editar y filtros usan `auth:sanctum` + `admin`; `mi-ficha` solo `auth:sanctum` y, dentro del caso de uso, asociación cuenta-ficha y permiso. El rol Colaborador es `user`. |
| I. Un módulo no accede a clases o tablas de otro | PASA. El tenant y la zona horaria se obtienen por el contrato público de `Shared` (`SharedFacade`). Puesto, unidad, estado y trayectoria, por puertos del módulo con adaptadores «no instalado». `colaborador_cuentas` es del módulo; sin claves foráneas a `users`. |
| I. Validación y autorización en los límites | PASA. Formas y longitudes en `GuardarColaboradorRequest`; unicidad, fecha no futura según el tenant y contacto completo en el caso de uso con reglas de dominio. |
| I. Errores sin información sensible | PASA. Ficha de otro tenant, ajena o inexistente: mismo 404. El 403 del Colaborador sobre rutas de administrador es uniforme. |
| I. Migraciones, factories, seeders; Pest, Pint | PASA. 4 migraciones, `ColaboradorFactory`, `PersonalSeeder` (depende de `UsuariosInicialesSeeder`), pruebas Unit y Feature. |
| II. Frontend modular, `shared` sin módulos | PASA. Código en `frontend/src/modules/gestionPersonal/fichas/`; reutiliza `shared/ui`; sin importar internals de `estructura`. El rol llega por el contrato público `modules/auth/login`. |
| II. Axios, TanStack Query, escrituras sin reintento, filtros en URL | PASA. `q`, `estado`, `unidad_id` y `page` en `useSearchParams`; mutaciones sin reintentos; la `Idempotency-Key` se genera una vez por formulario abierto. |
| II. App Shell, estados, formularios, WCAG, E2E | PASA. Estados cargando/vacío/error/éxito en listado y detalle; errores por campo; envío bloqueado; E2E `frontend/e2e/fichas.spec.ts`. |
| III. `design/` como fuente | **AJUSTE ANTES DE IMPLEMENTAR**. Los prototipos `GP-01_*.html` no son tableros `.dc.html` y difieren de la spec (ver Diseño de frontend). Primera tarea: ampliar `design/DESIGN.md` §4, §5 y §9 y añadir `design/ColaboradorFicha.dc.html` (+ `canvas.json`). |
| IV. Dependencias | PASA. Ninguna nueva. |
| IV. Calidad y seguridad | PASA. Datos de prueba sintéticos; el actor sale de la sesión; el tenant nunca se acepta del cuerpo. |

**Constitución y SQLite**: «SQLite es el almacenamiento predeterminado del desarrollo actual» permanece intacta; el
tenant por columna no exige enmienda. El esquema por tenant sobre PostgreSQL del insumo sí la exigiría (MINOR) y queda
fuera de esta feature (research D-02).

**Re-evaluación tras Fase 1**: sin cambios en los veredictos. El diseño añade `tenant_id` a la ficha, tres tablas y un
cambio aditivo en `Shared`; todo queda registrado en Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/005-fichas-colaboradores/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── colaboradores-api.md
│   └── personal-facade.md      # fachada pública y puertos de lectura
└── tasks.md                    # Lo genera /speckit-tasks
```

### Source Code (repository root)

```text
backend/
├── app/Modules/GestionPersonal/
│   ├── Domain/
│   │   ├── Entities/Colaborador.php
│   │   ├── Repositories/ColaboradorRepositoryInterface.php
│   │   ├── Services/{NormalizadorDeTexto,ReglasDeFicha}.php        (puros, sin Laravel)
│   │   ├── ValueObjects/{TipoIdentificacion,Identificacion,CodigoColaborador,DatosFicha,ContactoDeEmergencia,EstadoLaboral,Lectura}.php
│   │   └── Exceptions/{ColaboradorNoEncontrado,IdentificacionDuplicada,FichaModificada,ClaveDeOperacionReutilizada,CuentaSinFicha,SinPermisoDeFicha}.php
│   ├── Application/
│   │   ├── UseCases/{ListarColaboradores,ObtenerFiltrosDeColaboradores,ObtenerColaborador,RegistrarFicha,ActualizarFicha,ObtenerMiFicha}.php
│   │   ├── Ports/{ContextoDeTenantPort,LectorLaboralPort,LectorEstructuraPort,LectorDeCapacidadesPort,AccesosDeCuentaPort}.php
│   │   ├── DTO/{ColaboradorDTO,ColaboradorResumenDTO,DatosFichaDTO,FiltroColaboradoresDTO,ListadoColaboradoresDTO,FichaDetalleDTO}.php
│   │   └── Services/PersonalService.php                            (fachada interna del módulo)
│   ├── Infrastructure/
│   │   ├── Persistence/Eloquent/{Models/{ColaboradorModel,ColaboradorCuentaModel}.php,Repositories/{EloquentColaboradorRepository,EloquentAccesosDeCuenta}.php}
│   │   ├── Adapters/{SharedContextoDeTenant,LectorLaboralNoInstalado,LectorEstructuraNoInstalado,LectorDeCapacidadesNoInstalado}.php
│   │   └── Providers/GestionPersonalServiceProvider.php            (bindings; se registra en bootstrap/providers.php)
│   ├── Presentation/
│   │   ├── Controllers/{ColaboradorController,MiFichaController}.php
│   │   ├── Requests/{GuardarColaboradorRequest,ListarColaboradoresRequest}.php
│   │   ├── Resources/{ColaboradorResumenResource,ColaboradorDetalleResource}.php
│   │   └── Facades/PersonalFacade.php
│   └── Routes/api.php
├── app/Modules/Shared/                                             (ampliación aditiva: tenant en la cuenta)
│   ├── Application/{DTO/UsuarioDTO.php, Services/SharedService.php}   (+ tenantId; + getContextoDeTenant(userId))
│   ├── Infrastructure/Persistence/Eloquent/Models/{UserModel.php,TenantModel.php}
│   └── Presentation/Resources/UsuarioResource.php                  (+ tenant {id, nombre})
├── database/
│   ├── migrations/2026_10_15_00000{1..4}_{create_tenants_table,add_tenant_id_to_users_table,create_colaboradores_table,create_colaborador_cuentas_table}.php
│   ├── factories/ColaboradorFactory.php
│   └── seeders/{PersonalSeeder,UsuariosInicialesSeeder,DatabaseSeeder}.php
├── config/hrms.php                                                 (+ gestion_personal.escritura_habilitada)
└── tests/{Unit,Feature}/GestionPersonal/…

frontend/src/
├── modules/gestionPersonal/fichas/
│   ├── api.ts, text.ts, validation.ts, nav.tsx
│   ├── hooks/{useColaboradores,useListadoParams,useGuardarFicha,useMiFicha}.ts
│   ├── pages/{ColaboradoresPage,FichaFormPage,FichaDetallePage,MiFichaPage}.tsx
│   └── components/{ColaboradoresTable,FichaFormulario,FichaDatos,DatoOpcional,SeccionLaboral,Trayectoria,AlertaDuplicada,RequireAdmin}.tsx
├── test/fichasFixtures.ts
├── App.tsx                                                         (rutas y navegación)
└── e2e/fichas.spec.ts
design/
├── GP-01_Fichas-de-colaboradores.html                              (prototipo de referencia, ya presente)
├── ColaboradorFicha.dc.html                                        (nuevo tablero de detalle; + entrada en canvas.json)
└── DESIGN.md                                                       (actualizar primero: §4, §5, §8 y §9)
```

**Structure Decision**: Aplicación web con backend y frontend separados. Módulo nuevo `GestionPersonal` (backend) y
`modules/gestionPersonal/fichas/` (frontend: el módulo de negocio coincide con el backend y la funcionalidad es `fichas`,
como pide la constitución). GP-02, GP-03, GP-05 y GP-07 añadirán sus propias carpetas hermanas y usarán `PersonalFacade`.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| Rutas `/api/colaboradores` y `/api/mi-ficha` (la constitución pide `/api/<modulo>/`) | Contrato fijado por el insumo; recurso consumido por otras GP | `/api/gestion-personal/colaboradores` contradice el insumo; mismo precedente en 000–004 |
| `tenant_id` por columna sobre SQLite en lugar de esquema PostgreSQL por tenant (insumo) | La spec exige tenants desde esta feature y la constitución fija SQLite; no hay PostgreSQL en el repositorio | Esquema por tenant exige enmendar la constitución, un servicio de conmutación de conexión y migrar 000–004; es otra feature |
| Tablas `tenants` y columna `users.tenant_id` (módulo `Shared`) | Clarificación Q1: GP-01 añade el tenant a cuenta y sesión | Un tenant implícito único no permite probar FR-001/SC-004 |
| Tabla `colaborador_cuentas` (asociación cuenta-ficha y 3 permisos independientes), solo lectura y sembrada | Clarificación Q2; el insumo cita permisos con nombre propio (`personal.ficha_propia.consultar`) que no existen | Construir roles/permisos o la gestión de cuentas es la feature de identidad y acceso (fuera de alcance) |
| Columnas `identificacion_clave`, `nombres_busqueda`, `apellidos_busqueda`, `version`, `operacion_id`, `operacion_hash` en la ficha | Unicidad sin mayúsculas/espacios (FR-011), búsqueda sin tildes en SQLite (FR-023), edición concurrente (FR-019), alta idempotente (FR-014) | Comparar y normalizar al vuelo no usa índices; `updated_at` falla con resolución de 1 s; sin clave de operación el doble envío depende solo de la unicidad de identificación |
| `Idempotency-Key` obligatoria en `POST` (la spec solo pide no duplicar) | El insumo lo fija; permite devolver el mismo resultado (200) al reintento | La unicidad de identificación rechaza el reintento con un error engañoso al usuario que sí guardó |
| Puertos `Lector*` con adaptadores «no instalado» | GP-02, Estructura-unidades y GP-03/05 no existen; FR-029 exige distinguir «No registrado» de «Información no disponible» | Consultar `posiciones` (tabla de Estructura) viola I; datos simulados inventan asignaciones (FR-028) |
| Código `EMP-###` derivado del `id` global en la misma transacción (con código temporal único antes de conocer el `id`) | SQLite no tiene secuencias; patrón ya usado en `COMP-` y `PU-` | `MAX+1` sin bloqueo es una carrera; un contador por tenant añade una tabla sin que la spec exija numeración sin saltos |
| Bandera `hrms.gestion_personal.escritura_habilitada` | El insumo exige rollback que «deshabilita la escritura nueva conservando fichas» | Revertir la migración borra las fichas |
| Cambio aditivo en `Shared` (`UsuarioDTO`, `UsuarioResource`, `SharedService`) | El tenant debe viajar con la sesión | Que `GestionPersonal` lea `users` directamente viola I |

## Diseño de frontend

### Rutas

| Ruta | Pantalla (del spec) | Roles con acceso |
|---|---|---|
| `/colaboradores` | Listado de colaboradores | Solo Administrador (el Usuario ve «sin permisos») |
| `/colaboradores/nuevo` | Formulario de ficha (crear) | Solo Administrador |
| `/colaboradores/:colaboradorId` | Detalle de ficha | Solo Administrador |
| `/colaboradores/:colaboradorId/editar` | Formulario de ficha (editar) | Solo Administrador |
| `/mi-ficha` | Mi ficha (detalle propio, solo lectura) | Cualquier cuenta autenticada; el servidor decide con asociación y permiso |

### Componentes

- **Reutilizados de `shared/ui`**: `Alert` (error general, duplicado, conflicto), `Badge`, `Button`, `Breadcrumb`,
  `EmptyState`, `ErrorState`, `Skeleton`, `Pagination`, `SearchField`, `SelectField`, `TextField`, `TextareaField`.
- **Nuevos, solo del módulo**: `ColaboradoresPage`, `FichaFormPage`, `FichaDetallePage`, `MiFichaPage`,
  `ColaboradoresTable`, `FichaFormulario` (cuatro `fieldset` con contadores), `FichaDatos` (pares etiqueta-valor),
  `DatoOpcional` (muestra «No registrado» y distingue 0), `SeccionLaboral` (puesto, unidad, estado con «Información no
  disponible» y Reintentar), `Trayectoria` (línea de tiempo con marca «Programado»), `AlertaDuplicada` (enlace «Abrir
  ficha existente»), `RequireAdmin`, `nav.tsx` (ítems «Colaboradores» para el Administrador y «Mi ficha»).
- **Design system (primera tarea de frontend)**: ampliar `design/DESIGN.md` y añadir `design/ColaboradorFicha.dc.html`
  antes de implementar (Constitución III). Debe cubrir: pares etiqueta-valor con «No registrado», sección con pestañas
  (Personal y puesto; Desarrollo y Documentos solo cuando existan), línea de tiempo con hecho programado, Alert con
  enlace («Abrir ficha existente») y contadores `n/máximo`. Diferencias del prototipo `GP-01_*.html` con la spec, a
  registrar en `DESIGN.md` §9: título «Fichas de colaboradores» → «Colaboradores»; el correo bajo el nombre en la tabla no
  se muestra (FR-022); «Estado laboral» → «Estado»; «Editar datos» → «Editar»; el nombre de la organización en el header
  no se implementa aquí; la barra «Controles del prototipo», el progreso/«completitud», la formación académica, la
  experiencia anterior, la movilidad, los documentos, las categorías, la contratación desde Selección y la exportación
  pertenecen a GP-02, GP-03, GP-05, GP-06 y GP-07 y no se implementan.

### Datos y estado

- Listado: `GET /api/colaboradores?q=&estado=&unidad_id=&page=&per_page=`; clave `["colaboradores","lista",{q,estado,unidad_id,page}]`.
  Filtros y página en la URL; debounce de 300 ms con cancelación de la petición obsoleta (`signal` de TanStack Query) y
  vuelta a la página 1 al cambiar filtros. Opciones de los filtros: `GET /api/colaboradores/filtros`.
- Detalle: `GET /api/colaboradores/{id}`; clave `["colaboradores","detalle",id]`. «Reintentar» de una sección laboral
  vuelve a pedir el detalle. Mi ficha: `GET /api/mi-ficha`; clave `["mi-ficha"]`.
- Crear: `POST /api/colaboradores` con `Idempotency-Key` (`crypto.randomUUID()` al montar el formulario; se conserva en
  reintentos con el mismo contenido y se regenera tras un 422 `CLAVE_DE_OPERACION_REUTILIZADA`). Editar: `PATCH
  /api/colaboradores/{id}` con `version`. Mutaciones sin reintentos; tras éxito se invalidan `["colaboradores"]` y se
  abre el detalle con aviso.
- 409 `FICHA_MODIFICADA`: alerta con «Actualizar datos» que recarga la ficha conservando lo escrito para comparar; no
  reenvía solo.
- Rol: `useCurrentUser` del contrato público de autenticación; el menú muestra «Colaboradores» solo al Administrador.

### Validación

- Réplica de `GuardarColaboradorRequest`: tipo (Cédula/Pasaporte/Otro), número ≤ 50, nombres y apellidos 1–150 tras
  recortar; nacionalidad ≤ 100, género y estado civil ≤ 80, hijos entero ≥ 0, dirección ≤ 500, teléfono ≤ 30 con
  `^\+?[0-9 ()\-]+$`, correo ≤ 254 con formato; contacto de emergencia: nombre (≤ 150) y teléfono (≤ 30) juntos o los
  tres vacíos, relación ≤ 100; fecha de nacimiento no futura (la fecha del navegador es una ayuda; decide el servidor).
- Mapeo de errores: 422 a campos (`numero_identificacion`, `emergencia_nombre`, …); `IDENTIFICACION_DUPLICADA` a la
  alerta con «Abrir ficha existente»; 404 a «La ficha no existe»; 403 `CUENTA_SIN_FICHA` y `SIN_PERMISO_FICHA` a los
  mensajes de Mi ficha; 503 `INFORMACION_NO_DISPONIBLE` al estado de error con Reintentar.
