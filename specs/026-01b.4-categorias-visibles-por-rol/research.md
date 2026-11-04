# Research: Categorías por unidad y visibles por rol (01b.4)

No quedaban marcadores `NEEDS CLARIFICATION`. Se investigaron los puntos donde el plan de origen, la spec y el código actual deben encajar.

## 1. Cómo guardar las unidades asignadas

- **Decision**: columna `unidades_ids` JSON en `categorias_reconocimiento` (enteros ordenados, sin repetir), como define `modelo-de-datos.dbml`; sin tabla pivote ni FK.
- **Rationale**: ya se hizo igual con `indicadores` (migración `2026_11_03_000001`); la relación con `unidades_organizacionales` es lógica por regla del módulo (otro módulo, solo por Facade). El `UPDATE` condicional por versión que protege la edición concurrente cubre el cambio de unidades sin lógica adicional.
- **Alternatives considered**: tabla `categoria_unidades` (normalizada, permitiría índices y conteos) — descartada: el modelo de datos aprobado fija el JSON y la escala es de decenas de unidades; no hay FK posible hacia otro módulo.

## 2. Filtrar por unidad en la base

- **Decision**: `whereJsonContains('unidades_ids', $unidadId)` en el repositorio Eloquent cuando el filtro trae `unidadId`.
- **Rationale**: soportado por Laravel en SQLite (hoy) y PostgreSQL (destino del modelo). Los ids se guardan como enteros JSON, así que la comparación es exacta.
- **Alternatives considered**: cargar todas y filtrar en PHP — descartado: rompería la paginación en servidor. Verificar con una prueba que el filtro funciona en el motor de pruebas.

## 3. Quién es «líder»

- **Decision**: `EquipoPort::esLider(colaboradorId)` con el adaptador provisional `EquipoDesdeConfiguracion` (lee `config/compensaciones_prueba.php`, clave `equipos`).
- **Rationale**: Gestión de Personal todavía no publica líder/equipo; el plan de origen ya acepta el camino provisional y lo aísla en un único enlace del `ServiceProvider`. Gerencia = Administrador en el repo de prueba, por lo que ya cae en el alcance de gestor.
- **Alternatives considered**: usar el rol del token — descartado: el líder es un Colaborador o Administrador con equipo (no hay rol propio); inferirlo por el rol daría falsos líderes.

## 4. Unidad «actual» del colaborador

- **Decision**: `ColaboradoresPort::cuentaDeUsuario(userId)` → `colaborador_id` y `unidadActual([ids], fecha)` → unidad vigente en la fecha de hoy (zona `config('compensaciones.zona_horaria')`). El adaptador usa `PersonalFacade::getCuentaDeUsuario` y `getDepartamentoActual`, con el tenant de `EmpresaActualPort`.
- **Rationale**: son los métodos públicos existentes (`getDepartamentoActual` ya devuelve la unidad de la posición de la asignación laboral vigente). Sin ficha o sin unidad → alcance `Ninguno` (lista vacía). Si la lectura de Estructura falla (`InformacionNoDisponible`), el listado responde un error general seguro; no se muestra nada a medias.
- **Alternatives considered**: cachear la unidad por sesión — descartado: la spec exige que un cambio de unidad se refleje al reabrir (US2 AC4).

## 5. Tenant sin sesión

- **Decision**: `EmpresaActualPorSesionOConfiguracion`: con sesión usa `SharedFacade::getContextoDeTenant(userId)`; sin sesión (tareas programadas, comando) usa `config('compensaciones_prueba.tenant_id')`. Provisional en la parte sin sesión.
- **Rationale**: las Facades de Personal piden `tenantId`; el comando de migración de datos no tiene sesión pero tampoco necesita Personal (solo Estructura, que no usa tenant en `getUnidadesActivas`). El puerto se crea ya porque lo usan las specs siguientes.
- **Alternatives considered**: pasar el tenant desde el controlador — descartado: filtra un detalle de infraestructura a cada caso de uso.

## 6. Número de personas por unidad (FR-014)

- **Decision**: no incluirlo en esta entrega; el selector muestra nombre y, con la selección actual, cuántas unidades alcanza («La verán N unidades» / «Nadie la verá»). Se deja anotado como pendiente con el responsable de Gestión de Personal.
- **Rationale**: ningún método de las Facades listadas en el plan de origen cuenta personas por unidad; calcularlo con `buscarColaboradoresVigentes` + `getDepartamentoActual` para todas las unidades sería caro y erróneo (pagina). Inventar un puerto/adaptador sin respaldo del módulo dueño incumple la regla de no leer lo que no se publica.
- **Alternatives considered**: contar en el cliente — descartado: el cliente no tiene los colaboradores. El mockup muestra «24 personas»; la desviación queda registrada en el plan.
- **Efecto en la spec**: FR-014 y Assumptions ya se enmendaron: el selector muestra el alcance en unidades y «Nadie la verá», sin conteo de personas. Seguimiento: pedir a Gestión de Personal un método de conteo por unidad.

## 7. `todas_las_unidades` y el campo `aplica_a_todas`

- **Decision**: se guardan ids individuales. La respuesta calcula `aplica_a_todas = todas las unidades activas ∈ unidades_ids`.
- **Rationale**: respeta FR-011 (las unidades nuevas no se agregan solas) y permite mostrar «Todas las unidades» sin un indicador persistente que se desactualice. Si una unidad nueva se crea después, `aplica_a_todas` pasa a `false` por sí solo, que es lo honesto.
- **Alternatives considered**: bandera `todas` persistida — descartada por contradecir FR-011.

## 8. Control de acceso a `seleccionablesParaUnidad` (US4 AC4)

- **Decision**: método de Facade interno, sin endpoint HTTP; no recibe usuario.
- **Rationale**: el plan de origen lo define como método de Facade para 02.3 y 03.1; el acceso por rol lo ejercen sus endpoints. Un endpoint de consulta duplicaría la regla sin consumidor actual.
- **Alternatives considered**: `GET /categorias/seleccionables?unidad_id=` — descartado hasta que una función lo pida.

## 9. Impacto sobre pruebas existentes

- **Decision**: `unidades_ids`/`todas_las_unidades` es obligatorio en `POST`/`PUT`; las pruebas y el E2E existentes que crean o editan categorías se ajustan (fábrica con todas las unidades activas por defecto). El resto de listados/detalles de las pruebas existentes usan un usuario `admin` y no cambian de resultado.
- **Rationale**: FR-002/FR-013. Los puertos se reemplazan por falsos en Pest (ninguna prueba depende de `compensaciones_prueba.php`).

## 10. Gerencia «solo consulta»

- **Decision**: no se implementa un modo de solo lectura distinto; en el repo de prueba Gerencia = Administrador, que ya tiene acceso de gestión.
- **Rationale**: lo establecido en el plan de origen y en `00-decisiones-compartidas.md`; el rol real de Gerencia llegará con el módulo de autorización. SC-007 («ningún intento de edición de Gerencia tiene efecto») queda verificable para líder (403) pero no para Gerencia en el repo de prueba.
