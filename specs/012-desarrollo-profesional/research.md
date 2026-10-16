# Research: GP-03 · Información de desarrollo profesional

Se resolvieron contra el repositorio real (GP-01 en `develop`). No quedan `NEEDS CLARIFICATION`.

## Divergencias con el insumo

| Tema | Insumo | Decisión | Motivo |
|---|---|---|---|
| Base de datos | PostgreSQL, esquema por tenant | SQLite, `tenant_id` en cada tabla | Constitución y GP-01 |
| Código | `Modules/Personal`, `/api/colaboradores/{colaborador}` | `Modules/GestionPersonal`, rutas bajo `/api/colaboradores` y `/api/mi-ficha` | Precedente aprobado |
| Fechas futuras | no restringir | inicio nunca futuro; fin futuro solo en formación | Clarificación 3 de la spec |
| Versión de movilidad | usa `version` del colaborador | `movilidad_version` propia | FR-012, FR-018, bloques independientes |
| Mismo valor en movilidad | 200 sin incrementar | igual, tras comprobar la versión | Spec escenario 3 |
| IDs en JSON | cadenas decimales | enteros | Convención de GP-01 (`ColaboradorDetalleResource`) |
| Formato de error | `{error:{code,message,fields}}` | el envoltorio actual (`message`, `code`, `errors`) | Consistencia con GP-01 |
| Permisos `personal.*` | permisos nuevos | sin permisos nuevos; admin = capacidad `admin`; lectura propia = `puedeConsultarFicha` | Spec: no se crean roles ni permisos |

## Decisiones

### D1. Dónde guardar movilidad
- **Decisión**: columnas en `colaboradores`: `movilidad_estado` (default `NoInformada`), `movilidad_observaciones`, `movilidad_actualizada_at`, `movilidad_actualizada_por`, `movilidad_version` (default 1).
- **Rationale**: valor único por ficha; evita una tabla 1:1. Una versión propia permite que guardar movilidad y editar datos personales se detecten de forma independiente y no sobrescribe `updated_by`/`updated_at` de la ficha (FR-012, FR-018).
- **Alternativas**: tabla `movilidades` 1:1 (más joins sin beneficio); reutilizar `version` de la ficha (acopla bloques y contamina la auditoría de GP-01).
- **Impacto en GP-01**: `ColaboradorModel` usa `$guarded = []` y el repositorio hace `actualizar` con columnas explícitas, por lo que las columnas nuevas no se filtran al arreglo `datos` editable; se verifica con prueba de regresión de `ActualizarFichaTest`.

### D2. Control de concurrencia
- **Decisión**: `UPDATE … WHERE id=? AND tenant_id=? AND version=?` (filas) y `… movilidad_version=?` (movilidad); 0 filas ⇒ 409 `REGISTRO_MODIFICADO`. La versión se compara primero; si coincide y no hay cambios reales ⇒ 200 sin escribir.
- **Rationale**: mismo patrón que GP-01; sin bloqueos largos; SQLite serializa escritores.
- **Alternativas**: ETag/If-Match (nuevo patrón); bloqueo pesimista (innecesario).

### D3. Validación de fechas y «hoy»
- **Decisión**: `ReglasDeDesarrollo::errores(array $resultante, string $hoy, string $tipo)` en Domain, sin Laravel; `$hoy` viene de `ContextoDeTenant::hoy()` (zona del tenant). Comparación de cadenas `Y-m-d` tras validar fecha real.
- **Rationale**: mismo mecanismo que la fecha de nacimiento de GP-01; una sola fuente del «hoy»; probable con reloj fijado (Carbon en `ContextoDeTenant` ya soportado por las pruebas).
- **PATCH parcial**: se reconstruye el estado final (actual + cambios) y se valida completo (FR-015).

### D4. Defensa en profundidad en base de datos
- **Decisión**: triggers SQLite `BEFORE INSERT/UPDATE` para valores de `estado`/`movilidad_estado` y `fecha_fin >= fecha_inicio` (misma técnica que las features previas con intervalos); las reglas de «hoy» solo en aplicación (dependen del tenant).
- **Alternativas**: CHECK con `ALTER TABLE` (SQLite no lo permite tras crear); solo aplicación (menos robusto).

