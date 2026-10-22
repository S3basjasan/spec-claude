# Research: Rutas y planes de carrera

Decisiones de la Fase 0. Cada una resuelve una incógnita detectada al contrastar la spec, el insumo `plan-rutas.md` y el código existente.

## R1. Dónde vive el módulo y cómo colabora con los demás

- **Decisión**: dentro del módulo `Desarrollo` (backend) y `desarrollo/rutas` (frontend), con Clean Architecture como Actividades (017) y Reportes (018). Estructura y Personal se consumen por puertos de `Application/Ports` cuyos adaptadores llaman a `EstructuraFacade` y `PersonalFacade`.
- **Rationale**: es la convención ya usada; la constitución prohíbe tocar clases o tablas de otro módulo.
- **Alternativas**: un módulo nuevo `Carrera` (rechazado: el insumo fija `backend/app/Modules/Desarrollo`); leer tablas de puestos directamente (prohibido).

## R2. Facade propia

- **Decisión**: ampliar `DesarrolloFacade` (aditivo, solo lectura) con `inscripcionesActivas(int $tenantId, int $colaboradorId)` y `obtenerNivel(int $tenantId, int $nivelId)`, ambas con `tenantId` como el resto de métodos de la Facade. Detalle en `contracts/rutas-puertos-y-facade.md`.
- **Rationale**: el insumo las pide para Adecuación, PDI y Reportes; los métodos existentes ya reciben `tenantId`.
- **Alternativas**: Facade nueva (rechazado: `DesarrolloFacade` ya agrupa los consumos externos del módulo).

## R3. «Conocimientos» sin grado: competencias con grado, requisitos de texto informativos

- **Contexto**: el tablero y la spec hablan de tres columnas (Competencias, Conocimientos, Experiencia) y de conocimientos con grado A–D. `EstructuraFacade::getPuesto` devuelve, además de funciones, **competencias con grado** (`grado_requerido` A–D) y **requisitos de texto** de tipo `Formacion`, `Capacitacion` y `Experiencia`. No existe en Estructura un catálogo de conocimientos con grado, y no se implementará en este módulo (pertenece al componente de Estructura Empresarial).
- **Decisión (Opción A)**:
  1. Solo las **competencias** muestran grado requerido y diferencial respecto del nivel anterior.
  2. La tarjeta/columna «Conocimientos» muestra los requisitos de **Formación y Capacitación** del puesto tal como los entrega la Facade, sin grado, sin comparación y sin diferencial, etiquetada **«Informativo»**.
  3. La columna «Experiencia» muestra la **experiencia mínima en años** guardada en `ruta_niveles` (único requisito propio) y su diferencial; los requisitos de texto de tipo Experiencia del puesto aparecen como información debajo.
  4. El contrato del puesto expone únicamente competencias con grado y requisitos de texto (Formación, Capacitación, Experiencia). No hay tipo ni campo «conocimiento con grado» en el contrato ni en el modelo de datos.
  5. **Punto de extensión**: bandera `desarrollo.conocimientos_con_grado` en `config/desarrollo.php` (por defecto `false`, lectura de `DESARROLLO_CONOCIMIENTOS_CON_GRADO`). Con `false` el comportamiento es el de los puntos 1–4. **La rama `true` no se implementa**: si Estructura incorpora conocimientos con grado, bastará implementarla detrás de la bandera, ampliando el puerto y el cálculo de diferencial sin rehacer el diseño.
- **Rationale**: respeta «no duplicar datos de Estructura», no inventa un catálogo y mantiene las tres tarjetas del tablero con datos que sí existen.
- **Alternativa descartada**: esperar a que Estructura incorpore conocimientos con grado antes de construir el detalle del nivel. Se rechaza porque bloquearía la feature por una capacidad de otro componente; la bandera permite adoptarla después.
- **Alternativa descartada**: mostrar Formación y Capacitación con grado o calcularles diferencial (no existe grado que comparar).
- **Spec**: las frases de `spec.md` que asignan grado A–D a los conocimientos quedan desalineadas y se corrigen con `/speckit-clarify`; hasta entonces manda esta decisión.

