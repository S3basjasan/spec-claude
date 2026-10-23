# Contrato de pantallas: Plan de desarrollo individual (PDI)

Referencia visual **exacta**: `design/PDIAdmin.dc.html`, `design/PDIAsistente.dc.html`, `design/PDIDetalle.dc.html`, `design/PDISeguimiento.dc.html` (los cuatro son el **mismo prototipo del administrador**: misma cabecera, menú y código; solo cambia la vista inicial: lista, asistente, detalle o Gantt) y `design/PDIColaborador.dc.html` («Mi plan de desarrollo»). Más los tokens, patrones y componentes de `design/DESIGN.md`. Los textos visibles están en español y centralizados en `frontend/src/modules/desarrollo/pdi/text.ts`.

Las diferencias entre los tableros y la spec están en `plan.md` («Diferencias entre tableros y spec»); donde chocan, **manda la spec en reglas y datos y el tablero en aspecto y estructura**.

## Rutas

| Ruta | Pantalla | Permiso |
|---|---|---|
| `/pdi` | Administrador con alguna clave de gestión: pestañas **Lista de PDI** y **Seguimiento (Gantt)** (`?pestana=`). Colaborador con solo `ver_propio`: «Mi plan de desarrollo». | gestión / `ver_propio` |
| `/pdi/nuevo` | Asistente de creación (3 pasos, `?paso=`; acepta `?colaborador=&tipo=&puesto_objetivo=` para precargar desde Adecuación o «sin plan») | `desarrollo.pdi.gestionar` |
| `/pdi/:id` | Detalle (pestañas Resumen, Compromisos y acciones, Hitos, Historial; `?pestana=`). Con `ver_propio`: detalle del colaborador (sin pestañas de gestión) | `desarrollo.pdi.ver` / `ver_propio` |
| `/pdi/:id/editar` | Edición de un Borrador (mismo asistente con datos cargados) | `desarrollo.pdi.gestionar` |

La navegación declarativa agrega el ítem **PDI** (entre «Adecuación» y «Micro-learning»), visible con cualquier clave `desarrollo.pdi.*`, con `aria-current`. Al colaborador le aparece como «Mi plan de desarrollo».

## 1. Lista de PDI (administrador) — `PDIAdmin.dc.html`
- Encabezado «Plan de Desarrollo Individual (PDI)» con subtítulo y botón primario **Nuevo PDI**.
- Pestañas: **Lista de PDI** | **Seguimiento (Gantt)**.
- Tarjetas KPI (Borradores, Acordados, Cerrados, Hitos vencidos; cifra y nota).
- Bloque **Personas con brecha y sin PDI**: conteo y lista (nombre, departamento, origen DNF/adecuación, botón **Crear PDI** con la precarga en URL).
- Filtros: Tipo, Estado, Departamento, Responsable, rango de fechas y «Limpiar filtros»; todos en la URL.
- Tabla: Colaborador (nombre, código · departamento), Tipo (badge), Estado (badge), Responsable, Periodo, Progreso (barra y %), indicador **Hitos vencidos**, acción **Abrir**.
- Estados: loading (skeleton), empty («Ningún PDI coincide con los filtros»), error con «Reintentar», sin permisos; paginación en URL.

## 2. Asistente de creación — `PDIAsistente.dc.html`
Tres pasos con indicador de pasos (`step`), «Atrás» / «Continuar» y validación por paso.
1. **Colaborador y tipo**: selector de colaborador con búsqueda; Tipo de PDI (Mejora, Carrera, Talento) con descripción; Puesto objetivo (Carrera y Talento; sugerido desde la inscripción Activa); resumen de origen («DNF del … / Adecuación ADE-…»), brechas detectadas y vista previa de los compromisos precargados. Aviso si ya existe un plan abierto del tipo (bloquea «Continuar» y enlaza al plan).
2. **Compromisos y acciones**: tarjeta por compromiso (**Quitar**): Dimensión (solo las de `dimensiones_disponibles`; Conocimiento no se ofrece con la bandera en `false`), Elemento o Descripción, niveles actual y a alcanzar (solo Competencia/Conocimiento), Indicador (opcional), Inicio y Fin; dentro, lista de acciones con Tipo, Descripción, Persona a cargo, Inicio y Fin, y — en Curso, Mentoring y Codesarrollo — **Vincular a Plan de Formación** (selector con las acciones vinculables, ver `contracts/api.md`). Botones **Agregar compromiso**, **Agregar acción** y **Volver a precargar brechas**.
3. **Hitos, responsable y periodo**: Responsable (administradores), Inicio y Fin del periodo, calendario de **Hitos intermedios** (fecha y descripción, Agregar/Quitar). Botón primario **Crear PDI (Borrador)**.
- Estados: validación por campo, plan duplicado, conflicto de versión (409), error con «Reintentar», sin permisos.

