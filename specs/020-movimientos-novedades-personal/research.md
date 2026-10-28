# Investigación: Registro de movimientos y novedades de personal

**Revisión 2026-10-28**: los apartados originales describen la entrega inicial. La reformulación de promociones internas al final y su contrato complementario están pendientes de implementación.

**Feature**: 020-movimientos-novedades-personal · **Fecha**: 2026-10-21

Cada decisión se tomó inspeccionando el repositorio real (no el C4 histórico). Ninguna decisión agrega dependencias: todo usa Laravel 13, PHP 8.3+, Pest, SQLite, React 19, TanStack Query, RHF + Zod y Playwright ya instalados.

## Hallazgos del repositorio que condicionan el diseño

| Hallazgo | Evidencia | Consecuencia |
|---|---|---|
| El motor real es **SQLite** (no PostgreSQL por esquema). El aislamiento es por `tenant_id` en cada fila, con índices parciales y triggers escritos en SQL crudo. | `2026_10_16_000002/3/4_*` | Se sigue la misma técnica. La intención «PostgreSQL por esquema» queda como migración futura compatible: no se usa nada exclusivo de SQLite salvo índices parciales y `RAISE` en triggers, protegidos con `DB::getDriverName()` como hace la feature 014. |
| GP-02 ya tiene `vinculos_laborales` y `asignaciones_laborales` con índices únicos parciales (una asignación abierta por vínculo y por posición) y triggers `ASIGNACION_SUPERPUESTA`, `POSICION_SUPERPUESTA`, `VINCULO_SUPERPUESTO`. | `2026_10_16_000003/4` | Cerrar la asignación anterior y abrir la nueva en una transacción es seguro por construcción: cualquier carrera termina en una excepción de unicidad que se traduce a incidencia. |
| `VinculoLaboralRepositoryInterface::ejecutarBajoBloqueo` toma el bloqueo de escritura con un `UPDATE` sin cambio de valor sobre la fila de la ficha. | `EloquentVinculoLaboralRepository` | Es el mecanismo de serialización por ficha. Se reutiliza para presentar, resolver, retirar y aplicar. |
| `PoliticasFacade::seleccionarParaPresentar` exige transacción abierta y bloquea la cabecera del tipo (`bloquear`). | `PoliticasService`, `ResolvedorDePoliticaAplicable` | La presentación toma primero el bloqueo de la ficha y luego el de la cabecera del tipo, en una sola transacción: mismo mecanismo de coordinación que la publicación de políticas, sin lectura vulnerable previa. Se confirma que la publicación solo bloquea la cabecera, así que el orden ficha → cabecera no puede producir un ciclo. |
| El puerto `PosicionesAsignablesPort::verificar` ya expone `asignable` y `motivo`, y `EstructuraFacade` no expone niveles ni evaluaciones. | `PosicionesAsignablesEstructura`, `EstructuraFacade` | La condición estructural del destino sale de ese puerto. **No existe contrato de niveles ni de evaluación**: se modela el puerto de evaluación sin adaptador real (decisión D-03). |
| El idioma de operaciones idempotentes es `Idempotency-Key` UUID + hash sha256 del contenido + actor; reutilizar la clave con otro contenido responde 409. | `RegistrarIngreso`, `IngresoController` | Se replica el patrón para presentación y resoluciones. |
| Tenant y zona horaria vienen de `ContextoDeTenantPort::deCuenta(userId)`; solo se resuelve desde una cuenta. | `SharedContextoDeTenant` | El procesador programado no tiene cuenta: se agrega `deTenant(int)` al puerto (extensión compatible). |
| El colaborador se vincula a su ficha con `colaborador_cuentas` mediante `AccesosDeCuentaPort`; el rol operativo se distingue por la capacidad Sanctum (`admin` / `user`). | `EloquentAccesosDeCuenta`, `EnsureAdmin` | Las rutas del Colaborador exigen solo `auth:sanctum` y el caso de uso exige la asociación en el tenant; las del Administrador usan `['auth:sanctum','admin']`. |
| La carga de documentos (`documentos_colaborador`) es **solo del Administrador**; el Colaborador solo lista los suyos visibles. | rutas `colaboradores/{id}/documentos` y `mi-ficha/documentos` | El respaldo de una solicitud es una **referencia** a un documento ya cargado y accesible para quien presenta (Administrador: cualquier documento disponible de la ficha; Colaborador: solo los de su ficha visibles para él y con permiso documental). No se crea un segundo almacén ni se copian adjuntos. |
| Existe un precedente de proceso programado (`rutas:conciliar-bajas`, `documentos:limpiar-huerfanos`) y una convención de scheduler en `routes/console.php`. | `routes/console.php` | La aplicación automática es un comando de consola idempotente programado cada 5 minutos con `withoutOverlapping`. |

