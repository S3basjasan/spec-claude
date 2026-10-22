# Implementation Plan: Configuración de políticas operativas de personal

**Branch**: `016-politicas-operativas-personal` | **Date**: 2026-10-20 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/016-politicas-operativas-personal/spec.md` y el insumo técnico «Configuración de políticas operativas de personal» aportado por el usuario. El insumo declara PostgreSQL con esquema por tenant y rutas/tablas como propuesta, y pide contrastarlo con el repositorio; donde difiere de la realidad del repositorio o de la constitución prevalecen estas últimas (ver [research.md](research.md) §Divergencias con el insumo). El usuario indicó además que **por ahora hay un solo tenant**.

## Summary

Instalar en Gestión de Personal la administración de siete políticas generales por organización (Permisos, Ausencias, Licencias, Vacaciones, Ascensos, Traslados, Offboarding) con versiones inmutables: un borrador editable por tipo, publicación inmediata o programada, retiro de una versión programada y sustitución automática por vigencia. El catálogo de tipos, parámetros y reglas fijas vive en el servidor y se valida allí; la interfaz solo lo pinta. Además se expone el contrato que los procesos consumidores (novedades, movimientos, offboarding) usarán para elegir la versión aplicable al presentar una solicitud y conservarla, sin que esta funcionalidad implemente esos procesos.

Enfoque técnico: tres tablas nuevas (`politicas_personal` = cabecera por tipo que sirve de punto de serialización y contador de numeración; `politica_versiones` = borradores y versiones con sus condiciones selladas; `politica_acciones` = auditoría de administración de solo inserción) más reglas de dominio (`CatalogoDePoliticas`, `ReglasDePolitica`, `EstadoDeVersion` derivado de instantes, `DiferenciasDePolitica`). Todo cambio de estado ocurre en una transacción corta que toma el bloqueo de la cabecera del tipo, vuelve a leer el estado y es idempotente por `Idempotency-Key`. El estado temporal (Programada, Vigente, Sustituida) **se deriva de los instantes** y no de una etiqueta que un proceso auxiliar deba actualizar. Los consumidores entran por una fachada pública (`PoliticasFacade`) que devuelve la versión aplicable en el instante de presentación, dentro de la unidad de trabajo del llamador. Frontend: panel `Políticas de personal` (listado, detalle con versiones y comparación, editor de borrador con publicación) y un componente de consulta de condiciones reutilizable por los procesos.

## Technical Context

**Language/Version**: PHP 8.3 (`composer.json`; entorno local 8.5.5) / Laravel 13 (backend); TypeScript estricto, React 19 (frontend)

**Primary Dependencies**: solo las existentes (Sanctum, Eloquent; Vite, Tailwind 4, React Router, TanStack Query, Axios, React Hook Form, Zod). **Sin dependencias nuevas.**

**Storage**: SQLite (predeterminado de la constitución y del repositorio) con aislamiento por columna `tenant_id`; las reglas que el motor no expresa con `CHECK` se refuerzan con *triggers*, igual que en 013/014. El insumo propone PostgreSQL con esquema por tenant: no existe en el repositorio. Los parámetros se guardan como JSON en una columna de texto, validados por el servidor con el catálogo del tipo. El diseño evita SQL exclusivo de un motor salvo los índices únicos parciales y los *triggers* de inmutabilidad (equivalentes documentados en data-model.md).

**Testing**: Pest (Feature/Unit; reloj fijado con `Carbon::setTestNow`; concurrencia simulada entrelazando operaciones dentro de transacciones, patrón ya usado en 014), Pint; Vitest + Testing Library; Playwright (E2E ya adoptado)

**Target Platform**: Web (SPA + API REST), 320–1440 px, WCAG 2.2 AA

**Project Type**: web-service + SPA (monolito modular)

**Performance Goals**: listado de los siete tipos y detalle de un tipo en menos de 1 s con cientos de versiones por tipo (SC-008 exige comparar en menos de 1 minuto de uso, sin retardos técnicos perceptibles); la consulta de la política aplicable es una lectura indexada por `(politica_id, inicio_vigencia)`.

**Constraints**: una versión publicada no se edita ni se elimina; un único borrador activo por tipo; como máximo una versión programada por tipo; numeración monótona sin reutilización; estado derivado del reloj del servidor; reintentos idempotentes; ningún cambio altera solicitudes ya presentadas; sin parámetros fuera del catálogo; el borrador no depende de que Estructura esté disponible; errores públicos seguros.

**Scale/Scope**: 5 historias, 45 FR, 3 tablas, 9 endpoints administrativos + 1 de consulta de condiciones aplicables, 1 fachada pública, 3 pantallas administrativas + 1 componente de consulta reutilizable.

## Constitution Check

*GATE: debe pasar antes de Phase 0 research. Reevaluado tras Phase 1.*

| Principio | Evaluación |
|---|---|
| I. Clean Architecture modular | Código en `backend/app/Modules/GestionPersonal/{Domain,Application,Infrastructure,Presentation}` (el insumo nombra el módulo de Personal sin ruta; se usa el existente). Casos de uso explícitos por acción; controladores delgados sin consultas; dominio sin Laravel (catálogo, reglas, estados e instantes como `DateTimeImmutable` tras `RelojPort`); persistencia tras `PoliticaRepositoryInterface`. Los consumidores usan solo `PoliticasFacade`, nunca las tablas. **Pasa.** |
| Rutas `/api/<modulo>/` | Prefijo nuevo `/api/politicas-personal` (recurso propio, como `categorias-documentales` en 013). Se registra aquí la misma excepción por precedente que `/api/colaboradores` y `/api/mi-ficha`. **Pasa (precedente; registrado aquí).** |
| Endpoints admin con Sanctum + `admin` | Todas las rutas de administración van bajo `auth:sanctum` + `admin`. La consulta de condiciones aplicables va solo bajo `auth:sanctum`, es de lectura, limitada al tenant de la sesión y no expone borradores, auditoría ni responsables. **Pasa.** |
| Módulo no accede a tablas de otro módulo | El módulo no lee ni escribe tablas ajenas. Los consumidores (otros módulos) acceden por la fachada y por un contrato de transacción; no escriben las tablas de configuración. La integración con Estructura (puestos destino y niveles) pertenece a los procesos de movimientos, no a esta funcionalidad. **Pasa.** |
| II. Frontend | `frontend/src/modules/gestionPersonal/politicas/` (api, hooks, components, pages, validation, text, nav, index); páginas sin Axios; mutaciones sin reintento; loading/empty/error/success; búsqueda y filtros en URL; confirmaciones accesibles; conflicto de edición conserva el contenido. **Pasa.** |
| III. Diseño | `design/` no tiene un prototipo de esta pantalla. Se compone con los patrones Listado, Detalle y Formulario de `DESIGN.md`; se añade una fila en `DESIGN.md` §9 con las decisiones (línea de versiones, diálogo de publicación, comparación) antes de dar la feature por completa. **Pasa con acción registrada (T de DESIGN.md).** |
| IV. Calidad / dependencias | Pest + Pint, ESLint + tsc + Vitest + build y E2E. Sin dependencias nuevas. Auditoría sin datos personales (solo ids de actor, motivo y hashes). **Pasa.** |

Sin violaciones ni condiciones abiertas.

## Project Structure

### Documentation (this feature)

```text
specs/016-politicas-operativas-personal/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── api-politicas.md
│   ├── catalogo-de-parametros.md
│   └── consumo-de-politicas.md
├── checklists/requirements.md
└── tasks.md            # /speckit-tasks
```

### Source Code (repository root)

```text
backend/
├── database/migrations/2026_10_20_00000{1,2,3}_create_politicas_*.php   # politicas_personal, politica_versiones, politica_acciones
├── app/Modules/GestionPersonal/
│   ├── Domain/
│   │   ├── Entities/{PoliticaDePersonal,VersionDePolitica,AccionDePolitica}.php
│   │   ├── ValueObjects/{TipoDePolitica,EstadoDeVersion,InicioDeVigencia}.php
│   │   ├── Services/{CatalogoDePoliticas,ReglasDePolitica,DiferenciasDePolitica,ClasificadorDeVersiones}.php   # PHP puro
│   │   ├── Repositories/PoliticaRepositoryInterface.php
│   │   └── Exceptions/{PoliticaNoEncontrada,BorradorExistente,BorradorModificado,ProgramadaExistente,VigenciaIniciada,InicioEnElPasado,VersionNoRetirable,PoliticaNoConfigurada,PoliticaNoAdmiteSolicitudes,PoliticaNoDisponible}.php   # ClaveDeOperacion*, DatosInvalidos, EscrituraDeshabilitada se reutilizan
│   ├── Application/
│   │   ├── DTO/{PoliticaResumenDTO,PoliticaDetalleDTO,VersionDTO,PrevisualizacionDTO,PoliticaAplicableDTO,DatosDePublicacionDTO}.php
│   │   ├── Ports/RelojPort.php                                          # TransaccionPort y ContextoDeTenantPort ya existen
│   │   ├── Services/{ResolvedorDePoliticaAplicable,ArmadorDeVersion}.php
│   │   └── UseCases/{ListarPoliticas,ObtenerPolitica,ObtenerVersion,CrearBorrador,EditarBorrador,DescartarBorrador,PrevisualizarPublicacion,PublicarVersion,RetirarVersion,ConsultarPoliticaAplicable}.php
│   ├── Infrastructure/
│   │   ├── Adapters/RelojDelSistema.php
│   │   └── Persistence/Eloquent/{Models/{PoliticaPersonalModel,PoliticaVersionModel,PoliticaAccionModel}.php,Repositories/EloquentPoliticaRepository.php}
│   └── Presentation/
│       ├── Controllers/{PoliticaPersonalController,PoliticaBorradorController}.php
│       ├── Requests/{CrearBorradorRequest,EditarBorradorRequest,PublicarVersionRequest,RetirarVersionRequest}.php
│       ├── Resources/{PoliticaResumenResource,PoliticaDetalleResource,VersionResource,PoliticaAplicableResource}.php
│       └── Facades/PoliticasFacade.php                                    # contrato público para otros módulos
├── database/seeders/PersonalSeeder.php                                    # cabeceras de los 7 tipos y una política publicada de ejemplo
├── config/hrms.php (gestion_personal.politicas_habilitado)
└── tests/{Feature,Unit}/GestionPersonal/Politicas/

