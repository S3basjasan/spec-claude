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
| **Badge** | Píldora, fondo `--soft`, texto `--accent`, 13px, 600, padding 3px 10px. Variante **neutra** para estados inactivos: fondo `--border`, texto `--muted` (contraste ≥ 4.5:1 en ambos temas). Un estado se muestra siempre con texto, nunca solo con color. |
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
| **Skeleton** | Bloques `--border` con animación de pulso (1.4s); imita la forma del contenido real. |
| **EmptyState / ErrorState** | Ver patrón Estados. |
| **Breadcrumb** | 14px `--muted`, separador "/", último ítem en `--text`. |
| **StatCard** | Card con label (14px `--muted`), valor (32px 700) y nota (13px `--muted`). |

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
