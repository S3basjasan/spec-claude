# Design system — HRMS

Fuente visual única del HRMS. Aplica a los 5 módulos.
Las referencias `*.dc.html` de esta carpeta son mockups de Claude Design: sirven como **referencia visual**, no como código para copiar. La implementación usa los componentes compartidos de `frontend/src/shared`.

> Ante cualquier diferencia entre un mockup y la spec de una feature, **manda la spec**. Este documento manda sobre los mockups en estilos (colores, tamaños, espaciados).

---

## 1. Cómo usar este documento

1. Toda pantalla nueva se arma **combinando los patrones de la sección 5**. No se inventan colores, tamaños ni estilos nuevos.
2. Se diseña una pantalla nueva en Claude Design **solo si ningún patrón la cubre** (ver sección 8).
3. En la spec de cada feature, la sección *Pantallas → Mockup* apunta al patrón que usa. Ejemplo:
   `Mockup: patrón Listado (design/Competencias.dc.html), columnas Código, Nombre, Cargo, Estado.`

---

## 2. Tokens

Todos los colores se usan como variables CSS (`var(--accent)`), nunca como hex sueltos en los componentes. Hay tema claro y oscuro; el usuario lo cambia desde el header.

### Colores

| Token | Claro | Oscuro | Uso |
|---|---|---|---|
| `--bg` | `#F5F6F8` | `#0E1318` | Fondo de página |
| `--surface` | `#FFFFFF` | `#151B22` | Header, menú lateral, tarjetas, tablas, formularios |
| `--border` | `#E2E5EA` | `#27303A` | Bordes decorativos y separadores |
| `--input-border` *(propuesto)* | `#8A94A3` | `#5C6877` | Borde de inputs, selects y textareas |
| `--text` | `#12171D` | `#E7EBEF` | Texto principal |
| `--muted` | `#5A6573` | `#9CA7B3` | Texto secundario, subtítulos, metadatos, encabezados de tabla |
| `--accent` | `#0F6F69` | `#4FD1C5` | Acción principal, enlace activo, foco |
| `--accent-hover` | `#0B5B56` | — | Hover de botón primario |
| `--onaccent` | `#FFFFFF` | `#06201E` | Texto sobre `--accent` |
| `--soft` | `#E3F2F0` | `#12302D` | Hover, ítem activo del menú, badges, avatar |
| `--danger` | `#B42318` | `#FF8A80` | Eliminar, errores |
| `--dangersoft` | `#FDECEA` | `#3A1B1A` | Fondo de alertas de error |
| `--ondanger` | `#FFFFFF` | `#2A0B09` | Texto sobre `--danger` |
| `--warning` | `#B54708` | `#F5B454` | Advertencias que no son errores (p. ej. el ícono "No sigue la cadena") y el estado **Vacante**; contraste ≥ 4.5:1 sobre `--surface` y sobre `--warningsoft` |
| `--warningsoft` | `#FFF3E0` | `#3A2A10` | Fondo del Badge **Vacante** (variante de advertencia) |

**Colores semánticos de estado** *(añadidos por la feature 006, DNF; valores tomados de `DNFAdmin.dc.html`)*. Se usan en los Badges de prioridad y estado, siempre con texto, nunca solo con color:

| Token | Claro | Oscuro | Uso |
|---|---|---|---|
| `--warn` | `#8A4B0B` | `#F2C27A` | Texto de advertencia sobre `--warnsoft` (prioridad Alta, borrador, brecha en el gráfico) |
| `--warnsoft` | `#FDEFD9` | `#3A2A12` | Fondo de advertencia |
| `--info` | `#1F4E9C` | `#9DBEF5` | Texto informativo sobre `--infosoft` (prioridad Media) |
| `--infosoft` | `#E4EDFB` | `#172A4A` | Fondo informativo |
| `--neutral` | `#3B4654` | `#B8C2CD` | Texto neutro sobre `--neutralsoft` (prioridad Baja, Sin evaluación) |
| `--neutralsoft` | `#ECEEF1` | `#232B34` | Fondo neutro |

**Colores por tipo de acción formativa** *(añadidos por la feature 013, Plan de formación anual; valores de `PlanAnualAdmin.dc.html`)*. Los tipos Presencial, Virtual, E-learning y OJT reutilizan tokens existentes; Codesarrollo y Mentoring añaden dos pares. Siempre llevan el nombre del tipo como texto:

| Tipo | Texto / fondo (claro) | Texto / fondo (oscuro) | Tokens |
|---|---|---|---|
| Presencial | `#0B5A55` / `#E3F2F0` | `#7FE0D6` / `#12302D` | `--accent` / `--soft` |
| Virtual | `#1F4E9C` / `#E4EDFB` | `#9DBEF5` / `#172A4A` | `--info` / `--infosoft` |
| E-learning | `#8A4B0B` / `#FDEFD9` | `#F2C27A` / `#3A2A12` | `--warn` / `--warnsoft` |
| OJT | `#3B4654` / `#ECEEF1` | `#B8C2CD` / `#232B34` | `--neutral` / `--neutralsoft` |
| Codesarrollo | `#5B2E91` / `#F0E7FA` | `#CDA9F2` / `#2C1D44` | `--violet` / `--violetsoft` *(nuevos)* |
| Mentoring | `#9C2250` / `#FBE6EC` | `#F2A6C0` / `#40182A` | `--rose` / `--rosesoft` *(nuevos)* |

**Colores por tipo y estado de micro-contenido** *(feature 016, Micro-learning; sin tokens nuevos)*. El color nunca es la única señal: siempre va con el nombre del tipo o del estado.

| Tipo | Texto / fondo | Tokens |
|---|---|---|
| Video (rojo) | `--danger` / `--dangersoft` | también el borde superior de la tarjeta |
| PDF (azul) | `--info` / `--infosoft` | |
| Artículo (verde) | `--accent` / `--soft` | |
| Quiz (púrpura) | `--violet` / `--violetsoft` | |

Estados: Borrador `Badge` de advertencia (`--warning`/`--warningsoft`); Publicado `Badge` por defecto (`--accent`/`--soft`); Archivado `Badge` neutro (`--muted` sobre `--border`).

**Colores por estado de actividad y asistencia** *(feature 017, Actividades formativas; sin tokens nuevos)*. El color nunca es la única señal: siempre va con el nombre del estado.

| Elemento | Texto / fondo | Tokens |
|---|---|---|
| Actividad Programada | `--info` / `--infosoft` | igual que la acción |
| Actividad En curso | `--accent` / `--soft` | |
| Actividad Finalizada | `--neutral` / `--neutralsoft` | |
| Actividad Cancelada | `--danger` / `--dangersoft` | |
| Asistencia Pendiente | `--neutral` / `--neutralsoft` | sin registro |
| Asistencia Presente | `--accent` / `--soft` | |
| Asistencia Ausente | `--danger` / `--dangersoft` | |
| Asistencia Justificado | `--warning` / `--warningsoft` | |
| Badge «Asistencia pendiente» | `--warning` / `--warningsoft` | actividad Finalizada con inscritos sin registro |

Los tipos de actividad usan los mismos colores que el tipo de la acción del plan.

**Estados del plan y de la acción**: Borrador `--neutral`/`--neutralsoft`; Revisión `--warn`/`--warnsoft`; Aprobado `--info`/`--infosoft`; Ejecutando `--accent`/`--soft`; Cerrado `--neutral`/`--neutralsoft` con borde `--border`. Acción: Programada `--info`/`--infosoft`; En curso `--accent`/`--soft`; Finalizada `--neutral`/`--neutralsoft`; Cancelada `--danger`/`--dangersoft`.

Contrastes verificados (WCAG AA): `--text` y `--muted` sobre `--bg`/`--surface` ≥ 5.4:1; `--accent` sobre blanco 6:1; `--danger` sobre `--dangersoft` 5.75:1.

**`--input-border` es nuevo.** Los mockups usan `--border` (1.26:1) o `#C9CED6` (1.58:1) en los inputs, y ninguno llega al 3:1 que exige WCAG 1.4.11 para el borde de un control. Los valores propuestos dan 3.07:1 (claro) y 3.05:1 (oscuro).

### Tipografía

Fuente: **Public Sans** (Google Fonts), pesos 400, 500, 600, 700. Fallback: `system-ui, sans-serif`.

| Rol | Tamaño | Peso | Extra |
|---|---|---|---|
| Título de página (h1) | 28px | 700 | line-height 1.2, letter-spacing -0.02em |
| Subtítulo de página | 16px | 400 | color `--muted` |
| Título de sección o tarjeta (h2) | 16–18px | 700 | |
| Cuerpo, celdas, inputs | 15px | 400 | |
| Labels de formulario | 14px | 600 | |
| Texto auxiliar, ayuda, errores de campo | 13px | 400 | |
| Encabezado de tabla | 13px | 600 | color `--muted` |
| Valor de KPI | 32px | 700 | letter-spacing -0.02em |
| Marca "HRMS" | 18px | 700 | letter-spacing -0.01em |
| Hero (solo páginas públicas) | 48px | 700 | line-height 1.1, letter-spacing -0.03em |

### Espaciado y tamaños

- **Altura mínima de cualquier control interactivo: 44px** (botones, inputs, selects, ítems de menú, paginación). Botón principal de páginas públicas: 48px.
- Padding del contenido principal: 32px.
- Separación entre bloques de una página: 20–24px. Entre controles de una misma fila: 12px. Entre tarjetas en grilla: 16px.
- Padding de tarjetas y formularios: 20–24px. Celdas de tabla: 14px vertical × 20px horizontal.
- Ancho máximo del contenido: **1100px** en listados y dashboards, **760px** en formularios.

### Radios y sombras

| Elemento | Radio |
|---|---|
| Botones, inputs, ítems de menú | 8px |
| Menús desplegables | 10px |
| Tarjetas, tablas, diálogos | 12px |
| Badges | 999px (píldora) |
| Avatar | 50% |

| Elemento | Sombra |
|---|---|
| Menú desplegable | `0 8px 24px rgba(0,0,0,0.12)` |
| Diálogo | `0 12px 32px rgba(0,0,0,0.14)` |
| Tarjeta de login | `0 8px 24px rgba(18,23,29,0.06)` |