## Decisiones

### D-01 — Una tabla común de actuaciones con dos tablas de detalle

- **Decisión**: `actuaciones_personal` (estado actual y campos comunes), `movimientos_personal` y `novedades_personal` (1:1 por tipo), `actuacion_documentos` (referencias) y `actuacion_transiciones` (append-only).
- **Razón**: separa los tres tipos de dato exigidos (estado actual, antecedentes administrativos, hechos laborales derivados) y evita campos nulos por tipo. El hecho laboral derivado vive donde ya viven los hechos laborales: `asignaciones_laborales`, a la que se agrega la columna nullable `actuacion_id` para trazabilidad.
- **Alternativas**: una tabla única con columnas nulas (rechazada: mezcla invariantes); event sourcing (rechazado por la spec).

### D-02 — Transiciones append-only con la clave de operación como llave de idempotencia

- **Decisión**: cada cambio de estado inserta una fila en `actuacion_transiciones` (trigger que impide `UPDATE` y `DELETE`). La clave de operación de resolución se guarda ahí con su hash y actor, con índice único `(tenant_id, operacion_id)`.
- **Razón**: una sola estructura sirve de auditoría de negocio, de evidencia anterior (FR-042) y de reconocimiento de reintentos. La presentación guarda su clave en la propia fila común (como GP-02).
- **Alternativa**: tabla de claves separada (rechazada: duplica el registro).

### D-03 — Contrato de evaluación: puerto sin adaptador real

- **Decisión**: `EvaluacionDelDestinoPort::consultar(tenantId, colaboradorId, posicionDestinoId, instante)` devuelve `Valida | NoDisponible | NoVerificable` con un contexto verificable. El único adaptador registrado es `EvaluacionDelDestinoNoInstalada` (devuelve `NoDisponible`). `VerificadorDeEvaluacion` (dominio) valida que el contexto identifique al colaborador, al puesto destino, los requisitos considerados, el momento y el resultado, y que no esté vencido; si algo falta es `NoVerificable`. No existe umbral ni porcentaje.
- **Consecuencia**: la aprobación de ascensos y traslados queda **impedida de punta a punta** hasta que otro módulo registre su adaptador (FR-022b). Las pruebas la habilitan sustituyendo el puerto en el contenedor.
- **Alternativas**: afirmación manual «apto» (prohibida por FR-022); calcular una evaluación local con las competencias de Estructura (invento de regla de negocio, prohibido).

### D-04 — Ascenso y traslado se distinguen por el tipo elegido

- **Decisión**: el Administrador elige `Ascenso` o `Traslado` y se conserva así. No se verifica automáticamente «nivel superior».
- **Razón**: Estructura no expone un contrato de niveles de puesto; inferirlo sería inventar una regla. **Vacío registrado (G-01)**: cuando exista el contrato de niveles, una especificación posterior podrá validar la coherencia del tipo.

### D-05 — Fecha efectiva futura: estados `Aprobada` y `Programada`

- **Decisión**: aprobar un movimiento con fecha efectiva futura (en la zona horaria del tenant) lo deja `Programada`; con fecha efectiva igual o anterior a hoy lo deja `Aprobada` (pendiente de aplicar). Aprobar nunca modifica asignaciones. La aplicación es una operación independiente: la ejecuta el comando programado (identidad de sistema) o el Administrador con «Aplicar ahora» (que exige fecha efectiva alcanzada).
- **Razón**: respeta «presentar o aprobar no modifica la asignación» y «aplicar es independiente».

### D-06 — Procesador de aplicación automática

- **Decisión**: `movimientos:aplicar-programados` recorre los tenants con movimientos `Aprobada`/`Programada` cuya fecha efectiva ya llegó en la zona del tenant y aplica cada uno con el mismo caso de uso `AplicarMovimiento` que usa el Administrador. Las incidencias no se reintentan solas (decisión de la clarificación): quedan `IncidenciaDeAplicacion` hasta el reintento manual. Idempotencia: índice único parcial `asignaciones_laborales(actuacion_id) WHERE actuacion_id IS NOT NULL` y verificación de estado bajo bloqueo.
- **Aislamiento**: cada movimiento se procesa con el contexto de su propio tenant (`deTenant`), las lecturas llevan `tenant_id` y nada se comparte entre iteraciones.

### D-07 — Aplicación atómica