## R4. Permisos `desarrollo.rutas.*` sin infraestructura de roles

- **Hallazgo**: existe solo `users.role` (`admin` | `user`), emitido como capacidad del token (`SanctumEmisorToken`) y el middleware `EnsureAdmin`. No hay tablas de permisos ni de roles.
- **Decisión**: un catálogo estable de seis claves en `ClaveDePermisoRutas` (value object) y un servicio `AutorizadorDeRutas` que resuelve las claves de un usuario según su capacidad: `admin` recibe las seis de gestión y consulta; `user` recibe solo `desarrollo.rutas.ver_propio`. Un único middleware `EnsurePermiso` (alias `permiso:<clave>`) autoriza cada ruta (403) sin mirar nombres de rol en controladores. `GET /api/rutas/permisos` devuelve las claves del usuario para que el frontend decida botones y secciones.
- **Rationale**: cumple «autorización en un único punto» y «claves estables»; cuando exista administración de roles solo cambia la fuente de `AutorizadorDeRutas`.
- **Alternativas**: tablas `permisos`/`rol_permiso` con seeder (rechazado: es una feature de administración de roles, fuera de alcance y toca todos los módulos); usar solo `admin` (rechazado: mezcla gestión y consulta propia y obliga a reescribir luego).
- **Nota sobre `desarrollo.rutas.ver`**: la spec marca que el colaborador podría ver rutas Vigentes con ese permiso, pero FR-035 asigna solo `_propio` al colaborador. Se resuelve así: la lista de rutas Vigentes disponibles (FR-031) viaja dentro de `GET /api/rutas/mi-ruta` bajo `ver_propio`; `GET /api/rutas` y `GET /api/rutas/{id}` exigen `ver` (hoy solo administrador), y si un rol futuro tuviera `ver` sin `gestionar`, el repositorio filtra a Vigentes.

## R5. Baja de colaborador → retiro automático (FR-021a)

- **Hallazgo**: Personal no emite eventos de baja; expone `vigentesPorIds(tenantId, ids, fecha)` (ids con vínculo laboral vigente en la fecha).
- **Decisión**: caso de uso idempotente `ConciliarBajasDeInscripciones`: toma los `colaborador_id` con inscripción Activa, consulta cuáles siguen vigentes y retira (motivo «Baja del colaborador», `resuelta_at` ahora, historial con `user_id` nulo = sistema) las de quien no lo está. Se invoca al inicio de `ListarInscripcionesDeRuta`, `ObtenerRuta` (cantera), `ObtenerMiRuta` y desde el comando Artisan `rutas:conciliar-bajas` (programable). Si Personal falla, no se retira nada y la consulta sigue (degradación segura). Reutiliza `ColaboradoresVigentesInterface::vigentesPorIds` (puerto existente). Ver el aviso de siembra en el plan: el seeder y los E2E solo inscriben a colaboradores con vínculo vigente.
- **Rationale**: no exige tocar Personal ni crear un bus de eventos; el efecto es inmediato para quien consulta y consistente en cantera.
- **Alternativas**: evento de dominio desde Personal (rechazado: no hay infraestructura de eventos entre módulos; sería una feature transversal); solo comando programado (rechazado: cantera desactualizada entre ejecuciones).

## R6. Identidad de los niveles al actualizar la ruta

