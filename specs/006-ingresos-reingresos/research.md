# Research: Registro de ingresos y reingresos laborales (GP-02)

Sin `NEEDS CLARIFICATION` abiertos. Decisiones a partir del insumo «Plan GP-02», la spec con sus cuatro
clarificaciones, la constitución v1.0.0 y el estado real del repositorio (000–005 implementados). Donde el insumo y la
realidad difieren, se indica cuál prevalece y por qué.

## D-01 Ubicación, módulo y rutas

- **Decision**: Backend en `backend/app/Modules/GestionPersonal/` (mismo módulo que GP-01); frontend en
  `frontend/src/modules/gestionPersonal/fichas/`. Rutas `/api/colaboradores/{id}/ingresos`,
  `/api/colaboradores/{id}/opciones-ingreso`, `/api/colaboradores/{id}/situacion-laboral`,
  `/api/colaboradores/{id}/trayectoria`, `/api/mi-ficha/situacion-laboral` y `/api/mi-ficha/trayectoria`.
- **Rationale**: Lo fija el insumo; vínculos y asignaciones son parte de Personal y GP-01 ya dejó los puertos que GP-02
  implementa. Las rutas fuera de `/api/<modulo>/` tienen precedente (000–005).
- **Alternatives considered**: un módulo `Laboral` aparte (duplica contexto de tenant, ficha y permisos).

## D-02 Base de datos: SQLite en lugar de PostgreSQL con GiST (clarificación 2)

- **Hallazgo**: El insumo exige PostgreSQL, `btree_gist` y restricciones `EXCLUDE USING gist`, y pide no verificar con
  SQLite. El repositorio usa SQLite (constitución I; `DB_CONNECTION=sqlite`; `phpunit.xml` en `:memory:`).
- **Decision**: Mantener SQLite. La no superposición se garantiza en tres capas:
  1. **Aplicación**: `RegistrarIngreso` comprueba, dentro de la transacción de escritura, ficha y posición contra los
     intervalos existentes con la misma fórmula del dominio.
  2. **Serialización de escritores**: SQLite admite un único escritor; la primera instrucción de la transacción es una
     escritura sobre la fila de la ficha (bloqueo), de modo que las lecturas posteriores ven el estado confirmado y el
     segundo escritor espera (o recibe `SQLITE_BUSY`, que se traduce a reintento acotado).
  3. **Respaldo en la base**: *triggers* `BEFORE INSERT/UPDATE` que abortan ante superposición de vínculos por ficha,
     superposición de asignaciones por posición o por vínculo, y asignación fuera de su vínculo; más índices únicos
     parciales (`UNIQUE … WHERE fin IS NULL`) para «un abierto por ficha» y «un abierto por posición».
- **Rationale**: Es la única opción compatible con la constitución sin migrar 000–005. Los *triggers* cumplen el papel
  de las exclusiones: protegen aunque un futuro escritor (movimientos, salidas) omita la validación de aplicación.
- **Alternatives considered**: PostgreSQL global (enmienda de la constitución + migrar todo); `transaction_mode =
  IMMEDIATE` global (cambia el bloqueo de todas las features); solo validación en aplicación (sin red de seguridad).
- **Riesgo registrado**: las garantías se demuestran sobre SQLite. Si el destino productivo fuera PostgreSQL, los
  *triggers* se reemplazan por `EXCLUDE` sin cambiar dominio ni casos de uso; el insumo conserva su SQL como referencia.

## D-03 Intervalos y fórmula de superposición

- **Decision**: Intervalo `[inicio, fin)`, fin `NULL` = abierto (infinito, no «hoy»). Dos intervalos A y B se
  superponen si `A.inicio < COALESCE(B.fin, ∞)` y `B.inicio < COALESCE(A.fin, ∞)`, y **ambos son no vacíos**
  (`inicio < COALESCE(fin, ∞)`). Un ingreso nuevo es siempre abierto `[f, ∞)`, por lo que choca con un existente
  `[s, e)` si y solo si `e` es `NULL` o `e > f` (y `[s, e)` no es vacío). Un intervalo vacío (`inicio = fin`) no ocupa.
  Fechas como texto `AAAA-MM-DD` (comparables lexicográficamente); `∞` = `9999-12-31` solo dentro de la comparación.
- **Rationale**: Cubre RN02, RN03, RN05 y RN06 con una sola regla pura (`PeriodoLaboral`) reutilizada por dominio y
  *triggers*; los contiguos (fin = inicio) no se superponen.
- **Alternatives considered**: fin inclusivo (contradice RN05); usar `NOW()` como fin de los abiertos (el estado
  dependería del reloj de la consulta, no de la fecha efectiva).

## D-04 Ocupación derivada de asignaciones y efecto en 004 y 005 (clarificación 1)

