# Contrato HTTP: Rutas y planes de carrera

Todas las rutas exigen `auth:sanctum` (401 sin sesión) y el middleware `permiso:<clave>` (403). Las rutas fijas (`permisos`, `opciones`, `mi-ruta`) se declaran **antes** de `/{id}`. Errores: 404 recurso inexistente, 409 conflicto de versión, 422 validación `{ message, errors }`. No existen endpoints DELETE.

## Claves de permiso

| Clave | Admin | Colaborador |
|---|---|---|
| `desarrollo.rutas.ver` | sí | no |
| `desarrollo.rutas.gestionar` | sí | no |
| `desarrollo.rutas.inscribir` | sí | no |
| `desarrollo.rutas.inscripciones` | sí | no |
| `desarrollo.rutas.historial` | sí | no |
| `desarrollo.rutas.ver_propio` | no aplica | sí |

## Rutas

| Operación | Endpoint | Permiso |
|---|---|---|
| Permisos del usuario | `GET /api/rutas/permisos` | (solo autenticación) |
| Opciones del editor | `GET /api/rutas/opciones` | `gestionar` |
| Mi ruta | `GET /api/rutas/mi-ruta` | `ver_propio` |
| Listar | `GET /api/rutas` | `ver` |
| Consultar | `GET /api/rutas/{id}` | `ver` |
| Crear | `POST /api/rutas` | `gestionar` |
| Actualizar | `PUT /api/rutas/{id}` | `gestionar` |
| Cambiar estado | `PATCH /api/rutas/{id}/estado` | `gestionar` |
| Historial | `GET /api/rutas/{id}/historial` | `historial` |
| Listar inscripciones | `GET /api/inscripciones-ruta` | `inscripciones` |
| Buscar colaboradores inscribibles | `GET /api/inscripciones-ruta/colaboradores` | `inscribir` |
| Inscribir | `POST /api/inscripciones-ruta` | `inscribir` |
| Cambiar niveles | `PATCH /api/inscripciones-ruta/{id}/niveles` | `inscribir` |
| Registrar nivel alcanzado | `PATCH /api/inscripciones-ruta/{id}/alcanzar` | `inscribir` |
| Retirar | `PATCH /api/inscripciones-ruta/{id}/retirar` | `inscribir` |

## Detalle

### GET /api/rutas/permisos
`200 { data: { permisos: string[] } }`.

### GET /api/rutas/opciones
`200 { data: { tipos: [{valor, nombre}], departamentos: [{id, nombre}], puestos: [{id, codigo, nombre}] } }` (puestos y departamentos **activos**).

### GET /api/rutas
Filtros: `tipo`, `estado`, `departamento_id`, `q` (nombre), `page`, `per_page`. `200 { data: [{ id, codigo, nombre, tipo, estado, departamento_id, departamento, niveles_count, inscritos_count }], meta }`. Con un permiso `ver` sin `gestionar` solo se devuelven rutas Vigentes.

### GET /api/rutas/{id}
Dispara la conciliación de bajas (R5). `200 { data: { id, codigo, nombre, tipo, descripcion, estado, departamento_id, departamento, version, creada_por, creada_at, modificada_por, modificada_at, niveles: [Nivel], sin_nivel: n } }` con

```text
Nivel = { id, orden, puesto_id, puesto: {codigo, nombre, activo}, nombre, experiencia_minima_anios,
          en_uso: bool, inscritos_activos: n,
          competencias: [{ competencia_id, codigo, nombre, tipo, grado, diferencial: { tipo: 'nuevo'|'sube'|'baja'|'igual', antes?, delta? } }],
          competencias_quitadas: [{ competencia_id, codigo, nombre, grado_anterior }],
          conocimientos: { informativo: true, formacion: [string], capacitacion: [string] },
          experiencia: { anios_minimos, diferencial_anios|null, requisitos: [string] } }
```
`conocimientos` es **informativo**: texto de los requisitos de Formación y Capacitación del puesto, sin grado, sin comparación y sin diferencial (R3). Con la bandera `desarrollo.conocimientos_con_grado = false` (única rama implementada) esta es toda la forma; la rama `true` no existe en este contrato. Solo `competencias` y `experiencia.diferencial_anios` traen diferencial.
`competencias_quitadas` lista las competencias que tenía el nivel anterior y este ya no pide (vacío en el nivel 1). El nivel 1 devuelve el diferencial de competencias y de experiencia nulo. `puesto.activo = false` indica un puesto desactivado (la interfaz muestra una advertencia y la ruta no puede volver a publicarse). La respuesta incluye `fuentes_no_disponibles: []` (con `["estructura"]` si Estructura falla, y entonces los niveles traen `competencias: []`, `requisitos` vacíos y `puesto.activo = null`). 404 si no existe.

