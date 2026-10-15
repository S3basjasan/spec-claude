# Data Model: Unidades organizacionales

Revisión 2026-10-15. Basado en `estructura_empresarial.dbml` y en lo que ya existe en el repositorio. Las marcas **(plan)** indican lo que decide este plan por encima del DBML.

## Tipo de unidad (`tipos_unidad`) — gestionado por este módulo

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| nombre | varchar(50) único | Obligatorio; único sin distinguir mayúsculas ni tildes (la normalización se valida en la aplicación) |
| nivel | tinyint único | 1 = tipo raíz; continuo 1..N sin huecos **(plan: se añade a la migración existente)** |
| created_by, updated_by | bigint, nulos | Sin FK a `users` |
| created_at, updated_at | timestamp | |

Los tipos nunca se eliminan. Relación: `TipoUnidad` 1—N `Unidad` (solo lectura).

## Unidad organizacional (`unidades_organizacionales`)

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| codigo | varchar(20) único, anulable | `UO-` + id (≥ 3 dígitos), asignado en la transacción de creación; inmutable |
| nombre | varchar(150) | Obligatorio; no único |
| tipo_unidad_id | FK → tipos_unidad, restrict | **Calculado** al crear (nivel de la padre + 1); no cambia |
| unidad_padre_id | FK → sí misma, restrict, nulo | Nulo solo en la raíz |
| estado | varchar(10), default `Activa` | `Activa` \| `Inactiva` |
| fecha_creacion | date | Fecha del servidor al crear |
| fecha_supresion | date, nulo | Fecha del servidor al desactivar; nulo al reactivar |
| version | int, default 1 | **(plan)** control de edición simultánea; solo sube con ediciones de contenido |
| created_by, updated_by | bigint, nulos | Sin FK a `users` |
| created_at, updated_at | timestamp | |

Índices: `tipo_unidad_id`, `unidad_padre_id`, `estado` y el único parcial `unidades_una_raiz_activa` (**plan**). Todas las FK `restrictOnDelete`; sin soft delete.

### Datos calculados (no se almacenan)

- `nivel_arbol` (0 en la raíz), `tiene_dependientes`, `dependientes_activas` (rama activa: directas e indirectas), `posiciones_vigentes` (Ocupada + Vacante asignadas a la unidad), `admite_hijas` (el tipo no es del último nivel) y `sigue_cadena` (nivel del tipo = nivel del tipo de la padre + 1).

## Posición (`posiciones`) — solo lectura para este módulo

Creada en 004 (`id`, `puesto_id`, `estado`) y ampliada con `unidad_organizacional_id` (FK restrict, **nullable por ahora**; el DBML la define obligatoria y lo será cuando exista la feature de posiciones). Estados: `Ocupada` \| `Vacante` \| `Suprimida`.

## Registro de cambios (`auditoria_cambios`, creada en 004)

| Entidad | `entidad_id` | `campo` |
|---|---|---|
| `unidad` | id de la unidad | `nombre`, `tipo_unidad_id`, `unidad_padre_id`, `estado`, `fecha_supresion` |
| `tipo_unidad` | id del tipo | `nombre`, `nivel` |

Con `valor_anterior`, `valor_nuevo`, `user_id` y `created_at`. Referencias como id; en la creación, valor anterior nulo; solo inserción. Cascada: una pareja `estado` + `fecha_supresion` por unidad. Reordenamiento: una fila por cada nombre y nivel modificados.

## Invariantes (los mantiene la aplicación; la raíz única, además, la base)

1. Existe exactamente una raíz (`unidad_padre_id` nulo), de tipo nivel 1, siempre `Activa`, sembrada.
2. Niveles de tipo continuos 1..N, únicos; el tipo raíz siempre en 1; nombres únicos.
3. Al crear o reubicar: nivel del tipo de la unidad = nivel del tipo de la padre + 1. Tras un reordenamiento ninguna unidad queda bajo una padre de nivel ≥ al suyo; pueden quedar saltos de nivel (`sigue_cadena = false`).
4. `estado = Inactiva` ⇔ `fecha_supresion` no nula.
5. Unidad activa ⇒ todas sus ancestras activas.
6. Sin ciclos.
7. Ni unidades ni tipos se eliminan.

## Transiciones

- **Crear unidad**: `Activa`, `version` 1, fecha de creación del día, tipo calculado.
- **Editar**: cambia `nombre` y/o `unidad_padre_id` (reubica la rama); sube `version` solo con cambios reales.
- **Desactivar**: la unidad y su rama activa pasan a `Inactiva` con `fecha_supresion` del día (todo o nada); no cambian `version` ni `updated_by`.
- **Reactivar**: solo con padre activa; `Activa`, `fecha_supresion` nula; no reactiva dependientes.
- **Tipos**: crear (nivel = máximo + 1), renombrar y reordenar (dos pasos por la unicidad de `nivel`).

## Reglas de visibilidad

- Listado: Administrador todas (filtro de estado); Usuario solo activas. Detalle: cualquiera, también inactivas. Auditoría (quién creó y modificó): solo Administrador. Cadena de tipos: todos.

## Datos iniciales (`TiposUnidadSeeder` y `UnidadesSeeder`)

- Tipos: Gerencia General (1), Gerencia (2), Área (3), Departamento (4), Sección (5).
- 12 unidades en 5 niveles; UO-008 (Departamento de Capacitación) inactiva con `fecha_supresion`; posiciones sembradas según research D-18.
