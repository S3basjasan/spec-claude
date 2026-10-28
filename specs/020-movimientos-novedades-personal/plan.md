# Implementation Plan: Registro de movimientos y novedades de personal

**Revisión 2026-10-28**: los apartados originales describen la entrega inicial. La reformulación de promociones internas al final y su contrato complementario están pendientes de implementación.

**Branch**: `020-movimientos-novedades-personal` | **Date**: 2026-10-21 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/020-movimientos-novedades-personal/spec.md`

## Summary

Implementar, dentro del módulo `GestionPersonal`, el ciclo **solicitar → revisar → aprobar/rechazar → aplicar** para ascensos y traslados (movimientos) y para permisos, licencias, vacaciones y ausencias (novedades), para el Administrador de Talento Humano y el Colaborador.

Enfoque técnico (detalle y alternativas en [research.md](research.md)):

- Una actuación común (`actuaciones_personal`) con detalle por categoría, documentos referenciados y un registro append-only de transiciones; el hecho laboral derivado es una nueva fila de `asignaciones_laborales` con `actuacion_id`.
- Las condiciones salen de `PoliticasFacade`: se muestran sin reservar versión y se fijan al presentar con `seleccionarParaPresentar` bajo el bloqueo de la ficha, en la misma transacción.
- Toda mutación se serializa con `ejecutarBajoBloqueo` (bloqueo por ficha), es idempotente por `Idempotency-Key` + hash y usa `version` optimista.
- La evaluación del puesto destino entra por un puerto sin adaptador real (`EvaluacionDelDestinoPort`): la aprobación de movimientos queda impedida hasta que exista el contrato.
- La aplicación es un caso de uso atómico (cierra y abre asignaciones) invocado por un comando programado con identidad de sistema y por el reintento manual del Administrador; una incidencia no deja cambios parciales.
- Frontend: módulo `movimientos` con listado del Administrador (filtros en URL), proponer movimiento, presentar/registrar novedades, detalle con resumen de comprobaciones y «Mis solicitudes» del Colaborador.

## Technical Context

**Language/Version**: PHP 8.3+ (local 8.5.5), Laravel 13; TypeScript estricto, React 19 (valores reales de `composer.json` y `package.json`; la constitución exige respetarlos).

**Primary Dependencies**: las existentes — Sanctum, Pest, Pint; Vite, React Router, TanStack Query, Axios, React Hook Form, Zod, Vitest + Testing Library, Playwright. **Sin dependencias nuevas.**

**Storage**: SQLite con aislamiento por `tenant_id`, índices parciales y triggers (SQL crudo), como GP-02 y 016. «PostgreSQL por esquema» del insumo es la intención arquitectónica histórica, no el entorno desplegado; el diseño usa solo características portables salvo los triggers, protegidos por driver.

**Testing**: Pest (Feature y Unit) con SQLite `:memory:`; Vitest + Testing Library; un E2E Playwright del flujo principal con la base `e2e.sqlite`.

**Target Platform**: SPA + API Laravel en un monolito modular; planificador de Laravel para la aplicación automática.

**Project Type**: aplicación web (backend + frontend).

**Performance Goals**: listado paginado del tenant (índice `(tenant_id, estado, tipo, presentada_en)`); resumen con un solo `GROUP BY`; el procesador evalúa solo movimientos con fecha efectiva alcanzada.

**Constraints**: sin modificar la semántica de GP-01…GP-07 (solo una columna nullable agregada); cero asignaciones parciales; reintentos y concurrencia sin duplicados; errores públicos seguros; Colaborador sin acceso a campos restringidos (recurso distinto, no filtrado en la UI).

**Scale/Scope**: 6 tipos de actuación, 2 roles, 5 historias, ~13 endpoints de Administrador y 5 del Colaborador, 5 pantallas.

## Constitution Check

*GATE: pasa antes de la investigación y se reevalúa tras el diseño.*

| Principio | Evaluación |
|---|---|
| I. Backend modular con Clean Architecture | **Cumple.** Código en `app/Modules/GestionPersonal/{Domain,Application,Infrastructure,Presentation}`; controladores delgados que traducen excepciones; casos de uso explícitos por acción; persistencia tras `ActuacionRepositoryInterface`; Estructura, políticas y documentos por puertos/fachadas; rutas bajo `/api/movimientos-personal` y `/api/mis-solicitudes`; admin con `auth:sanctum` + `admin`; las rutas del Colaborador son la excepción justificada de «mi-ficha» (autenticación + asociación, igual que `api/mi-ficha`). Nada en `Shared/`. |
| II. Frontend modular, accesible y tipado | **Cumple.** `modules/gestionPersonal/movimientos/` con `api.ts`, `hooks/`, `components/`, `pages/`, `validation.ts`, `text.ts`; Axios solo vía `httpClient`; escrituras sin reintento; filtros en la URL; textos centralizados en español; loading/empty/error/success; formularios con errores por campo y bloqueo de doble envío; confirmación accesible en aprobar/rechazar/retirar. Importa de `politicas` solo por su `index.ts` público. |
| III. Diseño como referencia visual | **Cumple.** Se agregan filas de la sección de movimientos a `design/DESIGN.md` (listado, detalle con comprobaciones, formulario). No hay desviación visual. |
| IV. Calidad, seguridad y dependencias | **Cumple.** Pruebas Pest por comportamiento (permisos, aislamiento, atomicidad, concurrencia, idempotencia), Vitest y un E2E; sin dependencias nuevas; sin datos personales reales; entradas validadas en `FormRequest`. |

Reevaluación tras el diseño: sin cambios; sin entradas en *Complexity Tracking*. Excepciones registradas: ninguna.

## Project Structure

### Documentation (this feature)

```text
specs/020-movimientos-novedades-personal/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── api-movimientos.md
│   └── puertos-y-procesos-internos.md
├── checklists/requirements.md
└── tasks.md            # lo genera /speckit-tasks
```

### Source Code (repository root)

```text
backend/
├── app/Modules/GestionPersonal/
│   ├── Domain/
│   │   ├── Entities/Actuacion.php
│   │   ├── ValueObjects/{TipoDeActuacion,EstadoDeActuacion,ResultadoDeEvaluacion}.php
│   │   ├── Services/{ReglasDeActuacion,MaquinaDeEstados,DetectorDeCruces,VerificadorDeEvaluacion}.php
│   │   ├── Repositories/ActuacionRepositoryInterface.php
│   │   └── Exceptions/…           # una por causa distinguible (ver contrato de errores)
│   ├── Application/
│   │   ├── Ports/EvaluacionDelDestinoPort.php            (+ ContextoDeTenantPort::deTenant)
│   │   ├── DTO/…
│   │   ├── Services/{ResolvedorDeSolicitante,ArmadorDeDetalleDeActuacion}.php
│   │   └── UseCases/{PresentarNovedad,InformarAusencia,ProponerMovimiento,PasarARevision,AprobarActuacion,
│   │                 RechazarActuacion,RetirarNovedad,RevisarAusencia,AplicarMovimiento,
│   │                 AplicarMovimientosProgramados,ListarActuaciones,ResumirActuaciones,
│   │                 ObtenerActuacion,ObtenerContextoDeMovimiento,ListarDocumentosReferenciables,
│   │                 ListarMisSolicitudes,ObtenerMiSolicitud}.php
│   ├── Infrastructure/
│   │   ├── Adapters/EvaluacionDelDestinoNoInstalada.php
│   │   ├── Persistence/Eloquent/Repositories/EloquentActuacionRepository.php
│   │   ├── Console/AplicarMovimientosProgramadosCommand.php
│   │   └── Providers/GestionPersonalServiceProvider.php   (enlaces y comando)
│   ├── Presentation/{Controllers,Requests,Resources}/…    # ActuacionController, MiSolicitudController, …
│   └── Routes/api.php                                     (rutas nuevas)
├── database/migrations/2026_10_24_00000{1,2,3,4}_*.php
├── routes/console.php                                     (programación del comando)
└── tests/{Feature,Unit}/GestionPersonal/Movimientos/…

