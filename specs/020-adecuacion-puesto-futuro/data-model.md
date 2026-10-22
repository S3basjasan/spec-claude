# Data Model: Adecuación a puesto futuro y mapa de talento

Todas las tablas llevan `tenant_id`, sin soft deletes y sin `cascade`. Las referencias a Estructura y Gestión de Personal son **lógicas** (sin FK entre módulos). Las FK internas son solo hacia tablas de `Desarrollo`.

## adecuaciones (inmutable)
| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| tenant_id | bigint | |
| codigo | string(12) | `ADE-0001`, único por tenant, no editable |
| colaborador_id | bigint | referencia lógica a Personal |
| puesto_actual_id | bigint, nullable | copia del momento; null si no se pudo resolver (R6) |
| puesto_objetivo_id | bigint | referencia lógica a Estructura |
| inscripcion_id | bigint, nullable | FK a `ruta_inscripciones` |
| nivel_objetivo_id | bigint, nullable | FK a `ruta_niveles` |
| experiencia_requerida_anios | decimal(4,1), nullable | de la ruta o ingresada; null ⇒ Experiencia no se evalúa |
| preparacion | enum | `Lista`, `EnDesarrollo`, `NoLista`, `Incompleta` |
| umbral_meses | unsignedSmallInteger | copia del parámetro vigente |
| observaciones | string(500), nullable | |
| created_by | bigint | usuario que generó |
| created_at, updated_at | timestamps | `updated_at` no se modifica nunca |

Índices: `(tenant_id, codigo)` único; `(tenant_id, colaborador_id, puesto_objetivo_id, created_at)` (vigente); `(tenant_id, preparacion)`; `(tenant_id, created_at)`.

**Reglas**: no hay UPDATE ni DELETE. La adecuación vigente de `(colaborador, puesto objetivo)` es la de mayor `created_at` (desempate por `id`).

## adecuacion_detalles (inmutable)
| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| adecuacion_id | bigint FK | sin cascade |
| dimension | enum | `Competencia`, `Conocimiento`, `Experiencia` |
| tipo_elemento | enum, nullable | `Competencia`, `Conocimiento`; nulo en Experiencia |
| elemento_id | bigint, nullable | referencia lógica a Estructura; nulo en Experiencia |
| nombre_elemento | string(150), nullable | copia del momento, para el historial sin releer Estructura |
| requerido_actual_nivel | char(1), nullable | A–D |
| requerido_objetivo_nivel | char(1), nullable | A–D |
| valor_nivel | char(1), nullable | A–D (valor de la persona) |
| requerido_actual_anios | decimal(4,1), nullable | |
| requerido_objetivo_anios | decimal(4,1), nullable | |
| valor_anios | decimal(4,1), nullable | |
| diferencia | decimal(5,1), nullable | grados o años; nulo si Sin evaluación |
| estado | enum | `ConBrecha`, `Cumple`, `Supera`, `SinEvaluacion` |
| tiempo_cierre_meses | unsignedSmallInteger, nullable | obligatorio (≥1) si `estado = ConBrecha`; nulo en los demás |
| created_at | timestamp | |

Restricción única: `(adecuacion_id, dimension, tipo_elemento, elemento_id)`. Índices: `(adecuacion_id)`.

**Validaciones de dominio**: `ConBrecha` ⇒ `diferencia < 0` y `tiempo_cierre_meses ≥ 1`; `Cumple` ⇒ `diferencia = 0`; `Supera` ⇒ `diferencia > 0`; `SinEvaluacion` ⇒ `diferencia`, y tiempo nulos. Los niveles son `A|B|C|D`. Una adecuación debe tener al menos un resultado; si no hay requisitos evaluables no se guarda (422 `sin_requisitos_evaluables`).

## adecuacion_parametros (una fila vigente por tenant)
| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| tenant_id | bigint | único |
| peso_competencias | unsignedTinyInteger | 0–100 |
| peso_conocimientos | unsignedTinyInteger | 0–100 |
| peso_experiencia | unsignedTinyInteger | 0–100; los tres suman 100 |
| umbral_meses | unsignedSmallInteger | ≥ 1; inicial 12 |
| updated_by | bigint, nullable | |
| timestamps | | |

Valores iniciales: 40 / 30 / 30 y 12 (los siembra el seeder y los crea de forma perezosa el repositorio si no existen).

## adecuacion_parametros_historial (solo inserción)
| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| tenant_id | bigint | |
| campo | string(30) | `peso_competencias`, `peso_conocimientos`, `peso_experiencia`, `umbral_meses` |
| valor_anterior / valor_nuevo | string(20) | |
| user_id | bigint | |
| created_at | timestamp | |

Un cambio válido que modifica varios campos inserta una fila por cada campo cambiado, en una sola transacción con la actualización.

## Entidades derivadas (no se persisten)
- **Diagnóstico (previsualización)**: cabecera + resultados calculados + `avisos` (`sin_evaluacion`, `inactivos_omitidos`, `conocimientos_desactivados`, `experiencia_no_evaluada`) + preparación provisional (Incompleta/según brechas aún sin tiempo).
- **Cumplimiento** por dimensión y global (derivado de detalles): `min(1, valor ÷ requerido)` promediado.
- **Fila del mapa**: adecuación vigente + `brechas` (conteo `ConBrecha`) + `tiempo_maximo_cierre_meses` (máx. de `tiempo_cierre_meses`, nulo sin brechas) + nombres de colaborador/puestos/departamento/ruta resueltos por Facade.
- **Ranking**: lista ordenada por puntaje (descendente; desempate por menos brechas y luego por nombre) + `incompletos` + `sin_adecuacion`.

## Transiciones y ciclo de vida
Una adecuación nace al confirmar y no cambia. Los parámetros cambian por `PUT`; cada cambio genera historial y rige hacia adelante (no recalcula adecuaciones guardadas).

## Relaciones externas (solo lectura, por Facade/puerto)
Estructura: puesto, perfil de competencias activas con grado, departamento. Personal: colaborador vigente, nombre, departamento, puesto actual (`getPuestoActual`, aditivo), experiencia (`getDesarrollo`). Rutas: `inscripcionesActivas`, `obtenerNivel`, ruta de la inscripción. DNF (mismo módulo): último nivel evaluado por competencia. Catálogo Formativo: navegación del colaborador (`/formacion?competencia=<id>`).
