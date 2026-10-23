# Data Model: Plan de desarrollo individual (PDI)

Cinco tablas nuevas, todas con `tenant_id`, sin soft deletes y sin `cascade`. Las referencias a otros módulos son **lógicas** (sin FK entre módulos). Las FK internas son `restrictOnDelete`. Convenciones de migración: las de `adecuaciones` y `rutas` (`2026_10_24_*`). Las migraciones nuevas continúan la numeración: `2026_10_25_000001…000005`.

## Tablas

### `pdis`
| Columna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| tenant_id | bigint, índice | |
| codigo | string(12) | `PDI-0001`, único por tenant, generado por el sistema, no editable |
| colaborador_id | bigint | referencia lógica a Gestión de Personal |
| tipo | string(8) | `Mejora \| Carrera \| Talento`; inmutable |
| objetivo | string(500) | obligatorio |
| puesto_objetivo_id | bigint null | referencia lógica a Estructura; obligatorio en Carrera y Talento, nulo en Mejora |
| origen_dnf_id | bigint null | referencia lógica a DNF |
| origen_adecuacion_id | bigint null | referencia lógica a Adecuación |
| responsable_id | bigint | referencia lógica a un usuario Administrador activo |
| fecha_inicio, fecha_fin | date | obligatorias; `fecha_fin ≥ fecha_inicio` |
| estado | string(8) | `Borrador \| Acordado \| Cerrado`, por defecto `Borrador` |
| version | unsigned int | control optimista (R9); inicia en 1 |
| acuerdo_responsable_at | datetime null | |
| acuerdo_responsable_by | bigint null | |
| aceptacion_colaborador_at | datetime null | |
| comentario_colaborador | string(1000) null | última devolución del colaborador |
| resultado | string(13) null | `BrechaCerrada \| Parcial \| NoCerrada`; se guarda al cerrar |
| conclusion | string(1000) null | |
| cerrado_at, cerrado_by | datetime null / bigint null | |
| created_by, updated_by | bigint | |
| timestamps | | |

Índices: único `(tenant_id, codigo)`; `(tenant_id, colaborador_id, estado)`; `(tenant_id, estado, fecha_fin)`; `(tenant_id, responsable_id)`; **único parcial** `(tenant_id, colaborador_id, tipo) WHERE estado <> 'Cerrado'` (R8).

### `pdi_compromisos`
| Columna | Tipo | Notas |
|---|---|---|
| id, tenant_id | | |
| pdi_id | FK → pdis | restrictOnDelete |
| orden | unsigned smallint | posición en el plan |
| dimension | string(12) | `Competencia \| Conocimiento \| Experiencia \| Meta` |
| tipo_elemento | string(12) null | `Competencia \| Conocimiento`; solo en esas dimensiones |
| elemento_id | bigint null | referencia lógica a Estructura |
| descripcion | string(500) null | obligatoria en Experiencia y Meta |
| nivel_actual, nivel_objetivo | char(1) null | `A–D`; solo en Competencia y Conocimiento con grado; distintos entre sí |
| indicador | string(500) null | opcional |
| fecha_inicio, fecha_fin | date | dentro del periodo del plan |
| resultado | string(10) null | `Cerrada \| Parcial \| NoCerrada`; se guarda al cerrar |
| timestamps | | |

Restricciones de aplicación: no se repite `(tipo_elemento, elemento_id)` en un plan (FR-015).

### `pdi_acciones`
| Columna | Tipo | Notas |
|---|---|---|
| id, tenant_id | | |
| compromiso_id | FK → pdi_compromisos | restrictOnDelete |
| orden | unsigned smallint | |
| tipo | string(20) | `Curso \| Mentoring \| Coaching \| Autodesarrollo \| Codesarrollo \| NuevaResponsabilidad \| Otra` |
| descripcion | string(500) | |
| persona_cargo | string(150) null | texto libre |
| fecha_inicio, fecha_fin | date | dentro del periodo del plan |
| plan_accion_id | bigint null | referencia lógica a una acción del Plan de Formación; no único |
| estado | string(10) | `Pendiente \| EnCurso \| Cumplida \| Cancelada`; ver nota |
| estado_congelado | boolean, default false | verdadero tras cerrar el plan en acciones vinculadas (FR-020a) |
| created_by, updated_by | bigint | |
| timestamps | | |

Nota sobre `estado`: sin vínculo es el valor registrado a mano. Con vínculo y plan no Cerrado **no se lee** (se deriva, R3). Con `estado_congelado = true` se lee el guardado.

### `pdi_hitos`
`id, tenant_id, pdi_id (FK), fecha, descripcion (string 300), estado (Pendiente | Realizado, por defecto Pendiente), observaciones (string 500 null), registrado_by (null), registrado_at (null), timestamps`. La fecha cae dentro del periodo del plan.

