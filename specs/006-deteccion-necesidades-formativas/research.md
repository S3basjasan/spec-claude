# Research: Detección de necesidades formativas (DNF)

Sin `NEEDS CLARIFICATION` pendientes. Lo siguiente resuelve las incógnitas técnicas y deja registradas las dependencias.

## R-01 — Disponibilidad de los datos de otros módulos (riesgo principal)

**Hallazgo (verificado en el código):**
- `EstructuraFacade::getPuesto(id)` ya devuelve `competencias[]` con `competencia_id`, `tipo` (Cardinal/Específica), `estado` (activa/inactiva) y `grado_requerido`. `getArbolDeUnidades()` e `getUnidad()` permiten resolver departamento y subunidades. **Estructura alcanza.**
- `PersonalFacade` solo expone fichas (`getColaborador`, `getColaboradoresPorIds`, `buscarPorIdentificacion`, `registrarFicha`). La ficha no guarda puesto ni departamento, el único estado laboral existente es `SinVinculoVigente` y no hay niveles por competencia. El vínculo y la trayectoria son de GP-02, aún no implementada.
- No existe módulo Plan de Formación.
- `colaborador_cuentas` relaciona `user_id` con `colaborador_id` (único en ambos).

**Decisión:** el módulo consume lo faltante a través de **puertos propios** (patrón de 005): `ColaboradoresEvaluablesInterface`, `NivelesActualesInterface`, `PlanDeFormacionInterface`, más `PerfilesDePuestoInterface` (este sí con adaptador real sobre Estructura). Los adaptadores de GP y de Plan nacen **provisionales y vacíos**; los métodos de Facade que deberán ofrecer GP y Plan de Formación se especifican en [contracts/dnf-puertos-y-facades.md](contracts/dnf-puertos-y-facades.md).

**Consecuencia honesta:** hasta que GP-02 entregue puesto, vínculo y niveles, `POST /api/dnf` responde 422 «ningún colaborador evaluable» en un entorno real. Las pruebas usan dobles de los puertos y el seeder escribe las DNF de ejemplo directamente en las tres tablas (no depende de seeders de GP). Es una decisión de secuencia, no de diseño: al llegar GP-02 solo cambian adaptadores y bindings.

**Alternativas descartadas:** (a) crear tablas de puesto/nivel en Desarrollo — duplica datos y viola «no administra»; (b) implementar parte de GP-02 aquí — fuera de alcance; (c) leer tablas de otros módulos — prohibido por la constitución.

## R-02 — Cálculo de la brecha

**Decisión:** un único servicio de dominio puro `CalculadorDeBrecha` con la escala `D=1, C=2, B=3, A=4`.
- `diferencia = actual − requerido`.
- `brecha% = (requerido − actual) / requerido × 100` solo si `actual < requerido`; si no, `0`. Se guarda con 2 decimales (p. ej. C vs A → 50.00, D vs A → 75.00).
- Sin nivel actual → `estado = SinEvaluacion`, `diferencia/brecha/prioridad = null`.
- Prioridad solo con brecha: 3 grados → Crítica; 2 → Alta; 1 → Media si la competencia es Cardinal, Baja si Específica.
- Estado: `<0` ConBrecha, `0` Cumple, `>0` Supera.

Nota de diseño verificada: con requerido B (3) y actual D (1) la diferencia es −2 pero la brecha % es 66.67, y con requerido D no hay brecha posible. La prioridad se decide por **grados**, no por porcentaje (spec §3.4).

**Alternativa descartada:** calcular en SQL o en el frontend — duplicaría reglas y dificulta la prueba exhaustiva de las 16 combinaciones (SC-003).

## R-03 — Inmutabilidad

**Decisión:** repositorio solo con `guardar(Dnf)` y `asignarPlan(dnfId, planId, actorId, momento)`. No hay rutas PUT/PATCH/DELETE. Los modelos Eloquent rechazan `updating`/`deleting` (excepto la asignación del plan, que se hace con un `UPDATE … WHERE plan_formacion_id IS NULL` atómico y comprueba filas afectadas). FK sin cascada. Garantiza «un plan por DNF» también bajo concurrencia, además de un índice único en `plan_formacion_id`.

## R-04 — Código secuencial DNF-0001

**Decisión:** `max(numero)+1` por tenant dentro de la transacción de generación, con índice único `(tenant_id, codigo)` y reintento acotado (3) ante colisión. SQLite serializa escrituras, y el índice único protege otros motores. Sin tabla de contadores (evita una abstracción especulativa).

## R-05 — Rendimiento de la masiva

**Decisión:** una sola transacción; perfiles de puesto leídos una vez por puesto distinto (caché en memoria durante la petición); niveles actuales pedidos por lote de colaboradores; inserciones por lotes de `dnf_detalles`. Cumple SC-002 con 100 colaboradores × decenas de competencias.

## R-06 — «Mi diagnóstico» y brechas activas

**Decisión:** el controlador toma el `user_id` del token; el caso de uso lo traduce a `{tenant_id, colaborador_id}` con `CuentaColaboradorInterface` (cuenta ↔ ficha, vía `PersonalFacade::getCuentaDeUsuario`, que esta feature añade de forma aditiva) y consulta la **DNF más reciente que contiene al colaborador** (individual o masiva, por `created_at` e `id` descendente). Orden: prioridad (Crítica > Alta > Media > Baja) y, a igual prioridad, mayor |diferencia|; empates finales por nombre de competencia. Las tres primeras son las prioritarias. La misma lógica alimenta `DesarrolloFacade::brechasActivas(colaboradorId)` y un adaptador `BrechasActivasDesdeDnf` que sustituye a `BrechasActivasSinDatos` en el provider.

## R-07 — Nombres y datos de referencia

**Decisión:** la DNF guarda ids y las copias del momento que exige la spec (nivel requerido, tipo de competencia, puesto). Los nombres (colaborador, competencia, departamento, puesto) se resuelven al leer, por lote, con las Facades; las competencias inactivas se siguen resolviendo (`getCompetenciasPorIds` incluye inactivas). Si un nombre ya no se puede resolver se muestra el código y «No disponible».

## R-08 — Avisos de omisión y exclusión

**Decisión:** `competencias_omitidas` se guarda en `dnf` (total de inactivas omitidas). Los **colaboradores excluidos** (sin puesto, sin perfil o sin vínculo vigente) se devuelven en el `201` como `avisos.excluidos[] {colaborador_id, nombre, motivo}`; no se persisten porque no forman parte del diagnóstico. El detalle histórico muestra siempre `competencias_omitidas` y el conteo de «sin evaluación» calculado de los detalles.

## R-09 — Gráfico

**Decisión:** gráfico propio de HTML y CSS (una fila por competencia: barra para el nivel actual, marca para el requerido y tramo discontinuo para la brecha, escala A–D), expuesto como una sola imagen (`role="img"` con resumen) y con la tabla del diagnóstico como equivalente accesible. Se eligió HTML/CSS en lugar de SVG porque cada fila ocupa el ancho disponible y los nombres largos se recortan sin recalcular coordenadas (320–1440 px). Sin librería de gráficos (Constitución IV: sin dependencias nuevas).

## R-10 — Autorización

**Decisión:** rutas `auth:sanctum`; las de administrador con el middleware `admin` existente; `GET /api/dnf/mi-diagnostico` solo con `auth:sanctum` y datos del propio usuario. Se declara **antes** de `/{id}` con `whereNumber('id')`. Todas las consultas filtran por el `tenant_id` del usuario autenticado; un `id` de otro tenant devuelve 404.