- **Hallazgo**: En 004/005 `EstadoPosicion::enUso()` es verdadero para `Ocupada` y `Vacante`, es decir, **toda
  posición no suprimida ya bloquea** la desactivación de su puesto y de su unidad, tenga o no ocupante. Por tanto, el
  riesgo original (desactivar con alguien asignado) solo se da con una posición `Suprimida` que aún tenga una
  asignación vigente o futura.
- **Decision**: GP-02 no escribe `posiciones.estado`. Se añade en `Estructura` un puerto
  `OcupacionDePosicionesPort::posicionesConAsignacionVigenteOFutura(array $posicionIds, string $fecha): array`,
  implementado por un adaptador que llama a `PersonalFacade`. `CambiarEstadoPuesto` y `CambiarEstadoUnidad` lo
  consultan **además** de sus reglas actuales y suman esas posiciones al conteo del mensaje. Una asignación terminada
  no bloquea.
- **Rationale**: Cumple FR-016a/FR-016b con un cambio mínimo y sin duplicar estados; el contrato evita que Estructura
  lea tablas de Personal.
- **Alternatives considered**: escribir `Ocupada`/`Vacante` desde GP-02 (dos fuentes de verdad; un ingreso futuro
  marcaría la posición ocupada hoy); no tocar 004/005 (deja el caso suprimida+asignada sin protección).

## D-05 Posiciones: tenant, elegibilidad y datos

- **Hallazgo**: `posiciones` solo tiene `id`, `puesto_id`, `estado`, `unidad_organizacional_id` (nullable) y marcas de
  tiempo; no tiene tenant, código, nombre ni jefatura, y no existe funcionalidad que cree posiciones (clarificación 4).
  Puestos y unidades tampoco tienen tenant (catálogos de 004/005).
- **Decision**:
  1. Migración `2026_10_16_000001`: `posiciones.tenant_id` unsignedBigInteger NOT NULL DEFAULT 1 con índice; las
     existentes pasan al tenant 1 (mismo patrón que `users.tenant_id`). Puestos y unidades siguen siendo catálogos
     compartidos; la posición pertenece a un tenant.
  2. **Elegible** = misma `tenant_id` que la ficha, `estado` ≠ `Suprimida`, puesto `Activo`, `unidad_organizacional_id`
     no nula y unidad `Activa`, y sin asignación que choque con `[fecha, ∞)` (D-03). La posición `Ocupada` o `Vacante`
     guardada no cambia la elegibilidad: la ocupación sale solo de las asignaciones.
  3. Jefatura: no existe en Estructura; el contrato la devuelve `null` y la interfaz muestra «No registrado».
  4. Datos: `IngresosSeeder` asigna unidad a posiciones sembradas y crea fichas, vínculos y asignaciones de prueba.
- **Rationale**: RN04 exige pertenencia al tenant; sin `tenant_id` la ocupación se compartiría entre organizaciones.
- **Alternatives considered**: posiciones globales (fuga de ocupación entre tenants); crear una tabla propia de
  posiciones en Personal (duplica el catálogo, contradice RC-11).
- **Pregunta abierta para confirmar antes de implementar**: si 004/005 deben aislar también puestos y unidades por
  tenant; GP-02 no lo exige.

## D-06 Tipos de contrato (clarificación 3)

- **Decision**: Lista fija en `config/hrms.php` → `gestion_personal.tipos_contrato`: `indefinido` «Indefinido»,
  `plazo_fijo` «Plazo fijo», `temporal` «Temporal», `servicios_profesionales` «Servicios profesionales». El vínculo
  guarda la **clave** (`tipo_contrato_clave`); la etiqueta es de presentación. Una clave fuera de la lista →
  422 `CONTRATO_NO_HABILITADO`.
- **Rationale**: Sin catálogo existente y sin contratos configurables por tenant; una clave estable permite ampliar la
  lista sin migrar datos.
- **Alternatives considered**: tabla `tipos_contrato` (sugiere configurabilidad excluida); texto libre (no validable).

## D-07 Transacción y concurrencia en SQLite

- **Decision**: Orden de `RegistrarIngreso`:
  1. Autorizar y resolver tenant/ficha (404 uniforme).
  2. Validar forma y contrato; resolver **fuera** de la transacción un reintento válido por
     `(tenant_id, operacion_id)` → 200 con el resultado, o 409 si la huella o el actor difieren.
  3. `DB::transaction` (deferred) cuya **primera instrucción** es `UPDATE colaboradores SET updated_at = updated_at
     WHERE id = ? AND tenant_id = ?` (toma el bloqueo de escritura y «bloquea» la ficha).
  4. Con el bloqueo tomado: reintentar la lectura del paso 2 (carrera idempotente), verificar la posición por
     `EstructuraFacade::posicionEsAsignable` en la **misma conexión**, comprobar superposición de vínculos de la ficha
     y de asignaciones de la posición (D-03).
  5. Insertar vínculo y asignación (los *triggers* vuelven a comprobar) y confirmar ambos o ninguno.
  6. `SQLITE_BUSY` / `database is locked` → hasta 3 reintentos con la misma clave y espera acotada (50–200 ms);
     agotados, 503 `ESTRUCTURA_NO_VERIFICABLE` (la operación no se confirmó y puede reintentarse con la misma clave). Un *trigger* que aborta se traduce a
     `VINCULO_SUPERPUESTO` o `POSICION_NO_DISPONIBLE` según su mensaje.
