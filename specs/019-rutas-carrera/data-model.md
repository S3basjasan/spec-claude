# Data Model: Rutas y planes de carrera

Referencias a otros módulos (`puesto_id`, `departamento_id`, `colaborador_id`, `tenant_id`, `user_id`) son **lógicas, sin clave foránea**. Sin soft deletes, sin `cascade`, sin DELETE sobre rutas, inscripciones ni historial.

## Tablas

### rutas
| Campo | Tipo | Reglas |
|---|---|---|
| id | bigint PK | |
| tenant_id | bigint | índice |
| numero | unsignedInteger | secuencial por tenant, generado dentro de la transacción de creación; único `(tenant_id, numero)` |
| codigo | string(10) | `CAR-0001` generado desde `numero`, inmutable; único `(tenant_id, codigo)` |
| nombre | string(80) | obligatorio |
| nombre_clave | string(80) | único `(tenant_id, nombre_clave)`; nombre normalizado (trim, espacios colapsados, minúsculas) |
| tipo | string(20) | `Especialista` \| `Gerencial` \| `Interareas` \| `JovenesProfesionales` |
| descripcion | string(500) null | opcional |
| departamento_id | bigint null | ref lógica a Estructura (unidad); debe ser null si tipo = Interareas |
| estado | string(10) | `Borrador` (default) \| `Vigente` \| `Archivada`; índice |
| version | unsignedInteger | default 1; control de concurrencia (409) |
| created_by, updated_by | bigint null | usuario |
| timestamps | | |

### ruta_niveles
| Campo | Tipo | Reglas |
|---|---|---|
| id | bigint PK | |
| tenant_id | bigint | |
| ruta_id | FK rutas | sin cascade |
| orden | unsignedSmallInteger | consecutivo desde 1; único `(ruta_id, orden)` |
| puesto_id | bigint | ref lógica; obligatorio, activo al guardar; único `(ruta_id, puesto_id)` |
| nombre | string(80) | por defecto el nombre del puesto; editable |
| experiencia_minima_anios | decimal(4,1) null | ≥ 0, ≤ 60 |
| timestamps | | |

### ruta_inscripciones
| Campo | Tipo | Reglas |
|---|---|---|
| id | bigint PK | |
| tenant_id | bigint | |
| ruta_id | FK rutas | sin cascade |
| colaborador_id | bigint | ref lógica a Personal; debe estar activo al inscribir |
| nivel_actual_id | FK ruta_niveles null | pertenece a la ruta |
| nivel_objetivo_id | FK ruta_niveles null | pertenece a la ruta; orden mayor que el del actual |
| estado | string(10) | `Activa` \| `Finalizada` \| `Retirada`; índice |
| motivo | string(500) null | obligatorio al retirar |
| resuelta_by | bigint null | usuario (null = sistema, p. ej. baja) |
| resuelta_at | datetime null | |
| created_by | bigint null | |
| timestamps | | |

Índice único parcial `(ruta_id, colaborador_id)` donde `estado = 'Activa'` (SQLite lo admite). Índices `(tenant_id, colaborador_id)` y `(ruta_id, estado)`.

### ruta_historial
| Campo | Tipo | Reglas |
|---|---|---|
| id | bigint PK | |
| tenant_id | bigint | |
| ruta_id | FK rutas | |
| inscripcion_id | bigint null | solo eventos de inscripción |
| tipo | string(12) | `Ruta` \| `Nivel` \| `Inscripcion` |
| accion | string(30) | `creada`, `editada`, `estado_cambiado`, `nivel_agregado`, `nivel_editado`, `nivel_reordenado`, `nivel_quitado`, `inscrita`, `niveles_cambiados`, `nivel_alcanzado`, `retirada` |
| valor_anterior, valor_nuevo | json null | solo los campos que cambian |
| motivo | string(500) null | |
| user_id | bigint null | null = sistema |
| created_at | datetime | sin `updated_at`: inmutable |

El repositorio no ofrece actualizar ni borrar historial.

## Entidades de dominio y reglas

- **Ruta** (agregado raíz con sus niveles): crea en Borrador; `puedePublicarse()` exige ≥ 2 niveles y todos con puesto activo; transiciones válidas Borrador→Vigente, Vigente→Archivada, Archivada→Vigente (esta última con las mismas condiciones de publicación). Interáreas rechaza `departamento_id`.
- **NivelDeRuta**: `orden` consecutivo; `enUso` (derivado de inscripciones Activas) bloquea quitar y cambiar el puesto, no el nombre ni la experiencia.
- **InscripcionARuta**: crear exige ruta Vigente, colaborador activo y sin otra Activa en la ruta; `objetivo.orden > actual.orden` cuando ambos existen; `alcanzar()` mueve actual := objetivo, y si el objetivo era el último orden → Finalizada (`resuelta_at`), si no → objetivo = null; `retirar(motivo)` exige motivo; Finalizada y Retirada son terminales.
- **Diferencial / Cantera**: servicios de dominio puros sin persistencia (ver R9 y `contracts/rutas-api.md`). El diferencial se calcula solo sobre competencias (grado) y años de experiencia mínima; los requisitos de texto del puesto (Formación, Capacitación, Experiencia) se leen de Estructura y se muestran sin grado ni comparación. No existe ningún campo, tipo ni tabla de «conocimiento con grado» (R3).

## Transiciones de estado

```text
Ruta:         Borrador ──publicar──▶ Vigente ──archivar──▶ Archivada ──publicar──▶ Vigente
Inscripción:  Activa ──alcanzar (último nivel)──▶ Finalizada
              Activa ──retirar / baja──▶ Retirada
```

## Datos de ejemplo (RutasSeeder)

4 rutas sobre los puestos de `PuestosSeeder`: Especialista Vigente (4 niveles), Gerencial Vigente (4), Interáreas Borrador (2) y Jóvenes profesionales Archivada (3); experiencia mínima en al menos dos niveles; inscripciones Activas (con y sin nivel actual), Finalizada y Retirada sobre colaboradores de `PersonalSeeder`. Depende de ese orden en `DatabaseSeeder` (después de `ReportesSeeder`).