### `pdi_historial`
`id, tenant_id, pdi_id (FK), tipo (Creacion | Edicion | Estado | Acuerdo | Aceptacion | Devolucion | Responsable | Vinculo | Avance | Hito | Cierre), referencia_id (null), valor_anterior (string null), valor_nuevo (string null), motivo (string 1000 null), user_id, created_at`. Solo inserciones; sin `updated_at`.

## Entidades de dominio y objetos de valor

- `Pdi` (raíz): reglas de estado y de completitud; `Compromiso`, `Accion`, `Hito` como entidades hijas.
- Valores: `CodigoDePdi`, `TipoDePdi`, `EstadoDePdi`, `DimensionDeCompromiso`, `ResultadoDeCompromiso`, `ResultadoDePdi`, `TipoDeAccionPdi`, `EstadoDeAccionPdi`, `EstadoDeHito`, `TipoDeHistorialPdi`, `PeriodoDePdi`, `ClaveDePermisoPdi`.
- Servicios de dominio puros: `ReglasDeEdicionDePdi`, `CalculadorDeAvance`, `CalculadorDeResultadoDePdi`, `DerivadorDeEstadoDeAccion`, `ReglasDeCompromiso`.

## Reglas y transiciones

```
              crear                    acuerdo responsable
 (nada) ───────────────► Borrador ─────────────────────────┐ (sigue Borrador; visible al colaborador)
                           ▲  │  ▲                           │
   editar (borra acuerdos) │  │  │ devolver colaborador      │
   cambiar responsable ────┘  │  └───(comentario obligatorio; borra acuerdos)
                              │                              ▼
        devolver (motivo) ◄───┴──────────── aceptar ──► Acordado ──cerrar──► Cerrado
              (borra acuerdos)           (con acuerdo vigente)      (solo lectura)
```

| Transición | Condición | Efecto |
|---|---|---|
| crear → Borrador | validaciones de entrada; sin otro plan no Cerrado del mismo tipo | código, `created_by`, historial `Creacion` |
| editar Borrador | plan en Borrador, `version` vigente | reemplaza hijos; borra `acuerdo_*`, `aceptacion_*`; `version`+1; historial `Edicion` |
| acuerdo del responsable | Borrador y completo (≥1 compromiso, cada uno con ≥1 acción, responsable y periodo) | guarda `acuerdo_responsable_*`; el plan sigue Borrador y es visible al colaborador |
| aceptar | colaborador dueño, Borrador, acuerdo vigente | `aceptacion_colaborador_at`; pasa a **Acordado** |
| devolver (colaborador) | colaborador dueño, Borrador, acuerdo vigente, comentario | guarda comentario; borra `acuerdo_*` y `aceptacion_*`; deja de ser visible |
| devolver a Borrador (admin) | Acordado, motivo | borra `acuerdo_*`, `aceptacion_*`; deja de ser visible al colaborador |
| cambiar responsable | Borrador o Acordado; destino administrador activo | actualiza `responsable_id`; en Acordado conserva acuerdos; historial `Responsable` |
| avance de acción / hito | Acordado; acción sin vínculo | historial `Avance` / `Hito` |
| vincular / desvincular | Borrador o Acordado; tipo Curso, Mentoring o Codesarrollo; participante en plan Aprobado/Ejecutando | historial `Vinculo` |
| cerrar | Acordado; resultados en todos los compromisos; todas las acciones Cumplida o Cancelada | congela estado de acciones vinculadas; guarda resultados y `resultado` del plan; historial `Cierre` |

Cálculos (no se guardan salvo indicación):
- **Avance** = Cumplidas ÷ no Canceladas × 100 (entero redondeado); `null` si no hay no Canceladas.
- **Hito vencido** = `Pendiente` con `fecha < hoy` (hoy del reloj inyectable).
- **Resultado del plan** (se guarda al cerrar): todos `Cerrada` → `BrechaCerrada`; ninguno `Cerrada` → `NoCerrada`; otro caso → `Parcial`.

## Validaciones de entrada (resumen)
- Plan: `colaborador_id` vigente; `tipo` válido; `objetivo` 1–500; `puesto_objetivo_id` activo si Carrera/Talento (y ausente si Mejora); `responsable_id` administrador activo; `fecha_fin ≥ fecha_inicio`.
- Compromiso: dimensión válida; `elemento` activo y no repetido en Competencia/Conocimiento; `descripcion` en Experiencia/Meta; niveles A–D distintos entre sí cuando aplican; fechas dentro del periodo del plan.
- Acción: tipo válido; `descripcion` obligatoria; fechas dentro del periodo; `plan_accion_id` solo con tipos vinculables y validado por la Facade.
- Hito: `fecha` dentro del periodo; `descripcion` obligatoria.

## Seeder `PdisSeeder`
Cinco planes: Mejora Borrador sin acuerdo; Carrera Borrador con acuerdo del responsable (visible al colaborador); Talento Acordado con acción vinculada y un hito vencido; Mejora Cerrado «Brecha cerrada»; Carrera Cerrado «Parcial». Cubre las cuatro dimensiones y los siete tipos de acción.