`AplicarMovimiento` ejecuta bajo `ejecutarBajoBloqueo(ficha)`: (1) relee la actuación y su estado; (2) verifica que el vínculo siga vigente; (3) que la asignación de origen siga siendo la única abierta del vínculo y haya empezado antes de la fecha efectiva; (4) que el destino siga siendo asignable (puerto) y esté libre desde la fecha efectiva (`ReglasDeIngreso::asignacionEnConflicto`); (5) cierra la asignación de origen con `fecha_fin = fecha_efectiva`, inserta la nueva con `fecha_inicio = fecha_efectiva` y `actuacion_id`; (6) marca `Aplicada` y registra la transición. Cualquier falla de (2) a (5) revierte todo y, **fuera de esa transacción**, registra la incidencia en una transacción corta aparte, de modo que la incidencia persista aunque la aplicación se revierta.

### D-08 — Reconocimiento de cruce de períodos

`DetectorDeCruces` (dominio, puro) recibe las novedades Permiso/Licencia/Vacaciones del colaborador en estado Presentada, En revisión o Aprobada y devuelve las que comparten al menos un día con el período (fechas inclusivas). El detalle de la solicitud las lista; aprobar con cruces exige `reconoce_cruce = true` (422 `CRUCE_SIN_RECONOCER`); el reconocimiento queda en la transición con actor y momento. Las ausencias informadas no se incluyen: son un hecho, no una autorización.

### D-09 — Períodos de novedades inclusivos; intervalos laborales semiabiertos

Las novedades guardan `fecha_inicio` y `fecha_fin` como primer y último día solicitado (inclusivos), porque así los expresa quien las presenta. Los intervalos laborales de GP-02 siguen siendo `[inicio, fin)`; al aplicar un movimiento, `fecha_fin` de la asignación anterior es la fecha efectiva (excluida) y `fecha_inicio` de la nueva es la fecha efectiva (incluida).

### D-10 — Ausencias

Se consume la política `Ausencias` (disponibilidad, justificación y documento) pero **no** su anticipación ni se inventa retroactividad. Única restricción temporal: es un hecho ocurrido, así que ninguna fecha puede ser posterior a hoy (zona del tenant); `fecha_inicio ≤ fecha_fin`. Quedan en `Informada`; el Administrador las pasa a `Revisada` o `Justificada` con observación. Nunca se convierten en permiso aprobado.

### D-11 — Visibilidad del Colaborador

Sus novedades: todos los estados, con motivo del rechazo (resultado comunicable). Sus movimientos: solo `Aprobada`, `Programada`, `Aplicada`, y únicamente tipo, resultado y fecha efectiva. Una ausencia informada propia se muestra solo cuando el Administrador la revisó o justificó («autorizadas para comunicación»). El recurso del Colaborador es un `Resource` distinto que no contiene los campos restringidos (no se filtra en la interfaz).

### D-12 — Tipos de política y claves de acceso

El tipo de actuación mapea 1:1 a `TipoDePolitica` (Ascenso→Ascensos, Traslado→Traslados, Permiso→Permisos, Licencia→Licencias, Vacaciones→Vacaciones, Ausencia→Ausencias). Las políticas no conceden permisos: la autorización se decide por la capacidad Sanctum y la asociación cuenta–ficha.

### D-13 — Retiro por el solicitante

Solo novedades (Permiso, Licencia, Vacaciones) en `Presentada` o `En revisión`. Lo retira quien la presentó (el Colaborador, o el Administrador que la registró) y también el propio Colaborador titular cuando la registró un Administrador en su nombre. Estado terminal `Retirada`; compite con la resolución bajo el mismo bloqueo de ficha y versión optimista: solo uno prospera, el otro recibe 409.

### D-14 — Historial Laboral

Los hechos aplicados se publican ampliando `TipoDeHecho` con `Ascenso` y `Traslado` y leyéndolos desde `asignaciones_laborales` con `actuacion_id`. Solo los movimientos aplicados producen hecho laboral; las novedades no (no tienen efecto sobre la trayectoria de posiciones). Rechazos, evaluaciones y resoluciones quedan como antecedentes en las tablas de la actuación.

### D-15 — Frontend

Un módulo `frontend/src/modules/gestionPersonal/movimientos/` con rutas `/movimientos-personal` (Administrador) y `/mis-solicitudes` (Colaborador). Reutiliza `shared/ui`, el patrón de listado con filtros en la URL de `colaboradores`, `useClaveDeOperacion` y `CondicionesAplicables` de la feature 016 (importados solo por su `index.ts` público). Nada específico para Líder ni Gerencia.

## Compuertas de implementación (no son requisitos nuevos)