## 3. Detalle (administrador) — `PDIDetalle.dc.html`
- Encabezado: colaborador, badges de tipo y estado, «código · departamento · Destino · Responsable · periodo», barra de avance y %.
- **Resumen**: panel «Acuerdo y estado» con **Acuerdo del responsable** y **Aceptación del colaborador** (fecha y usuario), comentario del colaborador si lo devolvió, botones según estado y permisos: **Confirmar acuerdo** (Borrador completo y sin acuerdo), **Editar** (Borrador), **Devolver a Borrador** (Acordado; modal con motivo), **Cerrar** (Acordado; modal de cierre), **Cambiar responsable** (Borrador y Acordado). Texto guía: «El plan pasa a Acordado cuando el responsable confirma el acuerdo y el colaborador lo acepta».
- **Compromisos y acciones**: por compromiso, indicador y niveles; por acción, tipo, descripción, persona a cargo, fechas y estado: selector (Pendiente, En curso, Cumplida, Cancelada) **solo** en acciones sin vínculo de un plan Acordado; las vinculadas muestran el estado derivado en solo lectura con el nombre de la acción formativa y «Desvincular»; «Requiere revisión» cuando aplica. **Vincular a Plan de Formación** en Curso/Mentoring/Codesarrollo.
- **Hitos**: lista con fecha, descripción, estado (selector Pendiente/Realizado en Acordado), observaciones y marca **Vencido**.
- **Historial**: tabla de solo lectura (fecha, usuario, evento, valor anterior, valor nuevo, motivo) con paginación.
- **Modal Cerrar**: resultado por compromiso (Cerrada, Parcial, No cerrada), conclusión opcional, lista de acciones abiertas que bloquean; al confirmar muestra el resultado del plan y, en Carrera y Talento, **Generar nueva adecuación** (navega a `/adecuacion?colaborador=…&puesto=…`).
- Plan Cerrado: todo en solo lectura, con el panel «Resultado del cierre» (resultado, conclusión, fecha).

## 4. Seguimiento (Gantt) — `PDISeguimiento.dc.html`
- Filtros: Tipo, Responsable y Departamento (la spec añade Responsable; el tablero tiene Tipo y Departamento).
- Leyenda **Hecha / En curso / Pendiente / Hito** (mapeada a Cumplida, En curso, Pendiente, Hito), marcador de **hoy**, filas por plan con sus acciones y botón **Abrir**.
- Solo planes Acordado. Estados: empty («No hay PDI Acordados con estos filtros»), loading, error, sin permisos.
- Accesibilidad: la línea de tiempo tiene una alternativa tabular legible por lector de pantalla; los colores no son la única señal (etiquetas de estado).

## 5. Mi plan de desarrollo (colaborador) — `PDIColaborador.dc.html`
- Tarjetas de planes (tipo, estado, destino, avance, periodo); selector entre planes con la misma interacción que «Mi adecuación». Solo planes con el acuerdo del responsable.
- Detalle: Responsable, Periodo, Estado y panel **Acuerdo**; en Borrador (con acuerdo del responsable): casilla de lectura «Revisé los compromisos, acciones y fechas de este plan y acepto cumplirlos», botón **Aceptar plan** y **Devolver con comentario** (modal con comentario obligatorio).
- **Compromisos y acciones** (solo lectura), **Hitos de revisión** y **Qué sigue** (acciones e hitos próximos ordenados por fecha; vencidos destacados; enlace «Ver actividad» en acciones vinculadas).
- Estados: vacío («Aún no tienes un plan de desarrollo»), loading, error con «Reintentar».
- Textos del colaborador sin lenguaje de gestión; no se muestran datos de otras personas.

## Componentes
- **Reutilizados** (features 013–020 y design system): App Shell, Tabs, Badge, Card, Table, Select/Combobox, Input, Textarea, Button, Modal de confirmación, Paginación, Alert, Skeleton, estados vacío/error, barra de progreso.
- **Nuevos** (`frontend/src/modules/desarrollo/pdi/components/`): `PasosDelAsistente`, `SelectorDeColaboradorYTipo`, `TarjetaDeCompromiso`, `FilaDeAccion`, `SelectorDeAccionVinculable`, `CalendarioDeHitos`, `PanelDeAcuerdo`, `TablaDePdi`, `KpisDePdi`, `BloqueSinPlan`, `ModalDeCierre`, `ModalDeMotivo`, `LineaDeTiempoGantt`, `TarjetaDePlan`, `QueSigue`, `BadgeDeEstadoDePdi`, `BadgeDeTipoDePdi`, `BadgeDeEstadoDeAccion`, `BadgeDeResultado`.

## Accesibilidad y responsive
- WCAG 2.2 AA; objetivos táctiles ≥ 44 px (como los tableros); foco visible; modales con foco atrapado y restauración.
- 320–1440 px sin scroll horizontal de página: las tablas pasan a tarjetas apiladas en < 768 px; el Gantt scrollea dentro de su contenedor con la alternativa tabular.
- Tema claro y oscuro con los tokens existentes; sin colores propios.
