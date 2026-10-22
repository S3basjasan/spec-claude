# Research: Configuración de políticas operativas de personal

**Fecha**: 2026-10-20 | **Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md)

No quedan `NEEDS CLARIFICATION`. Esta investigación contrasta el insumo técnico con el repositorio real y fija las decisiones de diseño.

## Divergencias con el insumo

| El insumo dice | Realidad del repositorio | Decisión |
|---|---|---|
| PostgreSQL con esquema independiente por tenant | SQLite con `tenant_id` en cada tabla; hoy hay un solo tenant | SQLite + `tenant_id` (constitución I). Se diseña para que pasar a otro motor solo cambie los *triggers* de inmutabilidad y el mecanismo de bloqueo (D1, D3). |
| «Mantener una política por tipo dentro de cada tenant» | No existe tabla de políticas | `politicas_personal`, única por `(tenant_id, tipo)`, creada de forma idempotente al primer uso de escritura y por el seeder (D1). |
| Rutas y tablas «propuestas» | Rutas actuales: `/api/colaboradores`, `/api/mi-ficha`, `/api/categorias-documentales` | `/api/politicas-personal` por precedente de 013 (registrado en el plan). |
| Permisos por capacidad | Solo `admin` (token) y permisos de `colaborador_cuentas` | Administración con `auth:sanctum` + `admin`; el Colaborador solo lee condiciones aplicables. |
| Servicios de administración y de consulta separados | Patrón de casos de uso + fachada ya presente (`PersonalFacade`) | Casos de uso para administración; `PoliticasFacade` para consulta de otros módulos. |
| Tarea programada para estados | Existe el *scheduler* (limpieza de huérfanos en 014) | **No se usa para estados**: el estado se deriva de instantes (D2). |
| Solicitudes que referencian versiones | No existen aún las tablas de novedades, movimientos ni offboarding | Se define el contrato que deben cumplir (`contracts/consumo-de-politicas.md`); esta feature no crea tablas de solicitudes. |
| Selección de política y asociación «en la misma unidad de trabajo» | Mismo motor para todos los módulos | La fachada exige llamarse dentro de la transacción del consumidor y toma el bloqueo del tipo (D3). |

## D1. Cabecera por tipo como punto de serialización

- **Decisión**: tabla `politicas_personal` (una fila por `(tenant_id, tipo)`) con `ultimo_numero`, `version` y marcas de tiempo. Es el punto de bloqueo de publicar, retirar, crear borrador y seleccionar la política al presentar una solicitud, y el contador de numeración.
- **Rationale**: sin una fila estable no hay qué bloquear cuando el tipo aún no tiene versiones, y el contador monótono evita reutilizar números aunque se retire una versión. El patrón ya existe en 013 (`confirmarActiva`: un `UPDATE` que no cambia valores toma el bloqueo hasta el commit).
- **Creación**: las siete filas se aseguran con inserción idempotente (`INSERT … ON CONFLICT DO NOTHING` equivalente) en la primera escritura y en el seeder. Las lecturas toleran su ausencia y tratan el tipo como «sin configurar».
- **Alternativas**: bloqueo advisory por `(tenant, tipo)` (dependiente del motor); bloquear la última versión (no existe cuando es la primera); `MAX(numero)+1` sin cabecera (condición de carrera y reutilización tras borrados lógicos).

## D2. Estado derivado de instantes, sin proceso auxiliar

- **Decisión**: el estado se calcula al leer, con el reloj del servidor y estas reglas sobre columnas inmutables o de solo escritura única (`publicada_en`, `inicio_vigencia`, `retirada_en`, `descartada_en`): ver data-model.md §Estados. `Vigente` es la publicada no retirada con mayor `inicio_vigencia` ≤ ahora (desempate por número); las demás publicadas no retiradas con inicio ≤ ahora son `Sustituida`; las de inicio > ahora son `Programada`.
- **Rationale**: el insumo lo pide expresamente (la consulta debe funcionar aunque un proceso auxiliar se retrase). Una versión programada empieza a regir exactamente en su instante, y la sustitución es consecuencia, no una escritura.
- **Consecuencia**: la reconsulta dentro de la transacción de presentación evalúa el estado con el mismo instante que se registra en la solicitud (D3).
- **Alternativas**: columna `estado` actualizada por un comando programado (puede quedar desfasada); actualizar el estado al leer (escrituras en lecturas).