| # | Compuerta | Estado | Efecto mientras esté abierta |
|---|---|---|---|
| P-1 | Quién entrega la evaluación del puesto destino y su contrato | **Abierta** | Aprobar/aplicar ascensos y traslados impedido; presentar, consultar, revisar y rechazar funcionan. |
| P-2 | Reglas temporales y de superposición por novedad | **Abierta** | Cruce señalado y reconocido, sin bloqueos; ausencias solo no futuras. |
| P-3 | Saldos de vacaciones | **Abierta** | No se calcula ni se muestra saldo; sin «saldo validado». |
| P-4 | Método para corregir movimientos efectivos | **Abierta** | No existen endpoints de edición, eliminación ni reversión de lo aprobado o aplicado. |

## Ensayos de uso previos a los componentes (compuerta del insumo)

| Ensayo | Resultado | Vacío detectado → decisión |
|---|---|---|
| 1. Administrador presenta y resuelve un ascenso | Presenta con destino y justificación; en detalle ve evaluación «no disponible» y no hay control de aprobar; puede rechazar. | La pantalla necesita una explicación textual de la causa del bloqueo → componente `ResumenDeComprobaciones` con tres estados separados. |
| 2. Colaborador presenta un permiso y consulta la decisión | Ve condiciones antes de confirmar (reutilizando `CondicionesAplicables`), presenta, consulta el estado y ve el motivo del rechazo. | El Colaborador no puede subir documentos (carga solo Admin): solo puede referenciar sus documentos visibles → gap **G-02** en el formulario: si la política exige documento y no tiene ninguno visible, la presentación queda bloqueada con mensaje claro, no se oculta. |
| 3. Administrador informa una ausencia ocurrida | Formulario con aviso «informa un hecho ocurrido»; sin anticipación; queda Informada. | Fechas futuras: se rechazan como «aún no ocurrida» (D-10). |
| 4. Movimiento aprobado futuro llega a su fecha y la posición está ocupada | El comando registra incidencia sin cambios; el detalle del Administrador muestra la condición y «Reintentar»; el Colaborador sigue viendo «Programada»… | **Vacío G-03**: el Colaborador vería «Programada» aunque haya incidencia. Decisión: para el Colaborador `IncidenciaDeAplicacion` se muestra como «Aprobada, pendiente de aplicar» (nunca «Aplicada», sin detalle de la incidencia), coherente con FR-037. |

Los vacíos G-01 a G-03 se registran en este documento y no se convierten en supuestos nuevos fuera de lo aquí decidido; la spec los cubre sin cambios (G-03 es una precisión de FR-037 y se anota en `spec.md`).

## Decisiones de la reformulación · 2026-10-28

- **PI-D01 · Propiedad**: el diagnóstico del destino pertenece a Desarrollo y Formación, cuya adecuación existe en origin/develop. Se corrige la titularidad abierta de D-03 para el diagnóstico; la captura de niveles sigue siendo un acuerdo pendiente con Desarrollo porque sus puertos antiguos la esperan de Personal y 012 la excluye.
- **PI-D02 · Suficiencia**: una lista no vacía de requisitos y un resultado de texto solo validan forma. Se exige evidencia identificable por dimensión, perfil y método, incluyendo cobertura y pendientes. NoAplicable procede del perfil propietario; no de la ausencia de un proveedor. La preparación del diagnóstico no decide la aprobación.
- **PI-D03 · Historia y vigencia**: la evidencia original de aprobación queda congelada. Una revalidación antes de aplicar añade evidencia y justificación sin sustituir esa decisión. Cambios de perfil o vencimiento necesario bloquean ejecución hasta una revalidación válida. El contrato define una operación adicional, sin estados nuevos de aprobación ni corrección de movimientos ya aplicados.
- **PI-D04 · Ocupación**: las asignaciones efectivas son la fuente de verdad; Estructura publica ocupación coherente por un contrato explícito. La implementación actual de cerrar/abrir asignaciones no basta para garantizar estado y ocupante de posiciones. La operación debe ser atómica; sincronización eventual o reparación manual no acreditan esta revisión.
- **PI-D05 · Identidad y escalas**: el ocupante es una identidad laboral, con cuenta opcional. Las etiquetas configurables del diccionario no se convierten arbitrariamente a letras; el proveedor conserva significado y versión. Antigüedad o formación declarada no se transforman automáticamente en competencias observadas.
- **PI-D06 · Alcance**: se reutilizan la adecuación existente y el seguimiento 005. No se crean otro motor, rankings, convocatorias, puestos ni instrumentos en Personal. La clasificación administrativa Ascenso/Traslado se conserva con su limitación actual. La revisión de ausencias se mantiene como trabajo coordinado independiente con 016.
