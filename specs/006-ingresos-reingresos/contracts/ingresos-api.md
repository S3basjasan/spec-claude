# Contrato: API de ingresos y situación laboral

Base: `/api/colaboradores/{colaborador}` y `/api/mi-ficha`. JSON. Todas requieren `Authorization: Bearer <token>`
(sin token: 401 `{"message":"No autenticado."}`). Las rutas de `/api/colaboradores/{colaborador}/…` requieren además la
capacidad `admin` (sin ella: 403 `{"message":"No tienes permisos para realizar esta acción."}`, igual para toda ficha).
`{colaborador}` es el `id` numérico de la ficha (GP-01). **No existen** `PUT`, `PATCH` ni `DELETE` sobre ingresos: no hay
corrección, cancelación ni reprogramación.

**Errores con código**: `{ "message": "…", "code": "<CODIGO>", "errors": { "<campo>": ["…"] } }`; `errors` solo en 422.
Ficha inexistente o de otro tenant: 404 `{"message":"La ficha no existe.","code":"FICHA_NO_ENCONTRADA"}`.

## Representaciones

**Situación laboral**:

```json
{ "data": {
  "estado": "Activo",
  "vinculo_vigente": { "id": 7, "fecha_ingreso": "2025-10-15", "tipo_contrato": { "clave": "indefinido", "etiqueta": "Indefinido" },
                       "posicion": { "id": 12, "puesto": "Jefe de talento humano", "unidad": "Área de Talento", "jefatura": null } },
  "proximo_ingreso": null,
  "disponibilidad": "disponible"
} }
```

- `estado`: `Activo`, `Desvinculado` o `SinVinculoVigente`, calculado para la fecha de hoy del tenant.
- `proximo_ingreso`: `{ "fecha_ingreso", "tipo_contrato", "posicion": {…} , "programado": true }` o `null`.
- `disponibilidad`: `disponible` o `no_disponible` (Estructura no responde: `posicion.puesto`, `unidad` y `jefatura` en
  `null` y `estado`/fechas intactos).

**Hecho de trayectoria**: `{ "vinculo_id", "tipo": "Ingreso" | "Reingreso", "fecha_efectiva", "programado",
"tipo_contrato": {…}, "posicion": {…}, "registrado_en": "2026-10-15T10:00:00Z" }`, ordenados por `fecha_efectiva`
descendente. `registrado_en` es el momento del registro; no se expone el autor al Colaborador.

## POST /api/colaboradores/{colaborador}/ingresos

Cabecera obligatoria `Idempotency-Key: <uuid>`. Cuerpo: `fecha_ingreso` (`AAAA-MM-DD`), `tipo_contrato` (clave) y
`posicion_id`. Campos no declarados (`fecha_salida`, `estado`, `salario`, `tenant_id`, `vinculo_id`…) → 422
`CAMPO_NO_PERMITIDO`. Crea vínculo y asignación inicial en una transacción; `created_by` = actor, `created_at` =
momento del registro.

| Código | Cuándo | Cuerpo |
|---|---|---|
| 201 | Primera creación | `{ "data": { "vinculo": {…}, "asignacion": {…}, "situacion": <situación laboral> } }` |
| 200 | Repetición con la misma clave, cuerpo y actor | Igual que 201, con los datos ya creados |
| 404 | Ficha inexistente o de otro tenant | `FICHA_NO_ENCONTRADA` |
| 409 `CLAVE_DE_OPERACION_REUTILIZADA` | Misma clave con otros datos u otro actor | `message`, `code` |
| 422 `CLAVE_DE_OPERACION_REQUERIDA` | Falta o no es UUID | `message`, `code` |
| 422 | Validación de forma (fecha, contrato, posición) | `errors` por campo |
| 422 `CONTRATO_NO_HABILITADO` | La clave no está entre las 4 opciones | `errors.tipo_contrato` |
| 422 `POSICION_NO_DISPONIBLE` | Posición suprimida, puesto o unidad inactivos, de otro tenant, o con ocupación superpuesta `[fecha, ∞)` | `errors.posicion_id`, `message` con el motivo |
| 422 `VINCULO_SUPERPUESTO` | La ficha tiene un vínculo vigente o programado que se superpone con `[fecha, ∞)` | `errors.fecha_ingreso`, `message` con las fechas del vínculo en conflicto |
| 503 `ESTRUCTURA_NO_VERIFICABLE` | Estructura no pudo verificar la posición, o no se obtuvo el bloqueo tras los reintentos | `message`, `code` |
| 503 `INGRESOS_DESHABILITADOS` | `gestion_personal.ingresos_habilitados = false` (rollback operativo) | `message`, `code` |

