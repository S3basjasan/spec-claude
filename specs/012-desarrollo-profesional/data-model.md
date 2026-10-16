# Data Model: GP-03 · Información de desarrollo profesional

Todas las tablas pertenecen a `GestionPersonal`. Aislamiento por `tenant_id`; sin eliminación (FK `restrictOnDelete`, sin SoftDeletes, sin rutas DELETE).

## `formaciones_academicas` (nueva)

| Columna | Tipo | Reglas |
|---|---|---|
| id | bigint PK | |
| tenant_id | unsignedBigInteger NOT NULL | igual que el de la ficha |
| colaborador_id | FK `colaboradores.id`, restrict | inmutable |
| nivel | varchar(100) NOT NULL | recortado, no vacío |
| titulo_programa | varchar(200) NOT NULL | |
| institucion | varchar(200) NOT NULL | |
| estado | varchar(12) NOT NULL | `EnCurso` \| `Completada` \| `Incompleta` |
| fecha_inicio | date NULL | no futura |
| fecha_finalizacion | date NULL | ≥ inicio; puede ser futura |
| observaciones | varchar(2000) NULL | |
| version | unsignedInteger default 1 | optimista |
| created_by / updated_by | unsignedBigInteger NOT NULL | usuario |
| created_at / updated_at | timestamps | `updated_at` solo cambia con cambios reales |

Índice: `(tenant_id, colaborador_id, fecha_inicio, id)`. Sin UNIQUE.
Triggers: estado en el conjunto; `fecha_finalizacion IS NULL OR fecha_inicio IS NULL OR fecha_finalizacion >= fecha_inicio`.

## `experiencias_anteriores` (nueva)

| Columna | Tipo | Reglas |
|---|---|---|
| id, tenant_id, colaborador_id, version, created_by/updated_by, timestamps | como arriba | |
| organizacion | varchar(200) NOT NULL | |
| cargo | varchar(200) NOT NULL | |
| fecha_inicio | date NOT NULL | no futura |
| fecha_finalizacion | date NULL | no futura; ≥ inicio; null = «No registrado», nunca «vigente» |
| funciones | varchar(2000) NULL | |
| observaciones | varchar(2000) NULL | |

Índice: `(tenant_id, colaborador_id, fecha_inicio, id)`. Sin UNIQUE; se permiten periodos superpuestos.
Trigger: `fecha_finalizacion IS NULL OR fecha_finalizacion >= fecha_inicio`.

## `colaboradores` (ampliación aditiva)

| Columna | Tipo | Reglas |
|---|---|---|
| movilidad_estado | varchar(14) NOT NULL default `NoInformada` | `Disponible` \| `NoDisponible` \| `NoInformada` (trigger) |
| movilidad_observaciones | varchar(2000) NULL | |
| movilidad_version | unsignedInteger default 1 | optimista, independiente de `version` |
| movilidad_actualizada_at | timestamp NULL | solo se asigna con cambio real; NULL en fichas existentes |
| movilidad_actualizada_por | unsignedBigInteger NULL | |

Las fichas existentes quedan en `NoInformada`, sin fecha ni observaciones. `ColaboradorModel`/`datos` de GP-01 no incluyen estas columnas (no son campos de la ficha editables por `PATCH /api/colaboradores/{id}`).

## Entidades de dominio

- `FormacionAcademica`, `ExperienciaAnterior`: inmutables, con `id`, `colaboradorId`, campos, `version`, auditoría.
- `Movilidad`: `estado`, `observaciones`, `version`, `actualizadaAt`, `actualizadaPor`.
- Value objects: `EstadoDeFormacion`, `EstadoDeMovilidad` (enums con etiqueta de presentación en frontend, no en dominio).

## Reglas de validación (`ReglasDeDesarrollo`)

1. Recortar espacios exteriores; vacío tras recortar = ausente; medir longitud después.
2. Obligatorios: formación → nivel, titulo_programa, institucion, estado; experiencia → organizacion, cargo, fecha_inicio.
3. Fechas reales `Y-m-d`; `fecha_inicio <= hoy` (ambos grupos); `fecha_finalizacion <= hoy` solo en experiencia; `fin >= inicio` si ambas.
4. El estado no deduce ni exige fechas.
5. Longitudes: 100/200/200/2000 (formación), 200/200/2000/2000 (experiencia), 2000 (movilidad).

## Transiciones y cambio efectivo

- Formación/experiencia: PATCH parcial → estado final = actual ∪ cambios → validar completo → comparar con actual; sin diferencias ⇒ 200 sin escritura ni cambio de `version`/`updated_at`.
- Movilidad: comparar `movilidad_version`; sin diferencias ⇒ 200 sin escritura; con diferencias ⇒ `movilidad_estado`, `observaciones`, `movilidad_actualizada_at/por` = ahora/actor, `movilidad_version`++. «Disponible» no escribe en ninguna otra tabla.