## D3. Selección de la política al presentar una solicitud

- **Decisión**: `PoliticasFacade::seleccionarParaPresentar(tenantId, tipo, instante)` debe llamarse dentro de la transacción del consumidor. Toma el bloqueo de la cabecera (D1), calcula la versión aplicable con `instante` (el instante de confirmación de la presentación, FR-024d) y devuelve `PoliticaAplicableDTO` (versión, número, parámetros, condiciones selladas, hash). El consumidor guarda `politica_version_id` y la copia de las condiciones en la misma transacción.
- **Rationale**: una publicación concurrente no puede intercalarse entre la selección y la asociación: la que confirme primero fija el orden; si la presentación confirma antes, usa la anterior; si después, la nueva. `aplicablePara` (sin bloqueo) existe para mostrar condiciones antes de presentar; no reserva versión (FR-029).
- **Resultados**: `NoConfigurada` (sin versión vigente), `NoAdmiteSolicitudes` (`disponible=false`; no aplica a Offboarding, que no tiene ese parámetro) y `NoDisponible` (fallo de lectura). Ninguno devuelve valores supuestos; ninguno confirma la presentación.
- **Alternativas**: que el consumidor consulte las tablas (prohibido por la constitución); versión «reservada» al abrir el formulario (contradice FR-029).

## D4. Publicación atómica e idempotente

- **Decisión**: transacción: (1) validar la entrada; (2) tomar el bloqueo del tipo; (3) buscar `operacion_id` en `politica_acciones` — mismo hash devuelve el resultado original (200), otro hash es 409 `CLAVE_DE_OPERACION_REUTILIZADA`; (4) releer el borrador y comprobar `version` (409 `BORRADOR_MODIFICADO`) y que no esté descartado ni ya publicado; (5) rechazar si existe una programada (409 `PROGRAMADA_EXISTENTE`); (6) resolver el inicio en la zona del tenant (rechazar pasado, 422 `INICIO_EN_EL_PASADO`); (7) validar de nuevo parámetros, reglas fijas, motivo y revisión; (8) asignar `numero`, sellar `condiciones` y `hash`; (9) insertar la acción de auditoría. Cualquier fallo revierte todo.
- **Rationale**: es el patrón de 006 y 014 (`Idempotency-Key` UUID, hash del contenido, repetición válida = 200). La revalidación bajo bloqueo cubre cambios entre la previsualización y la confirmación.
- **Publicar con inicio inmediato mientras hay una programada**: se rechaza igual. Con una programada posterior, una versión publicada ahora quedaría sustituida enseguida y sin aviso; exigir el retiro previo (FR-020) lo evita. Decisión del plan; la spec la recoge en FR-020.

## D5. Inmutabilidad

- **Decisión**: *triggers* de SQLite (como en 013 y 014) que (a) impiden `UPDATE` de columnas selladas cuando `publicada_en` no es nulo (todas salvo `retirada_en`, `retirada_por`, `retiro_motivo`, `updated_at` y `updated_by`), (b) impiden `DELETE` de versiones publicadas y de cualquier fila de `politica_acciones`, y (c) impiden `UPDATE` en `politica_acciones`. Los borradores solo cambian mientras no estén publicados ni descartados. El dominio rechaza antes (409) y los *triggers* son la segunda barrera.
- **Rationale**: SC-005 pide que ninguna versión publicada pueda cambiarse; una barrera solo en código no sobrevive a un error futuro. Equivalente en PostgreSQL: *triggers* `BEFORE UPDATE/DELETE` con la misma lista de columnas.
- **Alternativas**: solo validación en aplicación (insuficiente); tabla de versiones separada «publicadas» (duplica el modelo).