frontend/
├── src/modules/gestionPersonal/politicas/
│   ├── api.ts, text.ts, validation.ts, nav.tsx, index.ts
│   ├── hooks/{usePoliticas,usePolitica,useBorrador,usePublicar,useRetirar,usePoliticaAplicable}.ts
│   ├── components/{TablaDePoliticas,FormularioDeParametros,EditorDeTareas,CondicionesFijas,LineaDeVersiones,ComparacionDeVersiones,DialogoDePublicacion,DialogoDeRetiro,CondicionesAplicables}.tsx
│   └── pages/{PoliticasPage,PoliticaDetallePage,BorradorPage}.tsx
├── src/App.tsx            # rutas /politicas-personal… (Administrador) y entrada de navegación
└── e2e/politicas.spec.ts
design/DESIGN.md (§9)
```

**Structure Decision**: aplicación web existente (backend + frontend); la funcionalidad añade una subárea por capa dentro de `GestionPersonal`/`gestionPersonal`. Las ediciones en otras funcionalidades se limitan al proveedor de servicios (bindings, ruta de políticas), `routes`, `config/hrms.php`, el seeder y `App.tsx`. El módulo de Estructura no se modifica.

## Decisiones clave (detalle en research.md)

1. **Cabecera por tipo como punto de serialización** (`politicas_personal`, única por tenant y tipo): publicar, retirar, crear borrador y seleccionar la política al presentar una solicitud toman el mismo bloqueo (actualización sin cambio de valor de la fila, patrón de `confirmarActiva` en 013, que bloquea fila en motores con bloqueo por fila y escritura en SQLite) y releen el estado dentro de la transacción. El orden de confirmación decide la versión aplicable.
2. **Estado derivado de instantes**: `Borrador` (sin `publicada_en` ni `descartada_en`), `Retirada` (`retirada_en`), `Programada` (publicada, no retirada, `inicio_vigencia` > ahora), `Vigente` (la publicada no retirada con mayor `inicio_vigencia` ≤ ahora, desempate por número), `Sustituida` (publicada no retirada con inicio ≤ ahora que no es la vigente). Ningún proceso periódico cambia etiquetas; un retraso de un planificador no puede dejar una versión vencida como vigente.
3. **Condiciones selladas al publicar**: la versión conserva `condiciones` (JSON canónico: tipo, número, parámetros normalizados, reglas fijas vigentes en ese momento y esquema) y su `hash`; los consumidores copian esa representación. Triggers impiden modificar columnas selladas y eliminar versiones publicadas.
4. **Numeración monótona**: `ultimo_numero` en la cabecera, incrementado bajo bloqueo al publicar; nunca se reutiliza (ni tras un retiro) y nunca se asigna a un borrador.
5. **Un borrador activo por tipo**: índice único parcial sobre la cabecera y estado, con reglas en el caso de uso (409 `BORRADOR_EXISTENTE` indicando el borrador existente).
6. **Publicar o programar exige que no haya otra versión programada** (también la publicación inmediata, para evitar que una programada posterior sustituya inesperadamente a la recién publicada); hay que retirarla primero.
7. **Idempotencia y auditoría en una sola tabla**: `politica_acciones` (solo inserción) guarda publicación, retiro, descarte y creación de borrador con actor, momento, motivo, `operacion_id` y `operacion_hash`; una repetición con la misma clave y contenido devuelve el resultado original; con otro contenido, 409.
8. **Catálogo en servidor**: `CatalogoDePoliticas` define por tipo los parámetros admitidos (clave, tipo, rango, texto de ayuda) y las reglas fijas visibles; `ReglasDePolitica` valida (campos desconocidos, rangos, tareas) y rechaza contradicciones; el detalle expone la `definicion` para que la interfaz pinte formularios sin lógica propia.
9. **Consumo por fachada**: `PoliticasFacade::aplicablePara(tenantId, tipo, instante)` y `…::seleccionarParaPresentar(...)` (toma el bloqueo dentro de la transacción del llamador). Distinguen `NoConfigurada`, `NoAdmiteSolicitudes` y `NoDisponible`; nunca devuelven valores supuestos. Los consumidores guardan `politica_version_id` (FK restrictiva) y la copia de `condiciones`.
10. **Inicio de vigencia en la zona de la organización**: la interfaz envía fecha y hora local; el servidor las resuelve en la zona del tenant (hueco de cambio de hora → primer instante válido posterior; repetición → primera ocurrencia) y devuelve el instante UTC y el local para mostrarlo en la confirmación.
11. **404 uniforme** para tipo, versión o borrador inexistente o de otro tenant; 403 al Colaborador en rutas administrativas; 409 para borrador existente, versión obsoleta, programada existente y clave reutilizada; 422 para validación; 503 `POLITICA_NO_DISPONIBLE` en consulta de condiciones y `ESCRITURA_DESHABILITADA` con el interruptor apagado.

## Diseño de frontend

### Rutas
| Ruta | Pantalla (del spec) | Roles con acceso |
|---|---|---|
| `/politicas-personal` | Listado de políticas | Administrador |
| `/politicas-personal/:tipo` | Detalle de política y versiones | Administrador |
| `/politicas-personal/:tipo/borrador` | Editor de borrador y publicación | Administrador |
| (componente `CondicionesAplicables`) | Condiciones aplicables a una solicitud | Cualquier usuario autenticado, dentro de los procesos consumidores |

`:tipo` usa el identificador en minúsculas (`permisos`, `ausencias`, `licencias`, `vacaciones`, `ascensos`, `traslados`, `offboarding`).

### Componentes
- **Nuevos**: `TablaDePoliticas`, `FormularioDeParametros` (campos según la `definicion` del servidor), `EditorDeTareas` (alta, orden y quita de tareas de offboarding), `CondicionesFijas` (solo lectura), `LineaDeVersiones`, `ComparacionDeVersiones`, `DialogoDePublicacion`, `DialogoDeRetiro`, `CondicionesAplicables` (exportado por `index.ts` para los procesos).
- **Del design system**: `Button`, `Alert`, `Badge`, `Breadcrumb`, `ConfirmDialog`, `EmptyState`, `ErrorState`, `Modal`, `SelectField`, `Skeleton`, `TextField`, `TextareaField`.

### Datos y estado
- TanStack Query: listado `['politicas']`, detalle `['politica', tipo]`, versión `['politica', tipo, 'version', id]`, aplicable `['politica-aplicable', tipo]`. Las mutaciones invalidan listado y detalle; no se reintentan.
- Filtros del listado (tipo, situación) en la URL.
- Conflicto de edición (409 `BORRADOR_MODIFICADO`): se conserva el contenido pendiente en el formulario, se muestra el aviso y se ofrece «Recargar versión actual»; nunca se reenvía automáticamente.
- `CondicionesAplicables` distingue «Política sin configurar», «No admite nuevas solicitudes» y una falla temporal (error con Reintentar); una falla nunca se muestra como ausencia de requisitos.

### Validación
- Zod espeja los rangos del servidor (anticipación entero ≥ 0, textos con máximos de FR-024e, tareas únicas sin distinguir mayúsculas ni tildes, hasta 30); el servidor es la autoridad y sus errores por campo se muestran junto al campo.
- Publicar exige motivo, revisor y observaciones opcionales dentro del máximo, y la elección de inicio (inmediato o fecha y hora local futuras).

## Complexity Tracking

Sin violaciones de la constitución; no se requiere justificación.
