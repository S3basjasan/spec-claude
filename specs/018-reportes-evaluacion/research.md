# Research: Reportes de evaluación de resultados

No quedaron `NEEDS CLARIFICATION` en el Technical Context. Esta investigación registra las decisiones técnicas que el insumo `plan-reportes.md` dejaba abiertas o que el estado real del repositorio obliga a ajustar.

## R1. Cómo lee Reportes los datos de las demás features

- **Decision**: puertos de lectura propios en `Application/Ports` (`FuenteDePlanesInterface`, `FuenteDeActividadesInterface`, `FuenteDeMicrolearningInterface`, `FuenteDeDnfInterface`) con adaptadores en `Infrastructure/Adapters` que usan los repositorios existentes: `PlanRepositoryInterface::planesConAcciones`, `ActividadRepositoryInterface::actividades/asistencias`, `ProgresoMicroRepositoryInterface::completaciones/progresosDe` y `DnfRepositoryInterface`. El `PlanesParaReportes` calcula el estado de cada acción con `CalculadorDeEstadoDeAccion` y `RelojInterface::hoy`.
- **Rationale**: el insumo habla de «Facades», pero Plan, Actividades, Micro-Learning y DNF están en el mismo módulo `Desarrollo` que Reportes. `DesarrolloFacade` es la API pública para **otros** módulos (017 R1 ya lo resolvió igual), y leer tablas ajenas desde el repositorio de Reportes acoplaría esquemas. Los puertos mantienen la regla «no acceder a tablas de otra funcionalidad» y permiten probar el dominio con dobles.
- **Alternatives considered**: (a) usar `DesarrolloFacade` desde el propio módulo — la Facade es contrato externo y crearía ciclos; (b) `JOIN` directo desde `EloquentReportesRepository` — acopla esquemas; (c) copiar datos a tablas de reporte — se desactualiza y contradice «las métricas no se guardan».

## R2. Departamento actual del colaborador

- **Decision**: añadir `PersonalFacade::getDepartamentoActual(int $tenantId, array $colaboradorIds, string $fecha): array` (`colaborador_id => unidad_id|null`), que resuelve la asignación laboral vigente en la fecha y, con `EstructuraFacade::getPosicionesDeGestionPorIds (mediante el puerto LectorEstructuraPort de Gestión de Personal)`, la unidad de su posición. Reportes lo consume por `DepartamentoDeColaboradorInterface` (adaptador `DepartamentoDeColaboradorDesdePersonal`) y los nombres de departamento por el puerto `DepartamentosInterface` ya existente (017).
- **Rationale**: FR-007 exige el departamento **actual**; hoy `PersonalFacade` no lo expone (solo ficha, cuenta y vigencia). El cambio es aditivo, de solo lectura y vive en el módulo dueño del dato.
- **Alternatives considered**: usar `unidad_id` de `ColaboradoresEvaluablesInterface` — es un adaptador provisional (`*SinDatos`) sin datos reales; usar el departamento de la actividad — es el destinatario, no el de cada colaborador.

## R3. Detalle de DNF para brechas

- **Decision**: añadir `DnfRepositoryInterface::evaluacionesDeCompetencias(tenantId, filtros): list<{dnf_id, fecha, colaborador_id, competencia_id, estado, nivel_requerido, nivel_actual}>` ordenadas por fecha y id, leyendo `dnf_colaboradores` y `dnf_detalles`. `getDnfRegistradas` (cabeceras) no basta porque no trae el estado por competencia.
- **Rationale**: las brechas cerradas y su evolución mensual exigen el estado de **cada competencia en cada DNF** (FR-012/017) y los niveles para la evolución de competencias del colaborador (FR-034).
- **Alternatives considered**: reconstruir el detalle llamando a `Obtener Dnf` por id (N+1); cambiar `getDnfRegistradas` (rompería el contrato publicado).

## R4. Regla de brecha cerrada, detectada y brechas activas

- **Decision**: `CalculadorDeBrechasCerradas` recorre, por (colaborador, competencia), las evaluaciones ordenadas por fecha. Estados `SinEvaluacion` se ignoran. Una brecha se **detecta** en la primera DNF con `ConBrecha` mientras no haya una abierta; se **cierra** en la primera DNF posterior con `Cumple` o `Supera` (la fecha de cierre es la de esa DNF). Tras un cierre, un nuevo `ConBrecha` abre otra brecha (caso límite de la spec). «Detectadas del período» = brechas cuya fecha de detección cae en el período (clarificación 5); «cerradas del período» = las cerradas con fecha en el período. **Brechas activas al cierre de un mes** = para cada colaborador, su DNF más reciente con fecha ≤ fin de mes, contando sus competencias `ConBrecha`.
- **Rationale**: es la definición literal de FR-012/017 y de las clarificaciones; la máquina de estados evita contar dos veces la misma brecha.
- **Consecuencia aceptada**: una brecha detectada en un período anterior y cerrada en el actual cuenta como cerrada del actual pero no como detectada del actual, así que `cerradas` puede superar a `detectadas`. Por eso ambas cifras se muestran por separado y la tarjeta no las presenta como fracción.
- **Alternatives considered**: contar todas las competencias «Con brecha» del período como detectadas (descartado en clarificación 5); estimar activas por (colaborador, competencia) en lugar de por DNF más reciente (la spec fija la DNF más reciente).

