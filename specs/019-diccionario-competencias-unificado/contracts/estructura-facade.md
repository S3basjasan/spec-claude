# Contrato: Facade de Estructura (competencias)

Los demás módulos acceden al diccionario **solo** a través de `EstructuraFacade`. Esta feature **conserva las firmas** de 001–003 y añade campos; ningún consumidor existente necesita cambiar.

## Funciones

| Función | Resultado |
|---|---|
| `getCompetencia(int $id)` | Competencia con sus grados, comportamientos y preguntas, activa o inactiva (para históricos). `null` si no existe. |
| `getCompetenciasPorIds(array $ids)` | Las competencias pedidas, activas o inactivas. |
| `existeCompetencia(int $id)` | `true` si existe. |
| `estaActiva(int $id)` | `true` si existe y está `Activo`. |
| `getCompetenciasActivas()` | Competencias activas, para selectores de asignaciones nuevas. |
| `getTodasLasCompetencias()` | Todas, activas e inactivas, sin grados y ordenadas por nombre (vistas de gestión). |
| `getComportamientos(int $competenciaId)` | Comportamientos agrupados por grado, o `null` si la competencia no existe o no tiene ninguno. |
| `getComportamientosPorCompetencias(array $ids)` | Lo mismo para varias competencias. |
| `tieneComportamientosActivos(int $competenciaId)` | `true` si la competencia está activa **y completa**. |
| `getComportamientosActivos()` | Comportamientos de las competencias activas y completas. |
| `getPreguntas(int $competenciaId)` | Las cuatro preguntas, o `null` si no existe o no tiene ninguna. |
| `getPreguntasPorCompetencias(array $ids)` | Lo mismo para varias competencias. |
| `tienePreguntasActivas(int $competenciaId)` | `true` si la competencia está activa **y completa**. |
| `getPreguntasActivas()` | Preguntas de las competencias activas y completas. |

## Forma de una competencia

Se conservan las claves actuales y se añaden las nuevas:

| Clave | Estado | Descripción |
|---|---|---|
| `id`, `codigo`, `nombre`, `descripcion`, `tipo`, `estado` | conservadas | |
| `grados` | conservada | Mapa por posición `A`–`D` → descripción (A = orden 1). Es la clave posicional estable; no depende de la escala |
| `escala` | **nueva** | `{ "tipo_grado_id", "nombre" }` |
| `grados_detalle` | **nueva** | Lista ordenada de `{ "id", "orden", "clave", "etiqueta", "descripcion", "comportamientos" }`; `id` es el `competencia_grado_id` y `clave` la letra A–D |
| `completa` | **nueva** | `true` si tiene los 4 grados, 16 comportamientos y 4 preguntas |

## Asignaciones de puestos

- `puesto_competencias` guarda `competencia_grado_id`. El grado requerido que devuelven la Facade y el API de puestos sigue siendo `grado_requerido` = clave posicional A–D; además se devuelve `grado_etiqueta` (el nombre con la escala vigente).
- Para una asignación nueva, quien asigna **debe** comprobar con la Facade que la competencia está activa y completa (`estaActiva` y `completa`) y que el grado indicado pertenece a esa competencia.
- Cambiar la escala de una competencia no modifica ninguna asignación: la fila de grado no cambia, solo su nombre.
- Una competencia inactiva se conserva y se puede consultar con `getCompetencia`; las asignaciones existentes no se alteran.

## Garantías

- Solo lectura: la Facade no crea ni modifica competencias.
- Nunca devuelve datos personales ni identificadores internos de otros módulos.
- Compatibilidad: si una competencia es incompleta, `getComportamientos` y `getPreguntas` devuelven lo que exista y `completa` es `false`; los selectores para asignaciones nuevas (`getCompetenciasActivas`) solo incluyen competencias activas, que por la regla de activación siempre son completas.