## D6. Borrador único por tipo

- **Decisión**: índice único parcial `(politica_id) WHERE publicada_en IS NULL AND descartada_en IS NULL`. Crear otro borrador devuelve 409 `BORRADOR_EXISTENTE` con el id del existente. Descartar marca `descartada_en/por` (el borrador no se elimina: queda el rastro) y registra la acción.
- **Rationale**: clarificación 1 de la spec; el índice lo garantiza aunque dos administradores creen a la vez. El borrador es compartido por los administradores del tenant; la concurrencia se controla con `version`.
- **Alternativas**: borrar el borrador al descartar (pierde auditoría); un borrador por autor (descartado en la clarificación).

## D7. Catálogo de tipos, parámetros y reglas fijas

- **Decisión**: `CatalogoDePoliticas` (PHP puro) declara para cada tipo: categoría (`Novedad`, `Movimiento`, `Offboarding`), parámetros admitidos con clave, tipo, rango y texto de ayuda, y las reglas fijas visibles (por ejemplo, para Ascensos y Traslados «La evaluación del colaborador contra el puesto destino es obligatoria» y «La justificación del movimiento es obligatoria»). `ReglasDePolitica` valida: rechaza claves desconocidas (422 con la clave), valores inválidos (anticipación entera ≥ 0), tareas sin nombre, duplicadas (comparación sin tildes ni mayúsculas) o más de 30, textos fuera de los máximos (FR-024e). Las reglas fijas no son parámetros: un cuerpo que intente enviarlas (`evaluacion_obligatoria`, `justificacion_movimiento`) se rechaza como contradicción con la regla fija con el mensaje de la regla.
- **Rationale**: SC-002 y FR-014; el servidor es la autoridad y la interfaz pinta la `definicion`. Las reglas fijas se sellan en `condiciones` para que cada versión explique las condiciones con las que se aplicó.
- **Parámetros por tipo**: ver [contracts/catalogo-de-parametros.md](contracts/catalogo-de-parametros.md).
- **Alternativas**: JSON Schema genérico (más flexible de lo que la spec permite); tabla de parámetros configurable (contradice «sin tipos arbitrarios»).

## D8. Inicio de vigencia y zona horaria

- **Decisión**: el cliente envía `inicio` como `{modo: "inmediato"}` o `{modo: "programado", fecha_hora_local: "AAAA-MM-DDTHH:mm"}`. El servidor interpreta la hora local en la zona del tenant (`ContextoDeTenant::zonaHoraria`), con la regla de la clarificación 2: hueco → primer instante válido posterior; repetición → primera ocurrencia. Persiste el instante UTC y devuelve ambos (`inicio_vigencia` y `inicio_vigencia_local`). «Inmediato» usa el reloj del servidor dentro de la transacción. Se rechaza un inicio ≤ ahora en modo programado.
- **Rationale**: evita ambigüedades de zona entre navegador y organización (spec, caso límite) y permite mostrar en la confirmación el momento exacto resultante.
- **Alternativas**: aceptar un instante UTC (la interfaz tendría que resolver la zona y las reglas del cambio de hora).

## D9. Fechas y reloj

- **Decisión**: `RelojPort` (`ahora(): DateTimeImmutable`) con `RelojDelSistema` sobre `Carbon::now()`. Las pruebas fijan el reloj con `Carbon::setTestNow`. Todas las comparaciones de estado usan un único `ahora` leído una vez por operación y dentro de la transacción.
- **Rationale**: dominio y aplicación no dependen de Laravel; determinismo en pruebas de vigencia y retiro.

## D10. Auditoría de administración

- **Decisión**: `politica_acciones` de solo inserción: `accion` (`BorradorCreado`, `Publicacion`, `Retiro`, `BorradorDescartado`), actor, momento, motivo (cuando aplica), `version_id`, `operacion_id` y `operacion_hash` (solo en publicación y retiro). Las ediciones de un borrador solo mantienen `updated_by` y `updated_at` en la fila (spec: «quién creó y modificó un borrador y cuándo»); no se registra el contenido en el registro técnico.
- **Rationale**: FR-040/041; sin datos personales ni contenido de solicitudes en registros técnicos. La configuración no genera eventos en el historial laboral.

