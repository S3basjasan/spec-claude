# Plan · 01b.4 Categorías por unidad y visibles por rol

## 1. Contexto de la feature
- Stack y arquitectura según `constitution.md`.
- Backend: `backend/app/Modules/Compensaciones/` (categorías: request, casos de uso, repositorio, auditoría, `CategoriasFacade`).
- Frontend: `frontend/src/modules/compensaciones/categorias/`.
- Depende de: 01b.3 y 01b.5 (las dos cambian la misma tabla).
- Esta spec crea los puertos hacia otros módulos que usan todas las specs siguientes (ver «Puertos» en la sección 3 y «Caminos provisionales» en `00-decisiones-compartidas.md`).

## 2. Modelo de datos
- `categorias_reconocimiento` (se agrega la columna `unidades_ids`, JSON; esta spec crea la migración).
- Externas, solo por Facade: `unidades_organizacionales` (Estructura), `colaboradores` y su unidad (Gestión de Personal).

## 3. Backend
Lectura: sesión Sanctum (401). Gestión: permiso `categorias.gestionar` (403).

| Operación | Endpoint | Notas | Requisito |
|---|---|---|---|
| Crear y editar categoría | POST /api/compensaciones/categorias · PUT /api/compensaciones/categorias/{id} | El body agrega `unidades_ids` (lista de enteros distintos, al menos uno) o `todas_las_unidades: true`, que se resuelve a todas las unidades activas en ese momento. Cada unidad debe existir y estar activa (`EstructuraFacade::getUnidadesPorIds`); si no, 422. Lista vacía: 422. El cambio de unidades se registra en la auditoría de la categoría con el campo `unidades`. | CA-01, CA-02, CA-08 |
| Listar categorías | GET /api/compensaciones/categorias | Con `admin`: todas, activas e inactivas, con los nombres de sus unidades (una sola llamada a `getUnidadesPorIds` por página). Líder (`EquipoPort::esLider`): todas las activas, sin acciones de gestión. Los demás: solo las activas asignadas a su unidad actual (`ColaboradoresPort::cuentaDeUsuario` y `unidadActual`). Si la persona no tiene ficha o unidad, la lista sale vacía. | CA-03 a CA-05 |
| Consultar categoría | GET /api/compensaciones/categorias/{id} | Sin `admin`, una categoría que no es de su unidad responde 404, salvo cuando se resuelve un registro histórico por la Facade. | CA-05 |
| Unidades disponibles | GET /api/compensaciones/unidades-disponibles | Solo `admin`. Unidades activas para el selector (`EstructuraFacade::getUnidadesActivas`). | CA-01 |

- `CategoriasFacade` agrega `seleccionablesParaUnidad(int $unidadId)`: categorías activas, con competencia activa y asignadas a esa unidad. La usarán asignar desafío (02.3) y otorgar (03.1); cualquier otra categoría se rechaza con 422. (CA-06, CA-07)
- Gerencia: en el repositorio de prueba la representa el Administrador, que ya ve todas las categorías (ver §3 de `00-decisiones-compartidas.md`).

Puertos (Aplicación) y adaptadores (Infraestructura) que crea esta spec:

| Puerto | Métodos | Adaptador en el repositorio de prueba |
|---|---|---|
| `EmpresaActualPort` | `tenantId()` | `EmpresaActualPorSesionOConfiguracion`. Con sesión: `SharedFacade::getContextoDeTenant(userId)`. Sin sesión (tareas programadas): `config('compensaciones_prueba.tenant_id')`. **Provisional** en la parte sin sesión. |
| `ColaboradoresPort` | `cuentaDeUsuario(userId)`, `nombres(ids)`, `vigentes(ids, fecha)`, `unidadActual(ids, fecha)` | `ColaboradoresDesdePersonal`: `getCuentaDeUsuario`, `getColaboradoresPorIds`, `vigentesPorIds`, `getDepartamentoActual` (todos existen). |
| `UnidadesPort` | `activas()`, `porIds(ids)` | `UnidadesDesdeEstructura`: `getUnidadesActivas`, `getUnidadesPorIds` (existen). |
| `EquipoPort` | `esLider(colaboradorId)`, `equipoDe(liderId)`, `liderDe(colaboradorId)`, `usuariosDe(colaboradorIds)` | `EquipoDesdeConfiguracion`, **provisional**. Lee `config/compensaciones_prueba.php`: `equipos` (líder → lista de colaboradores) y `usuarios` (colaborador → usuario), con ids que coinciden con `PersonalSeeder`. Cuando Gestión de Personal publique los métodos que faltan, se escribe `EquipoDesdePersonal` y se cambia solo el enlace en `CompensacionesServiceProvider`. |

- `config/compensaciones_prueba.php` lleva un comentario que dice que es solo para el repositorio de prueba y no se usa en el proyecto real.
- En las pruebas (Pest), los cuatro puertos se reemplazan por implementaciones falsas; ninguna prueba depende del archivo de configuración.
- Categorías existentes: la migración agrega la columna vacía. Un comando idempotente, `compensaciones:asignar-unidades-iniciales`, asigna todas las unidades activas a las categorías sin unidades (las migraciones no leen datos de otros módulos). Una categoría sin unidades solo la ve el Gestor, con el aviso «Sin unidades asignadas», y no es seleccionable.

Mensajes que muestra el frontend:
- 422: «Elige al menos una unidad.» / «La unidad seleccionada ya no está activa.»

Pruebas (Pest):
- Crear sin unidades o con una unidad inactiva: 422.
- `todas_las_unidades` guarda las unidades activas de ese momento.
- Un colaborador ve solo las activas de su unidad; el líder ve todas las activas sin acciones; el `admin` ve todas.
- `seleccionablesParaUnidad` excluye inactivas, de otra unidad y con competencia inactiva.
- El comando asigna unidades una sola vez aunque se ejecute dos veces.
- La auditoría registra el cambio de unidades.

## 4. Frontend
- Referencia visual: tablero «Categorías visibles por rol y asignación» del prototipo (`CategoriasPorRol.dc.html`). Se copia a `design/` antes de implementar la interfaz.
- En el formulario, selector múltiple de unidades activas con la opción «Todas las unidades»; error junto al campo si queda vacío.
- En la tabla del Gestor, columna «Unidades» (nombres o «Todas»); en el detalle, la lista de unidades.
- El colaborador ve la misma pantalla de consulta, filtrada por el servidor; no ve controles de gestión.
- Pruebas: Vitest del selector (obligatorio, «Todas») y E2E: el Gestor asigna una categoría a una unidad y un colaborador de otra unidad no la ve.

## 5. Relación con otros módulos
- Estructura, por `UnidadesPort`: `getUnidadesActivas`, `getUnidadesPorIds`.
- Gestión de Personal, por `ColaboradoresPort`: `getCuentaDeUsuario`, `getDepartamentoActual`, `getColaboradoresPorIds`, `vigentesPorIds`. Por `EquipoPort` (provisional): líder, equipo y usuario de cada colaborador, a pedir al responsable.
- Shared, por `EmpresaActualPort`: `getContextoDeTenant`.
- No se leen tablas de otros módulos.