frontend/
├── src/modules/gestionPersonal/movimientos/
│   ├── api.ts  text.ts  validation.ts  nav.tsx  index.ts
│   ├── hooks/{useActuaciones,useMisSolicitudes,useAccionesDeActuacion}.ts
│   ├── components/{TablaDeActuaciones,FiltrosDeActuaciones,ResumenDeComprobaciones,
│   │               FormularioDeNovedad,SelectorDeRespaldos,DialogoDeResolucion,AvisoDeCruces,…}.tsx
│   └── pages/{MovimientosPage,ProponerMovimientoPage,RegistrarNovedadPage,ActuacionDetallePage,
│              MisSolicitudesPage,NuevaSolicitudPage}.tsx
├── src/test/movimientosFixtures.ts
├── src/App.tsx                                            (rutas y navegación)
└── e2e/movimientos.spec.ts
design/DESIGN.md                                           (filas nuevas)
```

**Structure Decision**: aplicación web con módulo existente `GestionPersonal` (backend) y carpeta de funcionalidad `movimientos` (frontend), sin árboles nuevos ni código en `Shared`.

## Complexity Tracking

Sin violaciones de la constitución que justificar.

## Diseño de frontend

### Rutas

| Ruta | Pantalla (del spec) | Roles con acceso |
|---|---|---|
| `/movimientos-personal` | Listado de movimientos y novedades (con resumen y filtros en la URL) | Administrador |
| `/movimientos-personal/proponer` | Propuesta de ascenso o traslado | Administrador |
| `/movimientos-personal/novedad` | Formulario de novedad registrada en nombre del colaborador y registro de ausencia (`?tipo=`) | Administrador |
| `/movimientos-personal/:id` | Detalle de movimiento o novedad, resolución y aplicación | Administrador |
| `/mis-solicitudes` | Mis solicitudes (listado y detalle comunicable) | Colaborador |
| `/mis-solicitudes/nueva` | Formulario de novedad propia | Colaborador |

### Componentes

- **Nuevos**: `TablaDeActuaciones`, `FiltrosDeActuaciones`, `ResumenDeComprobaciones` (tres estados separados), `FormularioDeNovedad`, `SelectorDeRespaldos`, `DialogoDeResolucion`, `AvisoDeCruces`, `EstadoDeActuacion` (insignia).
- **Del design system**: `Alert`, `Badge`, `Button`, `ConfirmDialog`, `EmptyState`, `ErrorState`, `Pagination`, `SelectField`, `Skeleton`, `TextField`, `TextareaField`, `Tabs`, `Breadcrumb`; `CondicionesAplicables` de la feature 016.

### Datos y estado

- Cada pantalla consume los endpoints de [contracts/api-movimientos.md](contracts/api-movimientos.md); las claves de TanStack Query se parten por rol (`['movimientos', …]` y `['mis-solicitudes', …]`).
- Las mutaciones invalidan listado, resumen y detalle; ninguna se reintenta; cada formulario genera la `Idempotency-Key` con `useClaveDeOperacion` y la renueva solo tras un resultado terminal.
- Estado en la URL: `tipo`, `estado`, `grupo`, `colaborador_id`, `desde`, `hasta`, `page`.

### Validación

- Zod replica: fechas `AAAA-MM-DD`, `fecha_fin ≥ fecha_inicio`, justificación y documento según la política mostrada, máximos de texto (1000). Los 422 del servidor se asignan a campos por clave de `errors`; los códigos de negocio (`ANTICIPACION_INSUFICIENTE`, `CRUCE_SIN_RECONOCER`, `EVALUACION_NO_VERIFICABLE`…) se muestran como alerta con el texto propio de cada causa, distinta de «lista vacía» y de error de red.
- En 409 (`SOLICITUD_MODIFICADA`) el formulario conserva lo escrito, explica el cambio y ofrece «Actualizar» sin reenviar la versión obsoleta.

## Reformulación de promociones internas · 2026-10-28 (pendiente)

La arquitectura se amplía según [promocion-interna.md](contracts/promocion-interna.md). Desarrollo y Formación conserva evaluaciones, niveles y diagnósticos; Personal conserva decisiones y asignaciones. La captura evaluativa no se implementa en este módulo. Integrar la adecuación existente y acordar la procedencia de sus niveles es una dependencia de habilitación, no una suposición.

1. Revisar e integrar la funcionalidad `020-adecuacion-puesto-futuro` disponible en `origin/develop`, conservando los cambios locales de 005 y el trabajo separado de offboarding. Esta revisión documental no realiza fusiones. Comprobar el contrato real y resolver con Desarrollo la contradicción histórica de quién produce los niveles individuales.
2. Extender el contrato público de Desarrollo para suministrar identidad, criterios/versiones, método, vigencia, cobertura y evidencia por dimensión. El resumen `ultimaAdecuacion` y una lista de brechas no bastan. Reutilizar escalas estables del diccionario unificado; no asumir que todas las etiquetas son A–D. Una capacidad desactivada no constituye no aplicabilidad.
3. Evolucionar el puerto y verificador de Personal y añadir un adaptador real a la fachada pública de Desarrollo. Registrar NoDisponible/NoVerificable cuando falten datos. Las pruebas con dobles verifican el consumidor, pero no habilitan el adaptador de producción.
4. Acordar con Estructura un contrato de ocupación coherente con las asignaciones, la identidad de colaborador y su cuenta opcional. Actualizar el diseño técnico de la operación atómica y sus restricciones de concurrencia antes de programar; nunca escribir tablas internas ajenas ni presentar conciliaciones manuales como solución. Verificar compatibilidad con ingresos, posiciones y offboarding.
5. Conservar la evidencia de aprobación y registrar las revalidaciones anteriores a aplicar como eventos adicionales, sin sobrescribir aprobación o diagnósticos. Agregar el caso de uso y ruta de revalidación definidos en el contrato. Verificar criterio/perfil y vigencia al aplicar; si cambiaron, registrar incidencia y exigir la revalidación administrativa correspondiente.
6. Antes de editar la interfaz, ampliar `design/DESIGN.md` y la referencia de detalle de movimientos: dimensiones, pendientes, fuente/fecha, acceso al diagnóstico, evidencia de aprobación frente a actual y confirmación de revalidación. Mantener las dos proyecciones de permisos. No crear nuevas pantallas de evaluación en Personal.
7. Ampliar los contratos de consulta para 005 con referencias autorizadas de evidencia y movimiento; Desarrollo mantiene comparabilidad y evolución. No incorporar diagnósticos internos en la proyección comunicable de Mis solicitudes.

### Modelo, compatibilidad y migraciones

Reutilizar los campos de evidencia de movimientos para la instantánea de aprobación y el detalle append-only de transiciones para revalidaciones. No crear tablas de evaluaciones o niveles en Personal. Si las restricciones o proyecciones de ocupación requieren migraciones, deben ser aditivas o incluir una conversión documentada y probada sobre copia de datos, conservando identificadores, decisiones y hechos. No usar migrate:fresh para actualizar una base existente.

Las aprobaciones históricas que carezcan de evidencia suficiente conservan su historia y no se presentan como verificadas bajo el contrato nuevo. Los movimientos ya Aplicada no se revierten ni se reevaluan retroactivamente por esta entrega. Un movimiento aprobado pendiente debe superar la nueva comprobación o quedar con incidencia hasta revalidación. No modificar la política histórica de una actuación.

### Puertas de calidad y habilitación

Pruebas de contrato contra proveedores reales integrados; Pest de suficiencia, permisos, atomicidad, concurrencia, idempotencia y revalidación; regresiones de novedades/políticas/ingresos; Vitest de estados, dimensiones, decisión e historial; Playwright con propuesta, aprobación, fecha efectiva y ocupación coincidente en ficha y Estructura. Incluir PI-01–PI-12 y recuperación de fallas. Registrar capacidades pendientes y fallos preexistentes separadamente. No considerar completa la promoción mientras solo funcione con proveedores de prueba.

Se mantiene el alcance de las novedades originales. La separación entre hecho de ausencia y justificación requiere una revisión conjunta futura de 016 y 020; las reglas existentes no se cambian incidentalmente en esta entrega.