### POST /api/rutas
Body `{ nombre, tipo, descripcion?, departamento_id?, niveles?: [{ puesto_id, nombre?, experiencia_minima_anios? }] }`. `201 { data: Ruta }` en Borrador. 422: nombre vacío o duplicado (normalizado); `departamento_id` con tipo Interáreas o inexistente/inactivo; puestos repetidos, inexistentes o inactivos.

### PUT /api/rutas/{id}
Body `{ version, nombre, tipo, descripcion?, departamento_id?, niveles: [{ id?, orden, puesto_id, nombre, experiencia_minima_anios? }] }`. Reemplaza datos y niveles en una transacción conservando el `id` de los niveles existentes (R6) y registra historial por cada cambio. 409 si `version` no coincide. 422: validaciones anteriores, `orden` no consecutivo desde 1, quitar un nivel referenciado por una inscripción, cambiar el puesto de un nivel en uso, o un reorden que deje un objetivo Activo igual o anterior a su nivel actual. `200 { data: Ruta }`.

### PATCH /api/rutas/{id}/estado
Body `{ version, estado: 'Vigente'|'Archivada', confirmar_inscritos?: bool }`. Transiciones: Borrador→Vigente, Archivada→Vigente (≥ 2 niveles y todos con puesto activo), Vigente→Archivada. Si se archiva con inscripciones Activas y falta `confirmar_inscritos: true`, responde `422 { code: "requiere_confirmacion", inscritos: n }`. `200 { data: Ruta }`.

### GET /api/rutas/{id}/historial
`page`, `per_page`. `200 { data: [{ id, tipo, accion, inscripcion_id, valor_anterior, valor_nuevo, motivo, usuario, created_at }], meta }` (más reciente primero; usuario nulo = «Sistema»).

### GET /api/inscripciones-ruta
Dispara la conciliación de bajas. Filtros: `ruta_id`, `colaborador_id`, `estado`, `page`, `per_page`. `200 { data: [Inscripcion], meta }`, con
`Inscripcion = { id, ruta_id, colaborador: {id, codigo, nombre, departamento?}, nivel_actual: {id, orden, nombre}|null, nivel_objetivo: {…}|null, estado, motivo, created_at, resuelta_at }`.

### GET /api/inscripciones-ruta/colaboradores
`q`, `page`, `per_page`. Solo colaboradores con vínculo laboral vigente. `200 { data: [{ id, codigo, nombre }], meta }`.

### POST /api/inscripciones-ruta
Body `{ ruta_id, colaborador_id, nivel_actual_id?, nivel_objetivo_id? }`. `201 { data: Inscripcion }`. 404 ruta o colaborador inexistente. 422: ruta no Vigente, colaborador no activo, ya existe una Activa en esa ruta, niveles de otra ruta, objetivo no posterior al actual.

### PATCH /api/inscripciones-ruta/{id}/niveles
Body `{ nivel_actual_id, nivel_objetivo_id }` (nulos permitidos). Solo Activas y rutas no Archivadas. 422 con las mismas reglas de niveles.

### PATCH /api/inscripciones-ruta/{id}/alcanzar
Sin body. Mueve actual := objetivo; Finalizada si era el último nivel. `200 { data: Inscripcion }`. 422: sin objetivo o no Activa. La respuesta incluye `nota: "Informativo: el cambio real de puesto lo ejecuta Gestión de Personal."`.

### PATCH /api/inscripciones-ruta/{id}/retirar
Body `{ motivo }` (1–500). 422 sin motivo o no Activa.

### GET /api/rutas/mi-ruta
Dispara la conciliación de bajas. Solo datos del usuario del token (cuenta → colaborador por `PersonalFacade::getCuentaDeUsuario`).
`200 { data: { inscripciones: [{ inscripcion_id, ruta: {id, codigo, nombre, tipo}, niveles: [Nivel sin inscritos], nivel_actual_id|null, nivel_objetivo_id|null, siguiente_nivel_id|null, puede_ver_adecuacion: bool }], rutas_disponibles: [{id, codigo, nombre, tipo, niveles_count}], aviso: string } }`. Sin inscripciones → `inscripciones: []`. Un usuario sin ficha de colaborador recibe también la respuesta vacía.