Las tarjetas y tablas dentro de la app **no llevan sombra**, solo borde `--border`.

---

## 3. App shell

Referencia: `Main.dc.html`. Toda pantalla autenticada usa este marco.

- **Header** (64px, `--surface`, borde inferior): marca "H" + "HRMS" a la izquierda; a la derecha, botón de cambio de tema (icono, 44×44) y botón de usuario (avatar con iniciales, nombre y chevron).
- **Menú de usuario** (desplegable bajo el botón de usuario): nombre y rol, "Mi perfil", "Ajustes" y "Cerrar sesión" en `--danger`.
- **Menú lateral** (248px, `--surface`, borde derecho): un ítem por módulo con icono de 20px + texto. Ítem activo con fondo `--soft`, texto `--accent`, peso 600 y `aria-current="page"`.
- **Contenido**: fondo `--bg`, padding 32px, ancho máximo según el patrón.
- **Footer** (`--surface`, borde superior, 13px `--muted`): versión a la izquierda; Ayuda, Privacidad y Términos a la derecha.
- Debajo de ~800px de ancho, el menú lateral pasa arriba del contenido (flex-wrap). [Pendiente: diseño móvil definitivo, ver `Movil.dc.html` y `MovilMenu.dc.html`.]

Cada módulo agrega su propio ítem al menú lateral; no se crean menús separados por módulo.

---

## 4. Componentes base

Estos componentes viven en `frontend/src/shared/components` y los usan todos los módulos.