## R5. Período anterior equivalente y comparación

- **Decision**: `CalculadorDePeriodo` produce el rango anterior de igual duración inmediatamente previo (`fin_anterior = inicio − 1 día`, misma cantidad de días). `VariacionIndicador` calcula `diferencia = actual − anterior` y `variacion_pct = diferencia / anterior × 100`, nulos si el anterior no tiene datos o vale cero. Los mismos filtros (departamento, tipo, plan) se aplican a ambos períodos.
- **Rationale**: FR-008 y el caso límite «período anterior con valor cero»; mantener los filtros en la comparación es lo que hace útil la tarjeta (el tablero la oculta con filtros porque comparaba contra metas globales, ver plan).
- **Alternatives considered**: comparar contra el año anterior fijo o una meta (diseño) — no es lo que pide la spec.

## R6. Prorrateo por departamento

- **Decision**: `ProrrateadorPorDepartamento` calcula, por actividad, `proporcion = asistentes(dep) / asistentes(total)` (asistentes = asistencia Presente) y aplica la misma proporción a **costo** y a **beneficio estimado** (clarificación 2); el ROI porcentual no cambia, solo los montos. Si el total de asistentes es 0, la proporción es 0 y la fila no suma costos. Horas y satisfacción usan solo participantes del departamento.
- **Rationale**: FR-007 y clarificación 2. Aplicar la misma proporción a costo y beneficio mantiene el ROI coherente.
- **Alternatives considered**: prorratear por inscritos (el costo se concentra en quien asistió) o solo el costo (ROI distorsionado).

## R7. Caché

- **Decision**: **no** se implementa caché en la primera versión. Los datos se arman en una sola pasada por solicitud (`RecolectorDeReportes`) con consultas por lote, sin N+1. Si la medición de rendimiento del quickstart supera 5 s con el volumen objetivo, se agregará `Cache::remember` con TTL corto por (tenant, filtros) solo en `dashboard`.
- **Rationale**: el insumo lo permite «si el rendimiento lo exige»; la métrica depende de datos que cambian (asistencias, encuestas) y una caché prematura obliga a definir invalidación. Constitución IV: evitar abstracciones especulativas.
- **Alternatives considered**: caché con invalidación por eventos (complejidad sin necesidad medida).

## R8. Excel y PDF

- **Decision**: PDF con `dompdf` (ya en `composer.json`, usado por `CertificadoPdfDompdf`) y una vista Blade/HTML plana de indicadores y tablas, sin gráficos. Excel `.xlsx` con un adaptador `ExcelConZip` que escribe un libro mínimo (SpreadsheetML: `[Content_Types].xml`, `workbook.xml`, hojas y estilos básicos) con `ZipArchive` (extensión `zip` verificada en el entorno).
- **Rationale**: la Constitución IV prohíbe añadir dependencias sin aprobación y no hay librería de hojas de cálculo preaprobada. Un libro con cuatro hojas de texto y números es pequeño y probable de validar abriendo el zip.
- **Alternatives considered**: PhpSpreadsheet u OpenSpout — requieren aprobación explícita (si se otorga, solo cambia el adaptador tras `GeneradorDeExcelInterface`); CSV — no cumple «hojas».

## R9. Gráficos

- **Decision**: gráficos de barras, línea y pastel con SVG/CSS dentro de `reportes/components`, sin librería. Cada gráfico lleva una tabla accesible equivalente (como en `design/ReportesAdmin.dc.html`).
- **Rationale**: el insumo habla de «la librería definida en constitution.md», pero la constitución no define ninguna y añadir una requiere aprobación. Los tableros ya muestran la solución sin dependencias.
- **Alternatives considered**: Recharts/Chart.js — requieren aprobación.

## R10. Ventanas de tiempo calculadas