- **Rationale**: Evita el error de lectura previa seguida de escritura (`SQLITE_BUSY_SNAPSHOT`) y cumple RN07/RN08.
  Las posiciones involucradas se bloquean en orden de ID (aquí solo hay una, pero queda como regla para movimientos).
- **Alternatives considered**: lecturas previas dentro de la transacción (riesgo de instantánea obsoleta);
  `BEGIN IMMEDIATE` manual (rompe el contador de transacciones de Laravel y las pruebas con `RefreshDatabase`).

## D-08 Estado derivado y lectura

- **Decision**: `SituacionLaboralDerivada::paraFecha(vínculos, hoy)`: **Activo** si existe un vínculo con
  `fecha_ingreso <= hoy` y (`fecha_salida` nula o `> hoy`); **Desvinculado** si no hay vigente y existe alguna
  `fecha_salida <= hoy`; **SinVinculoVigente** en otro caso. El próximo ingreso es el vínculo con `fecha_ingreso > hoy`
  más cercano. `hoy` es `ContextoDeTenant::hoy()` (zona del tenant, GP-01); no hay tarea programada.
- **Rationale**: Es la definición de la spec (sección 4) y el insumo; se evalúa en cada lectura.
- **Alternatives considered**: columna `estado` actualizada por tarea (obsoleta entre ejecuciones, prohibida).

## D-09 Impacto sobre GP-01 y los lectores

- **Hallazgo**: GP-01 trae `LectorLaboralPort`, `LectorEstructuraPort` y `LectorDeCapacidadesPort` con adaptadores
  «no instalado», y su filtro de estado trata «Sin vínculo vigente» como «sin vínculo vigente hoy».
- **Decision**:
  - `EloquentLectorLaboral` implementa `situacionVigente`, `idsConVinculoVigente`, `idsPorEstado`,
    `colaboradoresEnPosiciones` y `trayectoria` sobre las nuevas tablas, y se añade `estadosPresentes(tenantId, fecha)`
    para que `ObtenerFiltrosDeColaboradores` ofrezca solo los estados con fichas.
  - **Ajuste a GP-01**: `Sin vínculo vigente` pasa a significar «ni vigente ni salida anterior»; los desvinculados se
    excluyen de ese filtro. `ListarColaboradores` usa `idsPorEstado` para los tres estados cuando el lector está
    instalado.
  - `EstructuraFacadeLector` implementa `LectorEstructuraPort` sobre `EstructuraFacade`; `LectorDeCapacidadesGp02`
    devuelve verdadero para `registrar_ingreso` (y falso para el resto).
  - El detalle de GP-01 añade `laboral.proximo_ingreso` y hechos `Ingreso`/`Reingreso`; no cambia ningún campo
    existente.
- **Rationale**: Cumple FR-026 sin duplicar la derivación del estado.

## D-10 Reintento, huella y errores

- **Decision**: `operacion_hash` = SHA-256 del cuerpo canónico (`fecha_ingreso`, `tipo_contrato`, `posicion_id`,
  `colaborador_id`). Misma clave y huella y mismo actor → 200; otra huella u otro actor → 409
  `CLAVE_DE_OPERACION_REUTILIZADA` (el insumo fija 409; GP-01 usó 422 por la spec de entonces, y GP-02 sigue su
  propio contrato). Falta de cabecera → 422 `CLAVE_DE_OPERACION_REQUERIDA`.
- **Rationale**: Idéntico mecanismo de GP-01 con el código HTTP que pide el insumo.

## D-11 Metadatos del negocio

- **Decision**: `created_by`, `updated_by` (id de cuenta sin FK), `created_at` (momento del registro) y
  `updated_at` en ambas tablas; `version` entero inicial 1, reservado para futuros movimientos y salidas. Sin tabla de
  auditoría.
- **Rationale**: FR-008 y el insumo.

## D-12 Frontend y diseño

- **Decision**: Pantalla `/colaboradores/:id/ingreso` con patrón Formulario; `SelectorDePosicion` sobre `Combobox`
  con carga remota paginada; el aviso «Ingreso programado» se calcula con la fecha del navegador solo como ayuda (el
  servidor decide con la zona del tenant). Ver «Diseño de frontend» del plan.
- **Alternatives considered**: modal sobre el detalle (el prototipo lo sugiere; un formulario de página cumple el patrón
  Formulario y el foco/teclado de WCAG con menos riesgo).
