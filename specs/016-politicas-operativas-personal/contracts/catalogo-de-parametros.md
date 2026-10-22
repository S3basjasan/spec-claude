# Contrato: Catálogo de tipos, parámetros y reglas fijas

Definido en el servidor (`CatalogoDePoliticas`). El detalle de cada tipo expone `definicion` con esta información para que la interfaz pinte formularios sin lógica propia. Nada de lo que no esté aquí se admite.

## Tipos

| Tipo (valor) | Identificador en rutas | Categoría |
|---|---|---|
| `Permisos` | `permisos` | Novedad (con anticipación) |
| `Ausencias` | `ausencias` | Novedad (sin anticipación) |
| `Licencias` | `licencias` | Novedad (con anticipación) |
| `Vacaciones` | `vacaciones` | Novedad (con anticipación) |
| `Ascensos` | `ascensos` | Movimiento |
| `Traslados` | `traslados` | Movimiento |
| `Offboarding` | `offboarding` | Cierre administrativo |

## Parámetros por categoría

Las claves se validan de forma estricta: una clave desconocida produce 422 con `errors.parametros.<clave> = "PARAMETRO_NO_ADMITIDO"`. Los valores no tienen valor por defecto de negocio: el borrador nuevo comienza con los obligatorios vacíos y la interfaz no asigna valores supuestos.

### Novedades (Permisos, Ausencias, Licencias, Vacaciones)

| Clave | Tipo | Reglas | Aplica a |
|---|---|---|---|
| `disponible` | booleano | obligatorio | los cuatro |
| `justificacion_obligatoria` | booleano | obligatorio | los cuatro |
| `documento_obligatorio` | booleano | obligatorio | los cuatro |
| `anticipacion_dias` | entero | obligatorio; ≥ 0; sin decimales; **no admitido en Ausencias** | Permisos, Licencias, Vacaciones |

Unidad: días calendario enteros, contados entre la fecha de presentación y la fecha inicial solicitada en la zona horaria de la organización. 0 permite iniciar el mismo día; no autoriza fechas pasadas.

### Movimientos (Ascensos, Traslados)

| Clave | Tipo | Reglas |
|---|---|---|
| `disponible` | booleano | obligatorio (iniciar nuevos movimientos) |
| `anticipacion_dias` | entero | obligatorio; ≥ 0; respecto de la fecha efectiva |
| `documento_adicional_obligatorio` | booleano | obligatorio |

### Offboarding

| Clave | Tipo | Reglas |
|---|---|---|
| `tareas` | lista de 0 a 30 | cada elemento: `nombre` (obligatorio, trimmed, ≤ 150), `descripcion` (opcional, ≤ 500), `exige_evidencia` (booleano, obligatorio); el orden es el de la lista; nombres únicos sin distinguir mayúsculas ni tildes |

Offboarding no tiene `disponible`: no existe un modo «sin nuevas solicitudes» (la salida siempre puede registrarse). La clave `tareas` es obligatoria al publicar (debe enviarse explícitamente); una lista vacía es válida.

## Textos y límites (FR-024e)

Descripción 1000; motivo del cambio 500; justificación de retiro 500; nombre del revisor 150; observaciones de la revisión 1000; todos se recortan de espacios y se rechazan si exceden. Se tratan siempre como texto (se escapan al mostrar); el catálogo no interpreta su contenido.

## Reglas fijas (solo lectura, selladas en `condiciones.reglas_fijas`)

| Clave | Aplica a | Texto |
|---|---|---|
| `NOV-RESUELVE-ADMIN` | Novedades | El administrador de Talento Humano resuelve las solicitudes que requieren aprobación. |
| `NOV-REGLAS-TEMPORALES` | Novedades | Las reglas temporales de cada novedad y los saldos de vacaciones se definen en el proceso de novedades. |
| `NOV-DOCUMENTO-SIN-ACCESO` | Novedades | Exigir un documento no concede acceso documental a otras personas. |
| `MOV-JUSTIFICACION` | Movimientos | La justificación del movimiento es siempre obligatoria. |
| `MOV-EVALUACION` | Movimientos | Antes de aprobar debe existir una evaluación del colaborador contra los requisitos del puesto destino. |
| `MOV-DESTINO` | Movimientos | La posición destino debe existir y cumplir las condiciones de asignación al aplicar el movimiento. |
| `MOV-UNA-POSICION` | Movimientos | Un colaborador no puede tener más de una posición vigente. |
| `OFF-MOTIVO-FECHA` | Offboarding | El motivo y la fecha efectiva de salida son siempre obligatorios. |
| `OFF-SIN-RETROACTIVAS` | Offboarding | No se autorizan salidas retroactivas. |
| `OFF-SIN-RETENCION` | Offboarding | Las tareas pendientes no prolongan el vínculo, no mantienen ocupada la posición ni impiden registrar la salida. |
| `OFF-RESPONSABLES` | Offboarding | Los responsables se asignan dentro del proceso de salida; la plantilla no otorga permisos. |
| `COM-AISLAMIENTO` | Todos | Ninguna política concede acceso entre organizaciones ni permisos que la persona no tenga. |

Un cuerpo que intente fijar una regla fija (por ejemplo `evaluacion_obligatoria: false` o `justificacion_movimiento: false`) se rechaza con 422 `errors.parametros.<clave> = "REGLA_FIJA"` y el texto de la regla, no como un parámetro desconocido genérico.

## Fuera de catálogo (nunca se admite)

Fórmulas o expresiones; cadenas o selección de aprobadores; sustitución del administrador por el líder; saldos, acumulación o descuento de vacaciones; autorización de fechas pasadas; liquidaciones o cálculos financieros; salidas retroactivas; prolongación del vínculo; permisos por persona; porcentajes mínimos de evaluación o ausencia de brechas; destinos o niveles; excepciones por texto libre; políticas por unidad, puesto, antigüedad o colaborador.