## D11. Lectura para no administradores

- **Decisión**: `GET /api/politicas-personal/{tipo}/aplicable` (solo `auth:sanctum`) devuelve la situación (`Vigente`, `SinConfigurar` o `NoAdmiteSolicitudes`) y, si hay vigente, número, inicio y parámetros con las reglas fijas; nunca descripción interna, motivo, revisión, responsables, borradores ni programadas. Si falla la lectura: 503 `POLITICA_NO_DISPONIBLE`.
- **Rationale**: FR-035/036 y el insumo («una falla no se presenta como ausencia de requisitos»). Después de presentar, el detalle de la solicitud usa su propia copia conservada (contrato de consumo), no este endpoint.

## D12. Interruptor operativo

- **Decisión**: `hrms.gestion_personal.politicas_habilitado` (por defecto `true`), como `documentos.habilitado`: apagado, las escrituras responden 503 `ESCRITURA_DESHABILITADA` y las lecturas siguen disponibles.
- **Rationale**: consistencia con 013/014 y reversión segura.

## D13. Estrategia de pruebas

- Dominio (unitarias): catálogo y validación (campos desconocidos, rangos, tareas duplicadas con tildes), clasificador de versiones con tabla de casos de instantes (programada → vigente → sustituida, retiro, empates), resolución de inicio con huecos y repeticiones de hora (zona de prueba con cambio de hora), diferencias entre versiones.
- Feature: ciclo completo (borrador → publicación inmediata → segunda versión programada → inicio → sustitución), inmutabilidad (HTTP y *triggers* a nivel de base), un borrador por tipo, numeración monótona tras retiro, idempotencia y conflicto de clave, conflicto de edición, retiro antes y después de la vigencia, publicación rechazada por programada existente, aislamiento por tenant (404), 403 al Colaborador, interruptor, consulta aplicable en sus tres situaciones y 503, y que ninguna respuesta aplicable exponga datos internos.
- Concurrencia (entrelazado simulado, patrón de 014): dos publicaciones del mismo tipo (una gana, la otra 409), publicación frente a retiro de la programada con el inicio en el mismo instante, y selección para presentar frente a publicación (la que confirma primero fija la versión).
- Contrato de consumo con un consumidor de prueba: copia de condiciones intacta tras publicar otra versión, plantilla de offboarding copiada y estable.
- Frontend: Vitest (formulario por tipo desde la `definicion`, editor de tareas, diálogo de publicación con comparación y errores por campo, conflicto que conserva el contenido, `CondicionesAplicables` en sus cuatro situaciones); E2E (publicar una política, programar y retirar, comparar).
- La validez de las reglas para la organización se revisa además con el cliente con casos de negocio; las pruebas solo demuestran el comportamiento del sistema.

## Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| Dos publicaciones simultáneas producen números duplicados o dos vigentes | Bloqueo de cabecera + contador bajo bloqueo + índice único `(politica_id, numero)`; la derivación de vigente es determinista aun con inicios iguales (desempate por número). |
| Una presentación usa una versión recién sustituida | La selección toma el mismo bloqueo y usa el instante de confirmación; la que confirma primero fija el orden (D3). |
| Un consumidor reinterpreta una solicitud antigua con la política nueva | El contrato exige guardar `politica_version_id` y la copia de las condiciones y prohíbe reseleccionar (consumo-de-politicas.md). |
| Un proceso auxiliar atrasado deja estados incorrectos | No existe proceso auxiliar de estados (D2). |
| SQLite no permite `CHECK` expresivos | *Triggers*, como en 013/014 (D5). |
| Texto libre con instrucciones | Se guarda y muestra como texto; el catálogo no interpreta contenido (FR-014). |
| Estructura caída | No intervienen en las políticas (FR-015); los procesos de movimientos resuelven destinos y niveles. |