- **Hallazgo**: `PUT /api/rutas/{id}` reemplaza datos y niveles; las inscripciones apuntan a `ruta_niveles.id`, así que borrar y recrear niveles rompería referencias.
- **Decisión**: cada nivel del cuerpo puede traer `id`. Los niveles con `id` se actualizan (orden, nombre, experiencia; puesto solo si el nivel no está en uso); los sin `id` se crean; los existentes que no vienen se eliminan (físicamente, solo dentro de la transacción y nunca si están referenciados por una inscripción Activa). Para respetar el único `(ruta_id, orden)` al reordenar, la transacción asigna primero órdenes temporales negativos y luego los finales. Tras aplicar, se valida que ningún objetivo Activo quede igual o anterior a su actual.
- **Rationale**: conserva referencias, cumple FR-011/017 y el insumo («niveles solo se eliminan en la transacción de actualización»).
- **Nota**: los niveles referenciados solo por inscripciones **no Activas** (Finalizada o Retirada) también bloquean el borrado físico por FK; se resuelve exigiendo que esos niveles se conserven (la regla de negocio solo prohíbe para Activas, pero la FK sin cascade lo impide), por lo que el caso de uso devuelve 422 «el nivel conserva historial de inscripciones» en ese caso.

## R7. Concurrencia

- **Decisión**: `rutas.version` entero como en `puestos`; `PUT` y `PATCH estado` reciben `version` y responden 409 si no coincide (caso borde «dos administradores»). Las operaciones de inscripción bloquean la fila de la inscripción dentro de la transacción.
- **Alternativas**: sin control (rechazado: la spec lo cubre como caso borde).

## R8. Código CAR-NNNN y nombre único

- **Decisión**: el código se genera dentro de la transacción de creación leyendo el máximo con bloqueo (patrón de `CodigoDeActividad` en 017); el nombre se normaliza (trim, espacios internos colapsados) y se guarda `nombre_clave` en minúsculas con índice único, como `puestos.nombre_clave`.
- **Rationale**: unicidad insensible a mayúsculas y espacios extremos (FR-003) con soporte de SQLite.

## R9. Diferencial sin almacenarlo

- **Decisión**: `CalculadorDeDiferencial` (dominio puro) recibe los perfiles de dos niveles consecutivos (ya resueltos por el puerto) y devuelve **solo para competencias**: `nuevo`, `sube` (con grados antes/después y diferencia), `baja` (grado menor), `igual`, y aparte `competencias_quitadas` (las que tenía el nivel anterior y este no pide); y por experiencia: años adicionales o `null` si falta alguno. Los perfiles de todos los niveles se obtienen con una lectura de `getPuesto` por puesto distinto (máx. ≈10 por ruta) y se memoizan en la petición. Los requisitos de texto (Formación, Capacitación, Experiencia) no entran al cálculo: se muestran tal cual (R3).
- **Alternativas**: guardar el diferencial (rechazado por FR-016).

## R10. Frontend

- **Decisión**: página de lista con tarjetas agrupadas por tipo y filtros en la URL; página de editor con pestañas Escalera / Participantes / Historial (la pestaña activa y el nivel seleccionado en la URL); `MiRutaPage` para el colaborador. `RequireRole` (patrón de actividades) elige vista admin o colaborador; `RequirePermiso` oculta acciones según `GET /api/rutas/permisos`. Los componentes de design system existentes (Modal, TextField, SelectField, Badge, ConfirmDialog, Alert, Skeleton, DataTable) se reutilizan sin crear equivalentes.
- **Rationale**: Constitución II/III y patrón de 017/018.

## R11. Pruebas

- **Decisión**: Pest Unit para las reglas de dominio (publicación, inscripción, nivel alcanzado, diferencial, cantera); Pest Feature por endpoint (autenticación, permiso, validación, historial, conciliación de bajas, Facade); Vitest para validación, estilos de estado y componentes de escalera y modales; un E2E Playwright del flujo principal (crear ruta → niveles → publicar → inscribir → nivel alcanzado → vista del colaborador). Los E2E no dependen de datos que otros seeders no garanticen (lección de la feature 018: no asumir `IngresosSeeder` en E2E; el seeder de rutas crea lo que necesita o el test lo prepara por API).