Orden de evaluación: ficha → contrato → posición (existencia, tenant, estado, puesto, unidad) → superposición de la
ficha → superposición de la posición. Un rechazo no escribe nada.

## GET /api/colaboradores/{colaborador}/opciones-ingreso

Parámetros: `fecha` (`AAAA-MM-DD`, obligatoria), `q` (≤ 50; puesto o unidad, sin distinguir mayúsculas ni tildes),
`page`, `per_page` (1–50, por defecto 20). No reserva la posición.

```json
{ "data": { "tipos_contrato": [ { "clave": "indefinido", "etiqueta": "Indefinido" }, … ],
            "ingreso_programado": true,
            "conflicto_ficha": null,
            "posiciones": [ { "id": 12, "puesto": "Jefe de talento humano", "unidad": "Área de Talento", "jefatura": null } ] },
  "links": {…}, "meta": { "current_page", "per_page", "total", "last_page", "from", "to" } }
```

- `conflicto_ficha`: `null` o `{ "codigo": "VINCULO_SUPERPUESTO", "desde", "hasta" }` si la ficha no puede ingresar en esa
  fecha (el formulario lo avisa antes de confirmar).
- 503 `ESTRUCTURA_NO_VERIFICABLE` si Estructura no responde (nunca una lista vacía engañosa).

## GET /api/colaboradores/{colaborador}/situacion-laboral y GET /api/mi-ficha/situacion-laboral

200 con la **situación laboral**. La propia aplica los controles de `mi-ficha` de GP-01 (403 `CUENTA_SIN_FICHA` y
`SIN_PERMISO_FICHA`).

## GET /api/colaboradores/{colaborador}/trayectoria y GET /api/mi-ficha/trayectoria

200 `{ "data": [ <hecho>… ] }`, orden descendente por `fecha_efectiva`; `[]` sin hechos. Misma regla de acceso propio.

## Cambios en contratos de GP-01 (aditivos)

- `GET /api/colaboradores/{id}` y `GET /api/mi-ficha`: `laboral` añade `proximo_ingreso` y `estado` puede ser
  `Activo`, `Desvinculado` o `SinVinculoVigente`; `trayectoria.hechos[].tipo` pasa a `Ingreso` o `Reingreso`;
  `capacidades.registrar_ingreso` es `true` para el Administrador.
- `GET /api/colaboradores/filtros`: `estados` incluye los estados presentes (`Activo`, `Desvinculado`,
  `SinVinculoVigente`).
- `GET /api/colaboradores?estado=SinVinculoVigente` **excluye** a los desvinculados (cambio de significado, ver
  research D-09).

## Trazabilidad

| Criterio | Cubierto por |
|---|---|
| GP02-CA01, CA02, CA08 | `POST` 201; fechas efectiva y de registro; sin cuentas nuevas |
| GP02-CA03 | `situacion-laboral` antes y desde la fecha con reloj fijado del tenant |
| GP02-CA04 | `POST` 422 `POSICION_NO_DISPONIBLE` sin filas |
| GP02-CA05 | Dos procesos simultáneos: uno 201, otro 422 `POSICION_NO_DISPONIBLE` |
| GP02-CA06 | `POST` con vínculo previo cerrado; ficha y vínculo anterior intactos |
| GP02-CA07 | `POST` 422 `VINCULO_SUPERPUESTO` aunque cambie la posición |