| Componente | Especificación |
|---|---|
| **Button – primario** | Fondo `--accent`, texto `--onaccent`, 600, 15px, alto 44px, padding 0 16–20px, radio 8. Hover `--accent-hover`. Un solo primario por vista. |
| **Button – secundario** | Fondo `--surface`, borde `--border`, texto `--text`, 500–600. |
| **Button – peligro** | Fondo `--danger`, texto `--ondanger`. Solo dentro del diálogo de confirmación. |
| **IconButton** | 44×44, radio 8, icono 18–20px. Siempre con `aria-label`. En filas de tabla, sin borde; el de eliminar va en `--danger`. |
| **Input / Select / Textarea** | Alto 44px (textarea según filas), padding 10–12px, radio 8, borde `--input-border`, fondo `--surface`, 15px. Error: borde `--danger` + `aria-invalid="true"`. |
| **FormField** | Label arriba (14px, 600), control, y debajo ayuda (13px `--muted`) o error (13px `--danger`, enlazado con `aria-describedby`). |
| **SearchInput** | Input con icono de lupa a la izquierda (padding-left 38px), `type="search"`, label oculto visualmente. |
| **Badge** | Píldora, fondo `--soft`, texto `--accent`, 13px, 600, padding 3px 10px. Variante **neutra** para estados inactivos: fondo `--border`, texto `--muted` (contraste ≥ 4.5:1 en ambos temas). Variante **advertencia** (estado **Vacante** de una posición): fondo `--warningsoft`, texto `--warning`. Variante **peligro** (estado **Suprimida**): fondo `--dangersoft`, texto `--danger`. Ocupada usa el estilo normal. Un estado se muestra siempre con texto, nunca solo con color. |
| **Card** | `--surface`, borde `--border`, radio 12, padding 20–24px. |
| **DataTable** | Contenedor Card sin padding con scroll horizontal (`min-width` 640px). Encabezados 13px 600 `--muted`; filas separadas por borde superior; hover de fila `--soft`; columna de acciones alineada a la derecha. |
| **Pagination** | "Mostrando X–Y de Z" a la izquierda; "Anterior", "Página N de M" y "Siguiente" a la derecha. Botón deshabilitado con opacidad 0.45. |
| **Alert** | Fondo `--dangersoft`, texto `--danger`, icono de advertencia, radio 8, padding 12px 14px, `role="alert"`. |
| **ConfirmDialog** | Card de 380px con sombra de diálogo, `role="alertdialog"`. Título como pregunta, descripción que nombra el elemento y advierte si no se puede deshacer, y botones Cancelar (secundario) + acción (peligro). |
| **Alert – neutro** | Variante informativa del Alert: fondo `--soft`, texto `--text`, borde `--border`, icono de información, `role="status"`. Para avisos que no son un error (p. ej. "La competencia asociada está inactiva"). |
| **Combobox** | Campo de texto (mismo estilo que Input) que filtra una lista de opciones al escribir, sin distinguir mayúsculas. `role="combobox"` con `aria-expanded`, `aria-controls` y `aria-activedescendant` hacia un `listbox` de `Card` con sombra (máx. 240px de alto con scroll); cada opción es `role="option"` con nombre (15px) y descripción opcional (13px `--muted`); la opción activa usa fondo `--soft`. Flechas arriba/abajo mueven la opción activa, **Enter** la selecciona, **Escape** cierra y **Tab** sale; al elegir, el campo muestra la etiqueta elegida. Sin coincidencias muestra "Sin resultados" (13px `--muted`) dentro de la lista. Error: igual que Input (borde `--danger` + `aria-invalid`). |
| **Lista de campos** | Grupo de campos de texto repetibles dentro de un `fieldset` con `legend`. Cada campo es un Input con **contador** `n/máximo` (13px `--muted`) debajo y un IconButton **Quitar** (`aria-label` "Quitar comportamiento N del grado X", deshabilitado en el mínimo, opacidad 0.45). Debajo, un botón secundario **Agregar …** con icono + (deshabilitado en el máximo). Mínimo 1 y máximo 4 campos; error por campo (13px `--danger`, enlazado con `aria-describedby`). |
| **Fila de tipo y texto** | Fila repetible dentro de un `fieldset` (funciones y requisitos de un puesto): `SelectField` de tipo (ancho fijo 190px) + Input de texto con **contador** `n/máximo` debajo + IconButton **Quitar** (`aria-label` "Quitar función N" / "Quitar requisito N"). En pantallas estrechas el selector pasa arriba del texto. Debajo del grupo, botón secundario **Agregar …** con icono + (deshabilitado en el máximo de 20). Error por campo (13px `--danger`, enlazado con `aria-describedby`) y error de grupo bajo el `legend`. |
| **Reordenar (Subir / Bajar)** | Dos IconButton 44×44 por fila ("Subir función N", "Bajar función N"), deshabilitados en los extremos (opacidad 0.45). Tras moverla, la numeración se recalcula desde 1 y un `role="status"` (`aria-live="polite"`) anuncia "Función movida a la posición N de M". No se usa arrastrar y soltar, de modo que la reordenación funciona con teclado y lector de pantalla (WCAG 2.5.7). |
| **Fila de competencia** | `Combobox` con las competencias activas aún no elegidas + datos de solo lectura (código, tipo y estado en `Badge`) + `SelectField` de **grado requerido** con los grados de la competencia (se reinicia al cambiar de competencia) + IconButton Quitar. Si la competencia asignada está **inactiva**: nombre con `Badge` neutro "Inactiva", grado **bloqueado** (texto con el grado y aviso de 13px `--muted`: "La competencia está inactiva: solo puedes dejarla como está o quitarla") y solo Quitar. |
| **Botón de estado de fila (Desactivar / Reactivar)** | Botón de texto secundario de 44px en la columna de acciones, junto al IconButton Editar; su texto alterna según el estado ("Desactivar" / "Reactivar") y su nombre accesible incluye el registro ("Desactivar Ventas"). **Desactivar deshabilitado**: cuando el recurso no puede desactivarse (p. ej. una unidad con unidades dependientes activas o posiciones ocupadas o vacantes) el botón lleva `disabled` (opacidad 0.45) y `aria-describedby` hacia un texto de 13px `--muted` **siempre visible** bajo el botón que indica el motivo con las cantidades ("No se puede desactivar: tiene 4 unidades dependientes activas y 3 posiciones ocupadas o vacantes."); el motivo no depende de un tooltip ni del color. Desactivar abre ConfirmDialog; Reactivar actúa sin confirmación y, si el servidor lo rechaza, muestra el motivo en un Alert. |
| **Modal** | Card de 480 a 640px de ancho máximo según el contenido, radio 14, padding 24px y sombra `0 16px 48px rgba(0,0,0,0.25)`, sobre un velo oscuro, `role="dialog"` con `aria-modal` y título enlazado; foco atrapado, Escape cierra y el foco vuelve al control que lo abrió; cabecera con título e IconButton de cierre, cuerpo con scroll y pie con acciones a la derecha. Los modales de confirmación destructiva usan `alertdialog` (ConfirmDialog). |
| **Chip de nivel** | Píldora 13px con el número de nivel (`1`, `2`…) y el nombre del tipo, fondo `--soft`, texto `--text`; en la cadena, separadas por un chevron decorativo. |
| **Alert con enlace** | Variante del Alert (de error) con un enlace de texto debajo del mensaje, en 600 y subrayado, que lleva a otro registro (p. ej. «Abrir ficha existente» ante una identificación duplicada). `role="alert"`; el enlace es un `Link`, no un botón. |
| **Par etiqueta-valor** | Lista de definición `<dl>` en grilla de 2 columnas (1 bajo 640px): etiqueta (`<dt>`, 13px 600 `--muted`) y valor (`<dd>`, 15px). Un dato ausente se muestra como **«No registrado»** en `--muted`; el número 0 se muestra como 0 (nunca como «No registrado»). Cada grupo va en una Card con `h2`. |
| **Información no disponible** | Texto «Información no disponible» (14px `--muted`, `role="status"`) con botón secundario **Reintentar**, en el lugar de una sección cuya fuente falló. Es distinto de «No registrado» (ausencia confirmada) y no oculta el resto de la ficha. |
| **Línea de tiempo** | Lista ordenada `<ol>` de hechos, del más reciente al más antiguo; cada ítem con borde izquierdo 2px `--border`, fecha en `<time>` (14px 600), tipo (15px) y detalle opcional (14px `--muted`). Un hecho con fecha efectiva futura lleva la insignia neutra **Programado**. |
| **Pestañas de sección** | `role="tablist"` con botones `role="tab"` (alto 44px, `aria-selected`, `aria-controls`) y panel `role="tabpanel"`; la activa lleva borde inferior 2px `--accent` y texto `--accent`. Una sección que su funcionalidad aún no ofrece o que la persona no puede ver **no se dibuja**; con una sola sección no hay pestañas. |
| **Alert de rechazo con motivo** | Alert (de error) con una frase que **explica el conflicto** (p. ej. «La posición está ocupada desde el 2026-01-01 durante el periodo solicitado.») y, cuando existe, la acción que lo resuelve en texto (cambiar la fecha o elegir otra posición). Va sobre los campos, junto al error del campo implicado, y **el formulario conserva todos los valores**. `role="alert"`. |
| **Selector de posición** | Un `SearchField` («Buscar posición», con pausa de 300 ms) más un `Combobox` con las posiciones elegibles para la fecha; cada opción lleva puesto y unidad. Al elegir una, un `<dl>` en solo lectura muestra **puesto, unidad y jefatura** («No registrado» si falta). Estados: cargando (Skeleton), vacío («No hay posiciones disponibles para esa fecha»), error de verificación (ErrorState con Reintentar) y, si hay más resultados de los mostrados, el aviso «Se muestran N de M posiciones». |
| **Skeleton** | Bloques `--border` con animación de pulso (1.4s); imita la forma del contenido real. |
| **EmptyState / ErrorState** | Ver patrón Estados. |
| **Breadcrumb** | 14px `--muted`, separador "/", último ítem en `--text`. |
| **StatCard** | Card con label (14px `--muted`), valor (32px 700) y nota (13px `--muted`). |
| **Panel lateral (slide-over)** | `dialog` modal anclado a la derecha, ancho máx. 560px y alto completo, sobre un velo semitransparente. Cabecera con código y nombre (`h2`) y botón cerrar (IconButton, `aria-label`); cuerpo con scroll; pie fijo con **Cancelar** (secundario) y **Guardar** (primario, bloqueado mientras guarda). Foco atrapado, Escape y botón cerrar lo cierran; si hay cambios sin guardar abre un ConfirmDialog de descarte. En pantallas estrechas ocupa todo el ancho. Referencia: `CatalogoAdmin.dc.html`. |
| **Indicador de cobertura** | StatCard con el porcentaje (32px 700), barra de progreso (`role="progressbar"` con `aria-valuenow/min/max` y `aria-label`; relleno `--accent` sobre `--border`), nota "X de Y competencias activas" y un enlace "Ver sin cobertura". Referencia: `CatalogoAdmin.dc.html`. |
| **Puntos de nivel (A/B/C/D)** | Cuatro puntos con la letra del grado; el nivel actual va resaltado (`--accent` con `--onaccent`) y los demás en `--border`. Siempre acompañados de texto accesible ("Tu nivel: B" o "Sin nivel registrado"); el nivel no se comunica solo con color. Referencia: `CatalogoColaborador.dc.html`. |
| **Tarjeta de competencia** | Card clicable (botón que ocupa toda la tarjeta): nombre (h3, 16px 700), `Badge` de tipo, puntos de nivel y contador "X cursos disponibles" (13px `--muted`). Se agrupan por área bajo un `h2`; rejilla `minmax(260px, 1fr)` con 16px de separación. |
| **Selector de recursos (selección múltiple)** | Dentro del panel lateral: `SearchField` y lista de recursos con casilla (alto mín. 44px), nombre, `Badge` de tipo, duración, formador y "usado en N competencias" (13px `--muted`); botón primario **Asociar** (deshabilitado sin selección) y enlace **Volver al detalle**. Sin recursos: mensaje "Todos los recursos disponibles ya están asociados a esta competencia." |
| **Ítem de recurso** | Fila con nombre, `Badge` de tipo (color semántico), duración y formador, y a la derecha el botón de acción (**Iniciar**/**Ver**, o **Quitar** en gestión). Sin destino, el botón se muestra deshabilitado con el texto "Próximamente". Un recurso archivado lleva `Badge` neutro "Archivado". |
| **Historial de cambios** | Lista paginada (más reciente primero): usuario y fecha (13px `--muted`), acción y recurso o valores anterior → nuevo. Estado vacío: "Aún no hay cambios registrados." |

**Componentes de la DNF** *(feature 006; referencias `DNFAdmin.dc.html` y `DNFColaborador.dc.html`)*

| Componente | Especificación |
|---|---|
| **Stepper (pasos)** | Lista de pasos numerados («1 Selección», «2 Diagnóstico») en botones de alto 44px con `aria-current="step"` en el activo (borde `--accent`, fondo `--soft`). Un paso aún no alcanzable va `disabled` (opacidad 0.55) con `aria-disabled`. Puede vivir en `shared/ui` por ser reutilizable. |
| **Opción de alcance (radio-tarjeta)** | Grupo `role="radiogroup"` con etiqueta; cada opción es una tarjeta (radio 10, padding 14–16px, borde `--border`, la elegida con borde `--accent` y fondo `--soft`) con un `input type="radio"` nativo, título 15px 600 y descripción 13px `--muted`. Navegable con flechas. |
| **Badge de prioridad** | Píldora 12–13px 700 con texto: **Crítica** `--danger` sobre `--dangersoft`; **Alta** `--warn` sobre `--warnsoft`; **Media** `--info` sobre `--infosoft`; **Baja** `--neutral` sobre `--neutralsoft`. Sin prioridad se muestra «—». |
| **Badge de estado de brecha** | Mismo formato: **Con brecha** (`--danger`/`--dangersoft`), **Cumple** (`--accent`/`--soft`), **Supera** (`--info`/`--infosoft`), **Sin evaluación** (`--neutral`/`--neutralsoft`). |
| **Vista previa del colaborador / departamento** | Card con pares etiqueta-valor (puesto, departamento, número de competencias) o, en masiva, «N colaboradores · M comparaciones competencia–colaborador». |
| **KPI de diagnóstico** | `StatCard` en rejilla `minmax(200px, 1fr)` (competencias evaluadas, con brecha, prioridad crítica o alta, prioridad más alta); el valor puede ir en `--danger` si hay urgencia. |
| **Gráfico de perfil ideal vs actual** | `role="img"` con `aria-label` que resume el contenido; eje A–D; barras del nivel actual (`--accent`), marcador/línea del nivel requerido (`--text`) y tramo de brecha con borde discontinuo `--warn` sobre `--warnsoft`; leyenda visible. Equivalente accesible: la tabla del diagnóstico. En pantallas estrechas pasa a barras horizontales. |
| **Tabla de diagnóstico** | `DataTable` con columnas competencia, nivel requerido, nivel actual, brecha (grados), brecha (%) con minibarra (`--danger` si Crítica, `--warn` si otra prioridad, `--accent` si no hay), prioridad y estado; filtros de prioridad y estado y casilla «Mostrar solo competencias con brecha». |
| **Resumen por colaborador (masiva)** | `DataTable` con colaborador, puesto, cantidad de brechas y prioridad más alta; cada fila es un botón que abre el diagnóstico del colaborador en la misma pantalla y un enlace «Volver al resumen». |
| **Tarjeta de brecha (colaborador)** | Card con nombre (h3), Badge de prioridad, dos barras horizontales A–D («Requerido» y «Actual», cada una con su letra visible), la línea «Nivel actual: C · Requerido: A · Diferencia: −2 (50 % de brecha)» y el botón **Ver cursos sugeridos** (secundario). Sin oferta: botón deshabilitado y «Aún no hay cursos asociados en el catálogo.» Con `aria-label` que resume la tarjeta. |
| **Mis brechas prioritarias** | Sección con `h2` y subtítulo; hasta tres tarjetas de brecha numeradas (1–3) de mayor a menor prioridad; debajo «Otras brechas detectadas» y un bloque plegable «Competencias que ya cumples (N)». |
| **Botón «Generar Plan de Formación»** | Botón primario solo para el Administrador. Deshabilitado con motivo visible (13px `--muted`, enlazado con `aria-describedby`) si no hay brechas o si ya generó plan; en este caso se sustituye por el enlace «Ver plan». |

**Componentes del Plan de formación anual** *(feature 013; referencias `PlanAnualAdmin.dc.html` y `PlanAnualColaborador.dc.html`)*

| Componente | Especificación |
|---|---|
| **Tabs (pestañas)** | `role="tablist"` con botones `role="tab"` de alto 44px, `aria-selected`, texto `--muted` y, en el activo, `--accent` con borde inferior de 3px `--accent`; navegación con flechas izquierda/derecha. El panel asociado lleva `role="tabpanel"`. |
| **Chip de tipo** | Píldora 12–13px 700 con el nombre del tipo y los colores de la tabla «Colores por tipo de acción». |
| **Badge de estado del plan / de la acción** | Píldora con texto y los colores de «Estados del plan y de la acción». Las acciones canceladas llevan además el badge «Cancelada». |
| **Calendario mensual** | Rejilla de 7 columnas (Lun–Dom) con título del mes, botones «Mes anterior», «Mes siguiente» y «Hoy» (44px); celdas con el número del día y, debajo, un chip por acción coloreado por tipo, con el nombre truncado y `title`; el día actual con borde `--accent`; más de 3 acciones en un día muestran «+N más». Cada chip es un botón que abre el detalle de la acción. Bajo 640px la rejilla pasa a una lista por día. Los días de otros meses van atenuados. Cada chip mide al menos 24×24px (WCAG 2.5.8) y las canceladas van tachadas además de llevar el nombre del tipo; el día actual se marca con borde `--accent` y `aria-current="date"`. |
| **Panel de detalle de acción** | Panel lateral (como el del Catálogo) con nombre, badge de tipo y de estado, competencias, fechas, formador, inscritos/capacidad y costo. |
| **Tarjeta de acción (colaborador)** | Card con nombre (h3), `Chip` de tipo, competencia, fechas, formador, badge de estado y botón «Ver detalle». La cancelada muestra el motivo en `--muted`. |
| **Barra de progreso del plan** | Misma barra que el indicador de cobertura (`role="progressbar"` con `aria-valuenow/min/max` y `aria-label`; relleno `--accent` sobre `--border`) con el texto «% del plan completado». |
| **Selector de participantes** | `Combobox` de búsqueda por colaborador con el contador «n/capacidad» (`--muted`; en `--warn` al llegar al máximo, con el botón de agregar deshabilitado) y lista de inscritos con botón «Quitar» por fila; el aviso de cruce de fechas (`--warn`, icono y texto) se muestra junto al participante. |
| **Historial del plan** | Lista cronológica (más reciente primero): badge de tipo (Estado, Participante, Cancelación, Aprobador), usuario y fecha (13px `--muted`), detalle «anterior → nuevo» y el motivo si existe. |

**Componentes de Micro-learning** *(feature 016; referencias `MicroAdmin.dc.html`, `MicroColaborador.dc.html`, `MicroReproduccion.dc.html` y `MicroQuiz.dc.html`)*

| Componente | Especificación |
|---|---|
| **Tarjeta de micro-contenido** | `article` con borde superior de 4px del color del tipo: miniatura (o imagen genérica por tipo, `role="img"`), `Etiqueta de tipo` y `Badge` de estado, código, título (h3), «Apto para conocimientos» (13px 600 `--accent`), primera competencia con «+N», duración, completaciones y botones de 44px por estado (Vista previa, Editar, Publicar/Archivar/Reactivar, Historial y Eliminar solo sin progreso). Cuadrícula `repeat(auto-fill, minmax(280px, 1fr))`. |
| **Etiqueta de tipo** | Píldora 13px 700 con el nombre del tipo y los colores de la tabla «Colores por tipo y estado de micro-contenido». |
| **Constructor de preguntas** | `fieldset` con la leyenda «Preguntas de opción múltiple (n/20)»; cada pregunta es una tarjeta `--bg` con enunciado (contador n/500), de 2 a 5 opciones con radio «correcta» y texto (n/300), IconButton Quitar y los controles Subir/Bajar (patrón *Reordenar*) con un `role="status"` que anuncia el cambio. En solo lectura (`fieldset disabled`) con `Alert` neutro si el quiz ya tiene intentos terminados. |
| **Reproductor (marcador de posición)** | Caja 16:9 `--neutralsoft` con ícono, tiempo `m:ss / m:ss`, botón primario Reproducir/Pausar o «Reanudar desde m:ss» y un `input range` con `aria-valuetext`. No hospeda video. |
| **Opción de quiz con retroalimentación** | Botón de 44px con letra en círculo; tras responder, la correcta con borde `--accent` y fondo `--soft` y la elegida errónea con `--danger`/`--dangersoft`, ambas con texto («Opción correcta», «Tu respuesta»); mensaje «Correcto»/«Incorrecto» en `role="status"`. |
| **Tarjeta de aprendizaje** | Como la de micro-contenido para el colaborador: estado (Sin iniciar, En progreso, Completado), motivo de asignación, posición de retoma, último puntaje «N de M» y botones Iniciar/Continuar/Ver y «Descargar certificado». |
| **Resumen de aprendizaje** | Cuatro `StatCard` en `dl` (`minmax(180px, 1fr)`): completados, en progreso, horas acumuladas y certificados. |

**Componentes de Actividades formativas** *(feature 017; referencias `ActividadesAdmin.dc.html`, `ActividadesAsistencia.dc.html`, `ActividadesCalificaciones.dc.html` y `ActividadesColaborador.dc.html`)*

| Componente | Especificación |
|---|---|
| **Control de asistencia** | Grupo (`role="group"`, `aria-label` con el nombre del participante) de tres botones de 40px «Presente», «Ausente» y «Justificado» con `aria-pressed`; la opción elegida lleva el color de su asistencia con borde (`--accent`, `--danger`, `--warning`). Pulsar de nuevo la elegida vuelve a Pendiente. Deshabilitado si la actividad no admite registro. |
| **Campo de calificación** | `input type="number"` de 40px de alto y 80px de ancho, mín. 1, máx. 5, paso 1, con etiqueta accesible por participante; deshabilitado salvo con Presente; el error aparece debajo con `role="alert"`. |
| **Resumen de asistencia** | Lista de chips (píldoras 13px 700): Presentes, Ausentes, Justificados y Sin registrar, cada uno con su cantidad. |
| **Tarjeta de actividad** | `article` de `--surface` con borde `--border` y radio 12px: título (h3), `Etiqueta de tipo` y badge de estado, aviso de proximidad («Es hoy», «Empieza mañana», «Empieza en N días», «Está en curso») y lista de datos (fecha, lugar o enlace, formador, asistencia y calificación). Nunca muestra observaciones. |
| **Resumen del colaborador** | Cuatro tarjetas (`minmax(180px, 1fr)`): actividades inscritas, finalizadas, % de asistencia y calificación promedio; «—» cuando no hay datos. |

**Foco visible** en todo elemento interactivo: `outline: 2px solid var(--accent); outline-offset: 2px`.

---

## 5. Patrones de pantalla

### Listado
Referencia: `Competencias.dc.html`. Para cualquier catálogo o lista de registros.

1. Encabezado: h1 + subtítulo a la izquierda; botón primario "Nuevo/Nueva …" con icono + a la derecha.
2. Barra de filtros: SearchInput (flexible, mínimo 280px) + selects de filtro.
3. DataTable con acciones por fila (IconButtons de editar y, según el recurso, eliminar o Activar/Desactivar, con `aria-label` que incluye el nombre del registro).
4. Pagination debajo.
5. Estados de carga, vacío y error según el patrón Estados, ocupando el lugar de la tabla.
6. Eliminar siempre pasa por ConfirmDialog.
7. Los recursos que nunca se eliminan (p. ej. competencias) muestran una columna **Estado** con Badge (Activo en estilo normal, Inactivo en variante neutra) y un botón de texto **Activar/Desactivar** que alterna según el estado actual. **Desactivar** pasa por ConfirmDialog (título como pregunta con el nombre entre comillas y la consecuencia); **Activar** actúa sin confirmación. La barra de filtros añade un select **Estado** (Activas por defecto, Inactivas, Todas) solo para quien gestiona el recurso.

8. Los recursos cuyo detalle es una **lista de viñetas por grado** (p. ej. comportamientos) usan el mismo botón de la fila (p. ej. "Visualizar comportamientos") para desplegar una fila con los cuatro grados A–D, cada uno con su título (`h3`) y sus textos como viñetas `<ul><li>`. Si el recurso depende de otro que está inactivo (p. ej. la competencia asociada), el despliegue y el formulario muestran el **Alert neutro** "La competencia asociada está inactiva" y el recurso sigue consultable y editable.

9. Cuando el detalle es una **lista fija de textos numerados** (p. ej. las cuatro preguntas de una competencia), la fila desplegable muestra un título (`h3`) y una **lista ordenada** `<ol>` con los textos en su orden, con el mismo aviso neutro si el recurso depende de algo inactivo.

10. Cuando el detalle de cada fila es la **definición completa de un recurso** (p. ej. un puesto), **toda la fila es el botón** que la despliega (`h2` con botón `aria-expanded` y `aria-controls`, y panel `role="region"` con `aria-label`), con chevron a la derecha; solo una fila permanece abierta. Referencia: `Puestos.dc.html`. El panel muestra bloques en tarjetas `--surface`: Misión (texto), Funciones (`<ol>` con el tipo de cada una en `Badge`), Requisitos (`<ul>` agrupado por tipo, con un `h4` por tipo) y Competencias (tabla con competencia, tipo y grado requerido en insignia `--accent`; las inactivas con `Badge` neutro). Los botones **Editar** y **Activar/Desactivar** van al final del panel, solo para quien gestiona el recurso; Desactivar pasa por ConfirmDialog y, si el backend lo rechaza, el diálogo muestra el motivo con la cantidad. El filtro de estado y la paginación siguen el punto 7. El mensaje de lista sin coincidencias es "Ningún puesto coincide con la búsqueda.".

11. Cuando el listado es de una **estructura de reporte** (p. ej. posiciones; referencia `Posiciones.dc.html`) la fila desplegable sigue el punto 10 con estas particularidades: columnas Código, Puesto, Unidad, **Reporta a** (código y, debajo, puesto; "—" y "Máxima autoridad" en la máxima autoridad), **Ocupante** ("Sin ocupante" atenuado y en cursiva), **Estado** (Badge: Ocupada normal, **Vacante** advertencia, **Suprimida** peligro) y Modalidad. Bajo la búsqueda ("Código, puesto, unidad u ocupante") va el contador de resultados ("11 posiciones") en una región `aria-live`. La barra de filtros añade el select **Estado** (Vigentes por defecto, Ocupada, Vacante; Suprimida y Todas solo para el Administrador), un select **Unidad organizacional** con las unidades en orden de árbol con sangría y el botón **Limpiar filtros**. El panel muestra tarjetas Ocupante ("Sin ocupante"), Supervisor (código, puesto y ocupante), Supervisa a, Puesto, Unidad (nombre, tipo y estado) y, solo para el Administrador, Auditoría. Editar y Suprimir van al final del panel, solo para el Administrador y no en posiciones Suprimidas; Suprimir se muestra deshabilitado con su motivo como descripción accesible (ocupada o con N subordinadas). Crear y editar usan el patrón **Formulario en modal**; en el selector de unidad las inactivas van deshabilitadas con "La unidad está inactiva" la unidad raíz va deshabilitada con "La Gerencia General ya tiene su posición (máxima autoridad)" si ya la tiene; el supervisor se habilita al elegir la unidad, con opciones "código · puesto · ocupante" agrupadas (`optgroup`) por unidad desde la elegida hasta la raíz, y queda fijo en "Sin supervisor (máxima autoridad)" si la unidad es la raíz. Al editar, las unidades que dejarían subordinadas en una unidad superior van deshabilitadas con "Tiene subordinadas en unidades superiores a esta". Suprimir pasa por ConfirmDialog (definitivo) y, si el backend lo rechaza, muestra el motivo. El mensaje sin coincidencias es "Ninguna posición coincide con los filtros.".
11. **Matriz formativa** (catálogo formativo, Administrador). Referencia: `CatalogoAdmin.dc.html`. Encabezado (h1 + subtítulo) y aviso neutro "El diccionario de competencias es de solo lectura"; **indicador de cobertura** arriba; barra de filtros con `SearchField` y selects de tipo, área formativa, con/sin cursos asociados y estado (este último solo para el Administrador, Activas por defecto); `DataTable` con columnas competencia (código y nombre), tipo, área formativa, cursos asociados, micro-contenidos asociados y la acción **Ver detalle**, que abre el **panel lateral**. Los contadores cuentan solo recursos disponibles. No hay botón para cambiar de vista: cada usuario tiene un solo rol.

12. **Tarjetas agrupadas por área** (catálogo formativo, Colaborador). Referencia: `CatalogoColaborador.dc.html`. Sección destacada **"Recomendados para cerrar mis brechas"** arriba (solo si hay recomendados), y debajo un grupo por área formativa (el último, "Sin área formativa") con **tarjetas de competencia**; al elegir una se abre el detalle con los grados A–D, los cursos y los micro-contenidos disponibles (con la nota de que son aptos para conocimientos, no para competencias conductuales).

13. **Asistente de generación de DNF** (Administrador). Referencia: `DNFAdmin.dc.html`. Encabezado (h1 «Detección de necesidades formativas» + subtítulo) y **Stepper** de dos pasos. *Paso 1 – Selección*: radiogroup de alcance (Un colaborador / Por departamento), `Combobox` de colaborador con búsqueda o `SelectField` de departamento, vista previa y botón primario «Detectar necesidades»; la masiva abre un **ConfirmDialog no destructivo** (botón primario, no de peligro) que dice «Se evaluarán N colaboradores». *Paso 2 – Diagnóstico*: título con el alcance y la fecha, enlace «Cambiar selección», KPI, avisos (Alert neutro) de competencias omitidas, sin evaluación y colaboradores excluidos, resumen por colaborador (solo masiva), gráfico, tabla de diagnóstico y el botón «Generar Plan de Formación». Estados: cargando (Skeleton), error con Reintentar y sin colaboradores evaluables (EmptyState).

14. **Historial de DNF** (Administrador). Patrón Listado (`Competencias.dc.html`): barra de filtros con fechas desde/hasta, `Combobox` de colaborador, `SelectField` de departamento y de prioridad (parámetros en la URL); `DataTable` con código, fecha, alcance, colaborador o departamento, cantidad de brechas, prioridad más alta (Badge) y «Ver detalle»; paginación. Sin editar ni eliminar. El detalle (`/dnf/:id`) muestra el paso 2 en solo lectura, con autor y fecha de generación y, si existe, el enlace al plan generado.

15. **Mi diagnóstico** (Colaborador). Referencia: `DNFColaborador.dc.html`. Encabezado con el puesto, KPI, leyenda «Cómo leer las barras» («A es el nivel más alto · Diferencia negativa = te falta»), **Mis brechas prioritarias**, «Otras brechas detectadas» y «Competencias que ya cumples». Sin DNF o sin brechas: mensaje informativo (EmptyState) en lugar de tarjetas.

### Listado en árbol
Referencia: `Unidades.dc.html`. Para recursos jerárquicos (p. ej. unidades organizacionales). Reutiliza el patrón Listado con estas particularidades:

1. **Cadena de tipos** sobre el listado: una fila de **Chips de nivel** (§4) ordenados desde el nivel 1, visible para todos; el botón "Editar tipos" solo para quien gestiona el recurso.
2. Columnas: **Nombre**, **Código**, **Tipo** (Badge), **Estado** (Badge: Activa normal, Inactiva neutra) y **Acciones** (solo quien gestiona). No hay columna de unidad padre: la jerarquía la da el árbol.
3. **Sangría de 24px por nivel** en la celda del Nombre. Las unidades con dependientes llevan delante un botón de **expandir / contraer** (`IconChevronRight`, `aria-expanded`, `aria-label` con el nombre); las demás, un espacio del mismo ancho. Cada nombre lleva un prefijo visualmente oculto "Nivel N: " para que la jerarquía no dependa de la sangría ni del color.
4. **Orden jerárquico**: cada unidad seguida de su rama; hermanas por código ascendente (numérico). Al entrar solo se ve la raíz, contraída; acciones **Expandir todo** y **Contraer todo**.
5. **Búsqueda** ("Buscar por código, nombre o tipo", sin mayúsculas ni tildes): muestra las coincidencias con las unidades de las que dependen, todo expandido y con los botones de expandir deshabilitados mientras haya búsqueda. Lo mismo con un filtro de estado distinto de Activa. Al limpiar vuelve la expansión previa.
6. **Resumen** `role="status"`: "N unidades · M activas" o "N coincidencias". Sin paginación. Sin coincidencias: "Ninguna unidad coincide con la búsqueda."
7. **Filtro de estado** (Activa por defecto, Inactiva, Todas) solo para el Administrador.
8. **Advertencia "No sigue la cadena"**: `IconAlertTriangle` junto al Tipo con la descripción accesible "No sigue la cadena: bajo «tipo de la unidad padre» corresponde «tipo esperado»".
9. Acciones por fila (solo Administrador): IconButton **Editar**, **Agregar subunidad** (deshabilitado en inactivas y en el último nivel, con "Este tipo no admite subunidades") y **Botón de estado de fila** (§4). Nunca hay Eliminar. Todo botón deshabilitado lleva su motivo como descripción accesible.
10. Tras cada acción, un mensaje de confirmación `role="status"` sobre el listado. Estados cargando, vacío y error según el patrón Estados.
12. Cuando la acción de la fila **abre la página de detalle** (p. ej. colaboradores), la última columna es **Acciones** con un enlace de texto «Ver ficha» (`aria-label` con el nombre del registro) en lugar del botón de edición; el filtro de estado ofrece «Todos» y solo los estados presentes. La fuente laboral caída se muestra como «Información no disponible» en las columnas Puesto, Unidad y Estado, sin ocultar las filas.

### Formulario
Referencia: `Formulario.dc.html`. Para crear y editar.

1. Breadcrumb ("Listado / Nuevo …" o "Listado / Editar …").
2. h1 con la acción ("Nueva competencia", "Editar competencia").
3. Card con los campos en una columna, ancho máximo 760px.
4. Si al guardar hay errores: Alert general arriba ("Revisa los campos marcados antes de guardar.") + error en cada campo.
5. Grupos de campos relacionados en `fieldset` con `legend` (por ejemplo, los 4 grados).
6. Acciones al final, alineadas a la derecha: Cancelar (secundario, vuelve al listado) y Guardar (primario).
7. Cuando la cantidad de textos es fija (p. ej. cuatro preguntas), el formulario usa **campos de texto numerados fijos** (Input con etiqueta "Pregunta 1"…"Pregunta 4", contador `n/máximo` debajo y error por campo), sin Agregar ni Quitar; no se usa la Lista de campos.
8. El **formulario de un puesto** usa cuatro `fieldset` en una sola Card de 760px: Datos generales (código en solo lectura o "Se asigna al guardar", nombre, descripción y misión con contador), Funciones (Fila de tipo y texto con Subir/Bajar), Requisitos (Fila de tipo y texto) y Competencias (Fila de competencia). Cada lista admite de 1 a 20 filas. Los errores de lista (p. ej. "Incluye al menos una función esencial.") se muestran bajo el `legend` del grupo y los de fila junto al campo. Referencia: `PuestosFormulario.dc.html`.

### Formulario en modal
Referencia: `Unidades.dc.html`. Para formularios cortos que se abren sobre el listado sin cambiar de ruta, dentro del componente **Modal** (§4).

1. Título (h2) y cierre con "Cancelar" o Escape; al abrir, el foco va al primer campo y al cerrar vuelve al botón que lo abrió.
2. **Crear unidad**: Nombre (contador `n/150`), Unidad padre (selector en orden de árbol; inactivas y del último nivel deshabilitadas, estas con "Este tipo no admite subunidades") y un campo de solo lectura "Se creará como: «tipo»" o "Selecciona una unidad padre para determinar el tipo". Desde "Agregar subunidad", la padre viene preseleccionada.
3. **Editar unidad**: Nombre y Unidad padre limitada a las activas del tipo inmediatamente superior (en la raíz, deshabilitada con "Ninguna (unidad raíz)"); en solo lectura, un bloque `<dl>` con Tipo, Código, fechas, cantidades y auditoría (solo Administrador).
4. Errores 422 junto al campo y alerta general; conflicto de edición con "Recargar"; botón "Guardar unidad" bloqueado mientras guarda. Nombre de unidad duplicado: permitido.

### Editar tipos (cadena)
Referencia: `Unidades.dc.html`. Modal con la lista de tipos por nivel.

1. Cada fila: número de nivel, nombre editable (contador `n/50`), **asa de arrastre** y flechas **subir / bajar** (IconButtons con `aria-label` "Subir «tipo»", "Bajar «tipo»"; deshabilitadas en los extremos). La fila raíz lleva `IconLock` y la descripción "El tipo raíz solo puede renombrarse", sin asa ni flechas.
2. **Vista previa** de la cadena actual y la resultante, con los tipos que cambian de nivel o de nombre resaltados (fondo `--soft` y texto, no solo color).
3. "Guardar cambios" deshabilitado sin cambios. Errores de nombre por fila.
4. **Bloqueo de reordenamiento**: modal encima con la cantidad y la lista de unidades afectadas (tipo y nivel nuevos; padre con su tipo y nivel nuevos), "Ver en el árbol" por unidad y "Volver a editar" que conserva el borrador.
5. **Nuevo tipo**: modal con "Nombre del tipo" y la indicación de que se agrega al final como hijo del último nivel.
6. **Desactivar en cascada**: diálogo (patrón Estados) que nombra la unidad; con dependientes, las lista y el botón dice "Desactivar N unidades"; con posiciones vigentes en la rama explica el bloqueo con las unidades y cantidades y no permite confirmar.
### Detalle de un registro
Referencia: `ColaboradorFicha.dc.html`. Para consultar un registro con varios grupos de datos (p. ej. la ficha de un colaborador).

1. Breadcrumb ("Listado / Nombre del registro").
2. Encabezado: h1 con el nombre, línea con el código y el estado (Badge neutro) y, a la derecha, las acciones permitidas (**Editar** solo para quien gestiona el recurso; no se dibujan acciones de funcionalidades que aún no existen).
3. Aviso de éxito (`role="status"`) tras crear o guardar.
4. Secciones (Pestañas de sección) y, dentro de cada una, grupos en Cards con Pares etiqueta-valor; la trayectoria es una Línea de tiempo.
5. Estados: cargando (Skeleton), «No registrado», «Información no disponible» con Reintentar por sección, registro inexistente (ErrorState sin Reintentar útil, con enlace al listado), sin permisos y éxito.
6. El **formulario de ficha** usa cuatro `fieldset` en una Card (Identificación con el código en solo lectura o «Se asigna al guardar», Datos personales, Contacto y Contacto de emergencia), contadores `n/máximo` en los textos con límite, Alert general «Revisa los campos marcados antes de guardar.» y, ante duplicado, **Alert con enlace**.

### Formulario de ingreso
Referencia: `ColaboradorIngreso.dc.html` (patrón Formulario). Para registrar el vínculo y la asignación inicial de una ficha.

1. Breadcrumb («Colaboradores / Nombre / Registrar ingreso») y h1 «Registrar ingreso» (o «Registrar reingreso» si la ficha está Desvinculada).
2. Un `fieldset` en Card con **Fecha de ingreso** (pasada, actual o futura), **Tipo de contrato** (Select con las 4 opciones del sistema) y **Posición** (Selector de posición).
3. Con fecha futura aparece el **Alert neutro** «Ingreso programado». Cambiar la fecha vuelve a cargar las opciones y, si la posición elegida deja de ser elegible, la descarta con un Alert neutro.
4. Rechazos (posición ocupada, vínculo superpuesto, contrato no habilitado, Estructura no verificable) usan el **Alert de rechazo con motivo**; el formulario conserva lo escrito y no hay cambio optimista del estado.
5. Acciones: «Confirmar ingreso» (primario, bloqueado mientras confirma) y «Cancelar» (vuelve a la ficha sin crear nada).

### Estados
Referencia: `Estados.dc.html`. Toda vista que carga datos debe cubrir los cuatro.

| Estado | Contenido |
|---|---|
| Cargando | Skeleton con la forma del contenido (filas de tabla, tarjetas) |
| Vacío | Icono en círculo `--soft`, título corto, explicación y, si aplica, botón primario para crear |
| Error | Icono en círculo `--dangersoft`, título "No se pudo cargar …", explicación y botón secundario "Reintentar" |
| Confirmación destructiva | ConfirmDialog |

### Inicio (dashboard)
Referencia: `Main.dc.html`. Saludo (h1) + subtítulo; grilla de StatCards (`minmax(220px, 1fr)`); debajo, "Actividad reciente" (2/3) y "Accesos rápidos" (1/3).

### Páginas públicas
Referencias: `Login.dc.html`, `Landing.dc.html`. Sin app shell. Login: tarjeta centrada de 420px como máximo, con marca encima, sobre `--bg`. Por ahora solo tema claro.

### Plan de formación anual *(feature 013)*
Referencias: `PlanAnualAdmin.dc.html` y `PlanAnualColaborador.dc.html`. Se omiten los controles de prototipo («Ver como…», menú con Empleados/Ajustes).

1. **Planes de formación (Listado)**: patrón Listado (`Competencias.dc.html`) con columnas código, nombre, año, estado (badge), acciones, costo total, aprobador y botones por estado; filtros año, estado y búsqueda por nombre; botón primario «Nuevo plan».
2. **Acciones formativas (Calendario y Lista)**: `Tabs` «Calendario» y «Lista» con filtros comunes (estado del plan, tipo, competencia, desde, hasta, «Limpiar filtros») sobre las pestañas; Lista como tabla con paginación; Calendario como se define en §4. Por defecto el año actual.
3. **Asistente de 3 pasos**: `Stepper` (Datos generales, Acciones formativas, Participantes y aprobador); cada paso es un formulario con el patrón Formulario; «Guardar borrador» siempre visible; el paso 2 lista las acciones con Editar y Quitar y un formulario de acción (modal o panel) con selección múltiple de competencias y selector «Partir de un curso del catálogo»; el paso 3 usa el selector de participantes por acción y el `SelectField` del aprobador.
4. **Detalle del plan**: patrón Detalle de un registro; bloques datos generales, acciones (con costo y horas totales) e historial; botones de estado según el estado del plan.
5. **Mi plan (colaborador)**: encabezado con la barra de progreso, «Próximas actividades» (hasta 3) y lista cronológica de tarjetas de acción; mensaje vacío «Aún no tienes acciones formativas asignadas».
6. **Estados**: toda vista implementa loading (Skeleton), vacío, error con «Reintentar» y éxito; los diálogos de motivo y de confirmación usan `Modal`/`ConfirmDialog`.

### Micro-learning *(feature 016)*
Referencias: `MicroAdmin.dc.html` (repositorio, formulario, vista previa), `MicroColaborador.dc.html` (Mi aprendizaje), `MicroReproduccion.dc.html` (video, PDF y artículo) y `MicroQuiz.dc.html` (quiz). Se omiten los controles de prototipo («Ver como…», menú con Empleados/Ajustes).

1. **Repositorio (cuadrícula)**: encabezado con «+ Nuevo Contenido», `Alert` neutro de alcance en conocimientos, búsqueda por título y filtros (tipo, estado —«Borrador y Publicado» por defecto—, competencia, «Limpiar filtros») y `Paginación`.
2. **Formulario (Modal de 640px)**: título, tipo (bloqueado al editar), competencias activas con `Combobox` y chips «Quitar», duración, URL o constructor de preguntas, miniatura; pie con Vista previa, Cancelar, «Guardar borrador» y «Guardar y publicar».
3. **Vista previa (Modal)**: el contenido tal como lo ve el colaborador, resuelto en el cliente, sin progreso ni intentos.
4. **Mi aprendizaje**: resumen, `Tabs` «Asignado (n)» y «Completado (n)» (en la URL) y cuadrícula de tarjetas de aprendizaje; mensaje por pestaña vacía.
5. **Reproducción y quiz**: ancho máximo 900px; reproductor o visor, botón «Marcar como completado» (oculto si ya completado), quiz de una pregunta a la vez con «Pregunta N de M» y resultado «N de M» con «Reintentar quiz».
6. **Estados**: toda vista implementa loading (Skeleton), vacío, error con «Reintentar» y éxito; archivar y eliminar usan `ConfirmDialog`.

---

### Actividades formativas *(feature 017)*
Referencias: `ActividadesAdmin.dc.html` (lista y formulario en modal), `ActividadesAsistencia.dc.html` (registro de asistencia), `ActividadesCalificaciones.dc.html` (calificaciones) y `ActividadesColaborador.dc.html` (Mis actividades). Se omiten los controles de prototipo («Ver como colaborador», menú con Empleados/Ajustes) y el panel «Fundamento de cada tipo».

1. **Lista (Administrador)**: encabezado con «+ Nueva Actividad», pestañas «Actividades» y «Calificaciones» (en la URL), búsqueda por nombre, filtros (tipo, estado, departamento, competencia, plan, rango de fechas —año actual por defecto— y «Limpiar filtros») y tabla con código, nombre, tipo, fechas, lugar o enlace, formador, departamento, capacidad, inscritos, estado y las acciones «Asistencia» y «Editar». No hay botón de eliminar ni de cancelar.
2. **Formulario (Modal de 860px)**: selector de la acción del plan (con filtro por plan), datos heredados en solo lectura y solo lugar o enlace y departamento editables; al editar, la acción es fija.
3. **Registro de asistencia**: encabezado con datos y estado de la actividad, resumen de asistencia, «Marcar todos presentes», tabla de inscritos (control de asistencia, calificación 1–5 y observaciones), «Guardar» con confirmación si cambia un registro previo y acceso al historial. Con la actividad Programada, Cancelada o el plan Cerrado la vista queda deshabilitada con su explicación.
4. **Calificaciones**: sub-pestañas «Por actividad» y «Por participante», filtros de fechas, tipo, departamento y plan, y detalle por actividad de un participante.
5. **Mis actividades (Colaborador)**: resumen, «Próximas actividades» e «Historial» con tarjetas; mensaje informativo si no hay actividades.
6. **Estados**: toda vista implementa loading (Skeleton), vacío, error con «Reintentar» y éxito.

---

## 6. Iconos

Se usa **Tabler Icons** (`@tabler/icons-react`), trazo 1.75, 20px en el menú y 18px en botones. Los SVG de los mockups son de referencia; al implementar se usan sus equivalentes de Tabler:

| Uso | Icono |
|---|---|
| Inicio | `IconHome` |
| Competencias | `IconAward` |
| Puestos | `IconFileText` |
| Empleados | `IconUsers` |
| Vacantes | `IconBriefcase` |
| Ajustes | `IconAdjustmentsHorizontal` |
| Buscar | `IconSearch` |
| Nuevo | `IconPlus` |
| Editar | `IconPencil` |
| Eliminar | `IconTrash` |
| Advertencia o error | `IconAlertTriangle` |
| Tema | `IconMoon` / `IconSun` |
| Desplegar | `IconChevronDown` |
| Unidades (menú) | `IconSitemap` |
| Indicador de dependencia (árbol) | `IconCornerDownRight` |
| Expandir / contraer rama | `IconChevronRight` |
| Tipo raíz bloqueado | `IconLock` |
| Asa de arrastre | `IconGripVertical` |
| Subir / bajar tipo | `IconArrowUp` / `IconArrowDown` |

Un icono que solo acompaña texto lleva `aria-hidden="true"`. Un botón que solo tiene icono lleva `aria-label`.

---

## 7. Contenido y tono

- Español neutro, tratando al usuario de **tú** ("Revisa los campos", "Inténtalo de nuevo").
- Botones con verbo + objeto: "Nueva competencia", "Guardar competencia", "Publicar vacante". Nada de "OK" ni "Aceptar".
- Errores: qué pasó + qué hacer. Nunca códigos técnicos ni mensajes del servidor sin traducir.
- Confirmaciones destructivas: nombran el elemento entre comillas y avisan si no se puede deshacer.
- Fechas relativas en actividad reciente ("Hace 2 h", "Ayer").

---

## 8. Cuándo diseñar una pantalla nueva

Solo si la pantalla tiene una interacción que ningún patrón cubre. Por ejemplo:

- ranking y ternas de candidatos (comparar lado a lado, ordenar);
- evaluación de un candidato o empleado por competencias y grados;
- reportes y dashboards de trazabilidad con gráficos.

En ese caso, se agrega como una pantalla nueva **en el mismo canvas de Claude Design** (para heredar los tokens), se exporta aquí y se añade a la tabla de patrones si es reutilizable.

---

## 9. Diferencias conocidas entre mockups y specs

| Mockup | Spec vigente |
|---|---|
| `Competencias.dc.html` no muestra el botón "Visualizar grados" | La spec lo pide: cada fila despliega los 4 grados |
| La búsqueda dice "por nombre o descripción" | Solo por nombre |
| Usa "Clasificación" y "Grados de dominio" | Términos de la spec: "Tipo" y "Grados de desarrollo" |
| `Login.dc.html` muestra "¿Olvidaste tu contraseña?" y "Mantener la sesión iniciada" | Fuera de alcance en la autenticación mínima; no se implementan |
| Inputs con borde `--border` o `#C9CED6` | Usar `--input-border` |
| Menú de usuario con "Mi perfil" y "Ajustes" | Fuera de alcance en la autenticación mínima; solo nombre, rol y "Cerrar sesión" |
| Páginas públicas "por ahora solo tema claro" | Tema claro/oscuro persistido también en el login |
| `Competencias.dc.html` solo muestra nombre, descripción y tipo por fila | Cada fila ofrece "Visualizar grados", que despliega una fila con el código, la descripción completa y los cuatro grados; la descripción de la tabla se recorta a 2 líneas |
| `Formulario.dc.html` no define contadores | Los campos de texto con límite (nombre 50, descripciones 500) muestran un contador `n/máximo` bajo el campo |
| `Estados.dc.html` muestra el diálogo de confirmación aislado | El diálogo de eliminar es un `alertdialog` modal con foco atrapado, Escape y Cancelar que cierran, y muestra el motivo si el servidor rechaza (competencia en uso) |
| `Competencias.dc.html` no muestra Código ni Estado, ni botón Activar/Desactivar | La spec pide columnas Código, Nombre, Descripción, Tipo y Estado (badge), botón Activar/Desactivar (alterna su texto) y filtro de estado solo para el Administrador; las competencias nunca se eliminan |
| `Estados.dc.html` confirma solo eliminaciones | Desactivar una competencia confirma con el texto "La competencia no podrá asignarse en nuevos registros."; activar no confirma |
| `Competencias.dc.html` y `Formulario.dc.html` no tienen un selector con búsqueda ni campos de texto repetibles | Se añaden **Combobox** (selector de competencia al crear comportamientos) y **Lista de campos** (1 a 4 comportamientos por grado) en §4; el listado de comportamientos reutiliza la estructura de `Competencias.dc.html` con Código y Estado, sin Activar/Desactivar/Eliminar (el estado lo gestiona el módulo de competencias) |
| `Estados.dc.html` solo define alertas de error | Se añade el **Alert neutro** para avisos informativos ("La competencia asociada está inactiva") |
| `Formulario.dc.html` no define textos numerados ni listas de resultados numeradas | El formulario de preguntas usa cuatro campos numerados fijos con contador (§5 Formulario, punto 7) y el listado despliega una lista ordenada `<ol>` (§5 Listado, punto 9); reutiliza Combobox y Alert neutro de 002 |
| `Puestos.dc.html` usa "Clasificación" en la tabla de competencias | La spec usa "Tipo" |
| `Puestos.dc.html` usa "Conocimientos" como tipo de requisito y una competencia "Gerencial" | Los tipos de requisito son Formación, Experiencia y Capacitación; los de competencia, Cardinal y Específica |
| `Puestos.dc.html` lista las funciones sin tipo y los requisitos como `Tipo: texto` sin agrupar | Las funciones muestran su tipo (Esencial o Complementaria) y los requisitos se agrupan por tipo |
| `Puestos.dc.html` no incluye filtro de estado, paginación, Editar, Activar/Desactivar, diálogo de confirmación ni indicador de competencia inactiva | La spec los pide (los de gestión, solo para el Administrador): ver §5 Listado, punto 10 |
| `Puestos.dc.html` no tiene formulario y su menú trae Organigrama y Perfilamiento | El formulario es `PuestosFormulario.dc.html` (§5 Formulario, punto 8); Organigrama y Perfilamiento son otras features y no se implementan en 004 |
| Los demás tableros no definen filas repetibles de tipo y texto, reordenación ni fila de competencia | Se añaden en §4: Fila de tipo y texto, Reordenar (Subir/Bajar) y Fila de competencia |
| `Unidades.dc.html` (antes de 005) traía un selector **Estado** en el formulario y el botón **Eliminar** con la nota "Una unidad solo se puede eliminar…" | La spec 005 no permite elegir el estado en el formulario ni eliminar: el tablero se corrigió con **Desactivar / Reactivar** (Desactivar con diálogo de confirmación como en `Estados.dc.html`), sin campo Estado y con la nota "Una unidad solo se puede desactivar si no tiene unidades dependientes activas ni posiciones asignadas." |
| `Unidades.dc.html` mostraba Nueva unidad y las acciones a todos y no tenía filtro de estado ni datos de solo lectura | Nueva unidad, Editar, Desactivar / Reactivar y el filtro de estado (Activa, Inactiva, Todas) son solo del Administrador (el tablero lo alterna con la propiedad `admin`); al editar se muestran en solo lectura código, fechas, cantidades y auditoría, y "Ninguna (unidad raíz)" se deshabilita si ya existe otra raíz activa (§5 Listado en árbol y Formulario en línea) |
| `Unidades.dc.html` dejaba a **Operaciones (UO-008)** inactiva con una posición asignada | Una unidad solo se desactiva sin posiciones Ocupadas ni Vacantes: Operaciones está inactiva y solo conserva posiciones **Suprimidas**, que no lo impiden |
| `Unidades.dc.html` trae en el menú Organigrama, Perfilamiento, Empleados, Vacantes y Ajustes, y una columna sin **Nivel N** para lectores de pantalla | Esos ítems pertenecen a otras features y no se implementan en 005 (el menú real solo añade "Unidades"); la implementación añade el prefijo oculto "Nivel N" y usa `--input-border` en los controles |
| `Unidades.dc.html` (revisión 2026-10-15) | La spec revisada añade la cadena de tipos, el árbol expandible con Expandir / Contraer todo, la búsqueda con ancestras, "No sigue la cadena", modales (Crear, Editar, Nuevo tipo, Editar tipos, Bloqueo), tipo calculado y no elegido, sin columna de unidad padre, desactivación en cascada con confirmación siempre y raíz no desactivable; el tablero se actualiza en esa línea |
| `Posiciones.dc.html` (tablero del usuario) solo muestra el listado con búsqueda y badges; no tiene filtros de estado y unidad, "Limpiar filtros", paginador, filas desplegables, Editar, Suprimir ni modales; muestra el POS-0305 Suprimido por defecto | La navegación real incluye "Posiciones" tras "Unidades"; las modalidades son Nombramiento, Contrato, Encargo y Subrogación (los mockups anteriores hablaban de Híbrida, Presencial y Remota); la unidad es propia de la posición, el supervisor es otra posición (código `POS-####`) y no se permiten posiciones sobre puestos o unidades inactivos |
| `CatalogoAdmin.dc.html` y `CatalogoColaborador.dc.html` traen los botones "Ver como colaborador" y "Ver como administrador" | Cada usuario tiene un solo rol (spec 005, FR-005b): no se implementan |
| `CatalogoAdmin.dc.html` muestra "Cobertura por área formativa", la columna "Nivel requerido" y el tipo "Gerencial" | No están en la spec 005: se omiten; solo el indicador global de cobertura, y los tipos son Cardinal y Específica |
| `CatalogoAdmin.dc.html` dice "Ver sin cursos" y "Los cambios se guardan al instante" | El enlace pasa a "Ver sin cobertura" (competencias sin ningún curso ni micro-contenido disponible) y los cambios se confirman con el botón **Guardar**, con advertencia de cambios sin guardar |
| `CatalogoAdmin.dc.html` no define selector de área, gestión de áreas, historial, última configuración, marca de archivado ni aviso de competencia inactiva | Se añaden en el panel lateral reutilizando `SelectField`, `Alert – neutro`, `Badge` neutro y `ConfirmDialog` (§4 y §5 punto 11) |
| `CatalogoColaborador.dc.html` muestra el puesto del usuario, "Para empezar", estados "En curso/Completado" y "Continuar", y la leyenda "Niveles A–D" con "nivel requerido" en cada tarjeta | Se omiten (requieren módulos no implementados o no están en la spec): solo **Iniciar** o **Ver** (deshabilitado con "Próximamente" sin destino); el nivel requerido solo aparece en Recomendados y en el detalle cuando hay brecha |
| Los menús de los tableros del catálogo traen Perfilamiento, Empleados y Ajustes | Pertenecen a otras features: el menú real solo añade el ítem **Formación** |
| `GP-01_Fichas-de-colaboradores.html` titula «Fichas de colaboradores» y usa «Estado laboral», «Editar datos» y el nombre de la organización en el header | La spec usa «Colaboradores», «Estado» y «Editar»; el nombre de la organización no se implementa en 005 |
| `GP-01_Fichas-de-colaboradores.html` muestra el correo bajo el nombre en la tabla | FR-022 lista código, nombre, puesto, unidad, estado y la acción; el correo no se muestra |
| `GP-01_Fichas-de-colaboradores.html` incluye formación académica, experiencia anterior, movilidad, documentos, categorías documentales, contratación desde Selección, exportación y una barra de «Controles del prototipo» | Pertenecen a GP-02, GP-03, GP-05, GP-06 y GP-07; 005 solo implementa Personal y puesto, y reserva Desarrollo y Documentos |
| `ColaboradorFicha.dc.html` | Tablero del detalle de ficha, creado en 005 (Constitución III); no existía un patrón de detalle con secciones |
| `DNFAdmin.dc.html` y `DNFColaborador.dc.html` calculan la **prioridad por porcentaje** de brecha (Crítica ≥ 66 %, Alta ≥ 50 %, Media ≥ 33 %, Baja < 33 %) y lo explican en una nota al pie | **Manda la spec 006 (FR-010)**: la prioridad se decide por grados de diferencia (3 Crítica, 2 Alta, 1 Media si la competencia es Cardinal o Baja si es Específica); el porcentaje es solo un dato derivado. El texto «Cálculo: …» se muestra con la regla de la spec y el cálculo siempre lo hace el backend. Los tableros son solo referencia visual (sus cifras son de ejemplo) |
| `DNFAdmin.dc.html` trae los botones «Ver como colaborador», la franja «Datos de solo lectura», el menú con Perfilamiento, Empleados y Ajustes, y «Última evaluación de desempeño» en la vista previa | Controles de prototipo u otras features: no se implementan. Cada usuario tiene un solo rol; el menú real solo añade el ítem **DNF**; la evaluación de desempeño no existe en la spec |
| `DNFAdmin.dc.html` muestra el estado del plan en el historial como «Plan activo» / «Borrador» y una oferta del catálogo formativo por brecha | La DNF solo conoce si **generó o no** un plan y su enlace; el estado del plan lo gestiona Plan de Formación. La oferta formativa no se muestra en la vista del administrador (la ve el colaborador con «Ver cursos sugeridos») |
| `DNFAdmin.dc.html` no tiene detalle de DNF, filtro por rango de fechas ni de prioridad en el historial, ni confirmación de la masiva como tal | Se definen en §5 puntos 13 y 14; el detalle reutiliza el paso 2 en solo lectura |
| `DNFColaborador.dc.html` dice «Avisaremos a Desarrollo y Formación para que lo incorpore» cuando no hay cursos | No hay notificación en la spec: solo se muestra «Aún no hay cursos asociados en el catálogo.» |
| `DNFColaborador.dc.html` trae «Ver como administrador», «Mi perfil» y «Ajustes» en el menú | No se implementan (un solo rol; fuera de alcance) |
| `GP-02_Registrar-ingreso.html` presenta el registro como un diálogo modal sobre la ficha | La spec define una pantalla de formulario (`/colaboradores/:id/ingreso`) con el patrón Formulario, para que el foco, el teclado y WCAG 2.2 AA queden cubiertos sin diálogos anidados |
| `GP-02_Registrar-ingreso.html` incluye formación académica, experiencia anterior, movilidad, documentos y la barra de «Controles del prototipo» | Pertenecen a GP-03, GP-05 y a la propia demostración; GP-02 solo registra el vínculo y la asignación inicial |
| `GP-02_Registrar-ingreso.html` no muestra la jefatura de la posición | La spec la pide en solo lectura; mientras Estructura no la publique se muestra «No registrado» |
| `ColaboradorIngreso.dc.html` | Tablero del formulario de ingreso, creado en 006 (Constitución III) |
| `PlanAnualAdmin.dc.html` pide en el paso 1 «Inicio del período», «Fin del período», «Presupuesto estimado (USD)» y «Objetivo del plan» | Manda la spec 013 (FR-002): solo nombre, año y descripción; el período es el año |
| `PlanAnualAdmin.dc.html` ofrece «Precargar acciones desde el último DNF» | Se omite: el borrador desde la DNF lo crea la DNF mediante la Facade del módulo (FR-029) |
| `PlanAnualAdmin.dc.html` asigna «Participantes por departamento» | La spec pide participantes por acción, con búsqueda por colaborador, capacidad usada y avisos de cruce junto al participante |
| `PlanAnualAdmin.dc.html` elige una sola «Competencia objetivo» y no tiene curso del catálogo ni duración | La spec pide una o más competencias, curso opcional que propone tipo/formador/competencias y duración en horas |
| `PlanAnualAdmin.dc.html` muestra KPI de avance de ejemplo | Solo se muestran cantidad de acciones, costo total y horas totales (FR-021) |
| `PlanAnualColaborador.dc.html` usa los estados «Completada», «Inscrito» y «Por confirmar», «Tu avance %» y «Lugar o plataforma» | La spec usa el estado de la acción (Programada, En curso, Finalizada, Cancelada), solo el «% del plan completado» general y no registra lugar o plataforma |
| `PlanAnualColaborador.dc.html` no muestra acciones canceladas | La spec aclarada (FR-022) las muestra con estado «Cancelada» y su motivo, fuera del progreso y de las próximas actividades |
| `PlanAnualAdmin.dc.html` y `PlanAnualColaborador.dc.html` traen «Ver como colaborador/administrador», «Empleados», «Ajustes» y «Mi perfil» | Controles de prototipo o de otras features: no se implementan (un solo rol por persona) |
| `GP-03_Desarrollo-profesional.html` muestra el estado de la formación con insignias de color propias (`b-info`, `b-ok`, `b-off`), fechas como «Inicio no registrado · Fin no registrado», «Sin observaciones» y el bloque «Trayectoria interna» dentro de la pestaña Desarrollo, y edita en un diálogo con `form` propio | La pestaña usa el `Badge` del sistema (normal para En curso y Completada, neutro para Incompleta y movilidad no disponible o no informada), fechas como «Inicio: … · Fin: No registrado», «No registrado» en observaciones ausentes y los diálogos dentro de `Modal`. La trayectoria interna sigue en GP-01/GP-02 y no se duplica en esta pestaña; el estado vacío dice «Formación académica no registrada» y «Experiencia anterior no registrada» |
| `GP-05_Documentos-del-colaborador.html` (pantalla Categorías documentales) muestra la columna «Documentos», el botón «Editar nombre», Desactivar sin explicación y Reactivar sin confirmación, y no pagina | El catálogo no consulta documentos, así que no hay columna «Documentos»; el botón es «Editar»; Desactivar confirma con «Sus documentos se conservarán» y Reactivar confirma con «No se reactivan documentos archivados»; el listado se pagina (20 por página) y conserva búsqueda, estado y página en la URL; los diálogos usan `Modal` y `ConfirmDialog` |
| `GP-05_Documentos-del-colaborador.html` (pestaña Documentos) muestra la vista previa embebida del PDF, y el Colaborador solo accede desde Mi ficha | «Abrir» muestra el PDF en una pestaña con un blob local obtenido con el cliente HTTP autenticado (no hay URL permanente ni vista previa embebida) y «Descargar» fuerza el nombre `Documento_{id}.pdf`; el Colaborador dispone de la página `/mis-documentos` porque el permiso documental es independiente del de ficha (la pestaña de Mi ficha se mantiene); la vista de archivados es un conmutador Disponibles/Archivados solo para el Administrador, sin acciones de escritura; el cambio de visibilidad y el archivo no se actualizan hasta la respuesta del servidor |
| `GP-01_Fichas-de-colaboradores.html` (acción de exportación) permite exportar desde la ficha y desde Mi ficha | «Exportar ficha» aparece en el detalle administrativo y, para el Colaborador, en `/exportar-mi-ficha` (y en Mi ficha si puede consultarla) porque el permiso de exportación es independiente; obtiene el PDF como blob con el cliente autenticado, muestra «Generando PDF…», deshabilita el botón mientras dura la petición, no descarga respuestas que no sean PDF, ofrece Reintentar ante 503 y oculta la acción ante 403; el PDF no se agrega a Documentos |
| `MicroColaborador.dc.html` indica «Aprueba un quiz con 70 % o más para obtener tu primer certificado», muestra un panel «Mis certificados» y un resultado aprobado/reprobado | No hay puntaje mínimo: completar un intento da el certificado. Se omite el panel; el certificado se descarga desde la pestaña Completado y se cuenta en el resumen. El resultado es «N de M» con un mensaje neutro |
| `MicroAdmin.dc.html` y `MicroQuiz.dc.html` fijan 4 opciones (A–D) por pregunta | Entre 2 y 5 opciones por pregunta con exactamente una correcta; el constructor permite agregar y quitar |
| `MicroAdmin.dc.html` asocia una sola competencia con un select simple | Selección múltiple de competencias activas |
| `MicroAdmin.dc.html` filtra el estado con Todos, Publicado y Archivado | Por defecto Borrador y Publicado («Borrador y Publicado»); opciones Borrador, Publicado, Archivado y Todos |
| `MicroAdmin.dc.html` trae «Ver como colaborador», tarjetas KPI y un bloque largo «Úsalo para conocimientos» con una cita | Cada persona tiene un solo rol: sin «Ver como…»; sin KPI; la nota de alcance es un `Alert` neutro breve |
| `MicroAdmin.dc.html` ofrece «Archivar en su lugar» en el diálogo de eliminar, incluso con completaciones | Con progreso de colaboradores el botón Eliminar no se ofrece; el diálogo de eliminar solo aparece en contenidos sin progreso |
| `MicroColaborador.dc.html` muestra un porcentaje de avance en la tarjeta | La tarjeta muestra el estado, la duración, la posición de retoma de un Video y el último puntaje «N de M» de un Quiz |
| `ActividadesAdmin.dc.html` crea la actividad con nombre, tipo, competencia, fechas, horas, formador, capacidad y campos de codesarrollo, mentoring y OJT | La actividad nace de una acción del plan: el modal elige la acción (datos heredados en solo lectura) y solo edita lugar o enlace y departamento |
| `ActividadesAdmin.dc.html` ofrece «Cancelar» por fila y «Ver como colaborador» | La actividad no se cancela aquí (se cancela la acción en el plan) y cada persona tiene un solo rol |
| `ActividadesAdmin.dc.html` filtra por tipo sin E-learning y sin código, competencia, plan ni rango de fechas | Se agregan el tipo E-learning, la columna Código y los filtros de competencia, plan y fechas (año actual por defecto), además del badge «Asistencia pendiente» |
| `ActividadesAsistencia.dc.html` admite calificaciones con pasos de 0,5 | La calificación es un entero de 1 a 5, solo con asistencia Presente; las observaciones solo con asistencia registrada |
| `ActividadesColaborador.dc.html` muestra las observaciones del formador en la tarjeta | Las observaciones son de uso interno y nunca se muestran al colaborador |