### D5. Orden estable
- **Formación**: `fecha_inicio IS NULL`, `fecha_inicio DESC`, `id DESC` (nulos al final). **Experiencia**: `fecha_inicio DESC`, `id DESC`. Índices `(tenant_id, colaborador_id, …)`.

### D6. Aislamiento y 404 uniforme
- **Decisión**: cada consulta del repositorio filtra por `tenant_id` y `colaborador_id`; un `{id}` de fila inexistente, de otra ficha o de otro tenant lanza la misma `RegistroNoEncontrado` ⇒ 404 sin cuerpo distinguible. La ficha se resuelve primero con `ColaboradorRepositoryInterface::porId`.
- **Alternativas**: 403 (revela existencia).

### D7. Lectura propia
- **Decisión**: `ObtenerMiDesarrollo` reutiliza `AccesosDeCuentaPort` (asociación + `puedeConsultarFicha`), igual que `ObtenerMiFicha`; no acepta identificador; 403 `CUENTA_SIN_FICHA`/`SIN_PERMISO_FICHA` con los mensajes de GP-01. Un colaborador que pida `/api/colaboradores/{id}/desarrollo` recibe 403 por el middleware `admin` sin revelar existencia.
- **Escrituras del colaborador**: rutas solo bajo `admin`; nunca existen rutas de escritura propias.

### D8. Capacidad `desarrollo` y composición con GP-02
- **Integración realizada**: se incorporó `origin/develop` con GP-02 y DNF en la rama de GP-03.
- **Decisión**: `LectorDeCapacidadesDesarrollo` habilita Desarrollo y delega las demás capacidades a `LectorDeCapacidadesGp02`, que conserva el interruptor `hrms.gestion_personal.ingresos_habilitados`.
- **Cableado**: se conservan los lectores reales laborales y de Estructura, las rutas de ingresos y trayectoria, y las nuevas rutas de Desarrollo y consulta propia.
- **Verificación**: la creación de fichas anuncia ambas capacidades; deshabilitar ingresos mantiene Desarrollo disponible. Se reutiliza el auxiliar compartido `hoyDelTenant` para evitar duplicaciones entre las suites GP-02 y GP-03.

### D9. Lector por lote para GP-06
- **Decisión**: `LectorDeDesarrolloPort::deColaboradores(int $tenantId, array $ids): Lectura` devuelve `[id => {formaciones[], experiencias[], movilidad}]` con tres consultas `whereIn`, expuesto vía `PersonalFacade` (`getDesarrollo`) y devolviendo `Lectura` (disponible / noDisponible). Instalado ⇒ nunca `noInstalado`.
- **Rationale**: sin N+1; GP-06 no accede a tablas del módulo.

### D10. Idempotencia de altas
- **Decisión**: sin `Idempotency-Key` en POST de filas (no hay identidad natural; spec permite filas idénticas). Mitigación: botón bloqueado, `useMutation` sin reintento, y en fallo de red el diálogo conserva lo escrito y pide revisar la lista antes de repetir. Prueba Vitest de que un doble clic emite una sola petición.

### D11. Auditoría y registro
- **Decisión**: `created_by/updated_by` (id de usuario) y marcas de tiempo por fila; `movilidad_actualizada_por/at` para el bloque. Sin tabla de historial (fuera de alcance). Los logs registran código de error, `tenant_id` e ids, nunca textos de formación u observaciones.

### D12. Escritura deshabilitada
- Se reutiliza `config('hrms.gestion_personal.escritura_habilitada')` que ya gobierna GP-01 (interruptor de emergencia): al estar apagado, los tres POST/PATCH responden como `EscrituraDeshabilitada`.

### D13. Frontend: ubicación y desacoplamiento
- **Decisión**: carpeta propia `desarrollo/` dentro de `gestionPersonal`; `FichaContenido` la importa por su barril público (misma área de módulo). La pestaña consulta su endpoint al activarse (`enabled` al montar el panel), con error independiente.
- **Diálogos** en lugar de páginas: lo muestra el prototipo; foco atrapado y devuelto al disparador (WCAG).

### D14. Pruebas de concurrencia reales
- Dos procesos PHP (como en GP-02) para: doble PATCH de la misma fila (uno 409), PATCH movilidad vs PATCH ficha (ambos aplican), dos POST simultáneos (ambos 201).