- **Decision**: `ReglasDeEncuesta` y `ReglasDeSeguimiento` (dominio puro) reciben la fecha de hoy del `RelojInterface`. Encuesta: abierta de `fecha_fin` a `fecha_fin + 30 días` (inclusive), si la actividad es Finalizada y el colaborador estuvo Presente (clarificación 1). Seguimiento: el hito `h` vence en `fecha_fin + h días` y se acepta de ese día a `+ 15 días` (inclusive). Pendientes = elegibles sin respuesta dentro de la ventana.
- **Rationale**: FR-022/025; mantener las ventanas derivadas evita tablas de planificación y jobs. Las respuestas ya registradas quedan aunque la ventana cierre.
- **Sin fecha de activación**: no se guarda ni se compara una fecha de puesta en marcha; las ventanas se aplican a toda actividad Finalizada. Si la ventana ya pasó, queda sin respuesta; si sigue abierta, se ofrece.
- **Alternatives considered**: guardar filas pendientes con vencimiento (requiere jobs y puede desincronizarse del plan); una fecha de corte de activación (complejidad sin necesidad de negocio).

## R11. Alerta de reingreso

- **Decision**: `CalculadorDeSeguimiento` calcula por actividad y hito `elegibles`, `respuestas`, `tasa_respuesta`, `promedio_aplicacion`, `promedio_apoyo` y marca `alerta_reingreso` si existe algún par consecutivo (30→60 o 60→90) con ≥ 3 respuestas en ambos y `promedio_aplicacion(anterior) − promedio_aplicacion(siguiente) ≥ 1`.
- **Rationale**: FR-028 literal; «hito anterior» se interpreta como el inmediatamente previo en la secuencia 30-60-90.
- **Alternatives considered**: comparar solo 30 vs 90 (diseño por niveles alto/medio/bajo) — la spec define una alerta binaria.

## R12. Mi progreso, certificados y plan de desarrollo

- **Decision**: `ObtenerMiProgreso` combina: actividades Finalizadas con asistencia Presente del colaborador (`FuenteDeActividadesInterface`), micro-contenidos completados y certificados (`FuenteDeMicrolearningInterface`, que reutiliza `progresosDe`), brechas cerradas y evolución de competencias (`FuenteDeDnfInterface`), próximas acciones (`AccionesDePlanInterface::deColaborador` ya existente, 017) y pendientes (`ReglasDeEncuesta/Seguimiento`). Los certificados se descargan con el endpoint existente de Micro-Learning (`CertificadoMicroController`).
- **Rationale**: FR-034/035; evita duplicar la generación de certificados y reutiliza puertos existentes.
- **Alternatives considered**: reexponer el PDF desde Reportes (duplicaría permisos y lógica).

## R13. Tenant, identidad y auditoría

- **Decision**: las tres tablas llevan `tenant_id` (como el resto de `Desarrollo`). `colaborador_id` se obtiene de `CuentaColaboradorInterface::cuentaDe(usuarioId)` en los endpoints del colaborador. `impactos_accion` guarda `created_by` y `updated_by` (usuario) y `NombresDeUsuarioInterface` resuelve los nombres para mostrarlos. Las exportaciones no se auditan (spec §5).
- **Rationale**: aislar datos entre tenants y cumplir FR-040 sin tablas de auditoría extra; las encuestas y seguimientos son su propio registro inmutable.
- **Alternatives considered**: tabla de historial de impactos (no pedida por la spec).

## R15. Fuentes no disponibles y colaboradores sin departamento

- **Decision**: `RecolectorDeReportes` envuelve cada fuente (planes, actividades, micro-learning, DNF, departamento actual) y, si una lanza una excepción de lectura, devuelve la lista `fuentes_no_disponibles` (`plan`, `actividades`, `microlearning`, `dnf`, `personal`) y deja esa parte del conjunto en `null`. `CalculadorDeIndicadores` marca como «no disponible» los indicadores y gráficos que dependen de la fuente caída y calcula el resto. Los colaboradores sin departamento actual (`null`) se agrupan como «Sin departamento» (`departamento_id: null`) en `horas_por_departamento` y se excluyen cuando `departamento_id` está filtrado.
- **Rationale**: FR-043 y FR-007; evita mostrar valores calculados con datos incompletos sin tumbar todo el dashboard.
- **Alternatives considered**: responder 500 si falla una fuente (peor experiencia); omitir en silencio la fuente (valores engañosos); descartar a los colaboradores sin departamento (pierde horas reales).

## R14. Línea base de pruebas

- **Decision**: validar contra la lista de fallos preexistentes (Pest 111 rojos, Vitest 54, `tsc -b` 18) y, para E2E en esta máquina, usar una configuración temporal de Playwright que siembre cada seeder sin `IngresosSeeder` (memoria del proyecto). `ReportesSeeder` no depende de `IngresosSeeder`.
- **Rationale**: no atribuir a esta feature rojos ajenos.
- **Alternatives considered**: ninguna.
