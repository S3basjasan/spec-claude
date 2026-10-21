# Research: Diccionario de competencias unificado

Todas las decisiones parten de lo ya construido en 001, 002 y 003 (revisado en el repositorio) y de las aclaraciones de la spec. No quedan `NEEDS CLARIFICATION`.

## R1. Cómo evolucionar el esquema existente

- **Decisión**: migraciones **nuevas** (fecha posterior a `2026_10_22_*`) que transforman las tablas y trasladan los datos; no se editan las migraciones antiguas.
- **Razón**: FR-012 exige conservar las competencias existentes y marcar inactivas las incompletas, algo que una migración editada con `migrate:fresh` no puede hacer. Hay datos reales de 001–003 y tablas de otros módulos que apuntan a `competencias` y a `competencia_grados`.
- **Alternativas**: editar migraciones y volver a sembrar (pierde datos; descartada); script manual fuera de las migraciones (no reproducible).
- **Orden**: (1) escalas y su catálogo, (2) `competencia_grados` a `tipo_grado_valor_id`, (3) categorías, (4) comportamientos y preguntas sin cabecera, (5) `puesto_competencias`, (6) historial de escala, (7) regla de completitud sobre el estado. Cada paso es reversible solo en estructura; el traslado de datos se prueba con Pest (ver R14).

## R2. Modelado de la escala por competencia

- **Decisión**: catálogo `tipo_grados` + `tipo_grado_valores` (según el DBML) con tres tipos sembrados: **Letras** (A, B, C, D; activo), **Números** (Nivel 1 a Nivel 4) y **Categorías** (Excelente, Muy buena, Buena, Normal). Cada `competencia_grados` apunta a un `tipo_grado_valor_id`; los cuatro grados de una competencia son siempre del mismo tipo y cubren los órdenes 1 a 4. El tipo marcado `activo` es solo la escala predeterminada del formulario de creación.
- **Razón**: el DBML ya define este modelo y permite cambiar la escala sin recrear filas, de modo que `puesto_competencias.competencia_grado_id` (y las referencias de otros módulos) siguen válidas.
- **Unicidad del tipo activo**: índice único **parcial** (`WHERE activo = 1`) creado con `DB::statement`, que SQLite soporta, más una comprobación en la aplicación como respaldo. Se descarta la columna generada del DBML por complicar la migración en SQLite sin ganancia.
- **Alternativas**: letra en `competencia_grados` más columna de escala en `competencias` (más simple, pero se aparta del DBML y duplica el catálogo de nombres).
- **Quién cambia el tipo activo**: fuera del alcance (spec, Assumptions); solo el seeder lo fija.

## R3. Nombres de categoría propios por competencia

- **Decisión**: tabla nueva `competencia_categorias` (`competencia_id`, `orden` 1–4, `nombre` ≤ 20, `nombre_clave`) con únicos `(competencia_id, orden)` y `(competencia_id, nombre_clave)`. Hay **cuatro filas o ninguna** por competencia. La etiqueta de un grado en escala Categorías es el nombre de su fila en `competencia_categorias`; si la competencia no tiene filas, se usa el valor por defecto de `tipo_grado_valores`. Los nombres se conservan al cambiar a otra escala (spec, US5-7).
- **Razón**: los valores del catálogo son globales y no pueden ser por competencia. Una tabla pequeña y propia evita ensuciar el catálogo.
- **Longitud 20**: coincide con `tipo_grado_valores.valor` (20) y se añadió a FR-007 para que spec y plan queden alineados.
- **Escritura**: los endpoints reciben `categorias` (4 textos); si la escala es Categorías es obligatorio; si no, es opcional y, si no se envía, las filas guardadas no se tocan. Aplicar la escala Categorías a una competencia sin filas no crea ninguna (usa los valores por defecto).
- **Alternativas**: un `tipo_grado` por competencia (rompe la unicidad del nombre y la regla de un solo tipo activo); JSON en `competencias` (no consultable ni validable por restricciones).

## R4. Edición concurrente (FR-035)

- **Decisión**: una sola columna `competencias.version` (ya existe, empieza en 1) que **sube con cualquier escritura** sobre la competencia: datos y grados, comportamientos, preguntas, escala y estado. Los `PUT` reciben la `version` que cargó el formulario; si no coincide con la actual se responde `409` con "Esta competencia cambió mientras la editabas. Recarga para ver la versión actual." Se reutiliza la excepción `ConflictoDeVersion` y su manejo en el controlador.
- **Razón**: la spec cuenta como cambio también el estado y la escala; un contador único es la forma más simple de cubrir las tres secciones y los cambios de estado. Las cabeceras de registro con su propia `version` desaparecen con las tablas "registro".
- **`PATCH …/estado` y `PATCH …/escala` no exigen versión**: son acciones puntuales con destino explícito y `422` si ya están en ese valor; solo incrementan la versión. Así un cambio de estado concurrente hace fallar el siguiente guardado de formulario, que es lo que pide la spec, sin obligar a los botones a llevar versión.
- **Bloqueo atómico**: dentro de la transacción, `UPDATE competencias SET version = version + 1 WHERE id = ? AND version = ?`; si afecta 0 filas, conflicto. Evita la condición de carrera entre leer y escribir.
- **Cambio respecto de 001**: allí el cambio de estado no alteraba la versión; ahora sí (el spec lo exige).

## R5. Competencias existentes incompletas (FR-012)

- **Decisión**: una competencia está **completa** si tiene exactamente 4 grados, 16 comportamientos (4 por grado) y 4 preguntas con texto. En la migración, toda competencia que no lo sea y esté Activa pasa a Inactivo, con una fila en `competencia_estado_historial` (`user_id` nulo) y `version` + 1. Después, `PATCH …/estado` a `Activo` valida la completitud y responde `422` con "No se puede activar: faltan los comportamientos y las preguntas." (lista exactamente lo que falte).
- **Edición de una competencia incompleta**: los formularios de comportamientos y preguntas exigen todos los campos, por lo que guardar una sección la completa; las demás secciones siguen como estaban. La respuesta de consulta incluye `completa` y `faltantes`, y el admin ve un aviso neutro en la tarjeta.
- **Alternativas**: mantener Activas las incompletas y solo marcarlas (contradice FR-012); bloquear la migración si hay incompletas (impide desplegar).

## R6. Grado requerido en puestos (FR-034)

- **Decisión**: `puesto_competencias.competencia_grado_id` (FK a `competencia_grados`) sustituye a `grado_requerido`. Migración: se añade la columna, se rellena haciendo coincidir `(competencia_id, grado_requerido)` con el grado de esa competencia por posición (A→1 … D→4), se hace obligatoria y se elimina `grado_requerido`.
- **Contrato externo**: el API de puestos y la Facade siguen devolviendo `grado_requerido` como **clave posicional A–D** (orden 1–4), más la etiqueta calculada, para no romper a `Desarrollo` (`PerfilesDePuestoDesdeEstructura` usa `nivel_requerido`) ni a otros consumidores. Las reglas de `ReglasDeCompetenciasDePuesto` siguen validando "el grado existe para la competencia".
- **Razón**: el cambio de escala no debe alterar asignaciones; un identificador posicional estable lo garantiza, y la etiqueta se resuelve solo al mostrar.
- **Alternativas**: exponer `competencia_grado_id` en el API de puestos (cambia el contrato de varios módulos y el formulario de puestos sin necesidad en esta feature).

## R7. Historial

- **Decisión**: se conserva `competencia_estado_historial` y se añade `competencia_escala_historial` (`competencia_id`, `escala_anterior`, `escala_nueva`, `user_id`, `created_at`; solo inserción). Se registra la escala por su nombre de tipo.
- **Razón**: es coherente con lo ya construido en 001 y evita migrar el historial a la tabla genérica `auditoria_cambios` del DBML, que ninguna otra tabla del módulo usa hoy.
- **Alternativas**: una tabla genérica `competencia_historial` con `campo` (obliga a migrar historial existente).

## R8. Forma del API

- **Decisión**: una sola API bajo `/api/competencias`:
  `POST /`, `GET /`, `GET /{id}`, `PUT /{id}`, `PUT /{id}/comportamientos`, `PUT /{id}/preguntas`, `PATCH /{id}/escala`, `PATCH /{id}/estado`, más `GET /api/tipo-grados`. El detalle devuelve todo en una sola respuesta. Sin `DELETE`, sin `competencias-disponibles`, sin `/api/comportamientos` ni `/api/preguntas`.
- **Razón**: coincide con las tres modales de edición del diseño y con la atomicidad de la creación; una sola petición de detalle sirve las tres pestañas.
- **Orden por posición**: los textos de grados, comportamientos y preguntas llegan como arreglos; el orden es la posición (a partir de 1; el primer grado es el más alto).

## R9. Visibilidad y listado sin filtro de estado

- **Decisión**: el listado no tiene filtro de estado en la interfaz. El API conserva `estado` (opcional) para consumidores internos: para el administrador, sin `estado` devuelve activas e inactivas; para los demás, siempre `Activo` aunque se pida otro valor. Cada elemento trae `completa` para que el administrador vea las incompletas.
- **Razón**: spec FR-018 y FR-020; mantener `estado` en el API no cuesta nada y no afecta a la interfaz.

## R10. Paginación (decisión que la spec dejó al plan)

- **Decisión**: se conserva la paginación del servidor y el paginador del design system, con 12 tarjetas por página (múltiplo de dos columnas), máximo 50, y el número de página en la URL. El recuento bajo la cuadrícula es el total de resultados, no el de la página.
- **Razón**: con cientos de competencias, cargarlas todas es frágil, la constitución exige persistir la paginación en la URL y el diseño de listados del repositorio ya la define. El mockup no tenía paginador porque solo mostraba siete.
- **Alternativas**: sin paginación (riesgo de listas largas); "cargar más" (componente nuevo sin necesidad).

## R11. Compatibilidad de la Facade

- **Decisión**: `EstructuraFacade` conserva las firmas de 001–003 y los consumidores actuales siguen funcionando: `getCompetencia`, `getCompetenciasPorIds`, `existeCompetencia`, `estaActiva`, `getCompetenciasActivas`, `getTodasLasCompetencias`, `getComportamientos*`, `getPreguntas*`, `tieneComportamientosActivos`, `tienePreguntasActivas`. `grados` sigue siendo el mapa A–D por posición; se añaden `escala`, `grados_detalle` (id, orden, etiqueta, descripción, comportamientos) y `completa`. Las dos funciones de "activos" exigen competencia activa **y** completa.
- **Razón**: Compensaciones, Desarrollo y GestionPersonal leen estas funciones; así la unificación no obliga a tocarlos.
- **Alternativas**: renombrar a un contrato nuevo (cambios en tres módulos sin valor para esta feature).

## R12. Diseño (constitución III)

- **Decisión (ya aplicada)**: añadir `design/Diccionario.dc.html`, el artboard del zip, y **no** sobrescribir `design/Competencias.dc.html`, porque este es el patrón genérico «Listado (tabla de datos)» que `DESIGN.md` referencia en otras cuatro secciones y en `canvas.json`. El artboard nuevo incorpora las desviaciones documentadas en la spec: dos columnas, descripción en la pestaña Competencia, ocultar acciones de administrador al resto, campo Tipo en la edición, diálogo de confirmación al desactivar, estados de carga y error, y aviso de contenido incompleto. Actualizar `design/DESIGN.md` (listados con tarjetas y pestañas, formulario completo, escala, avisos).
- **Razón**: la constitución exige actualizar primero el diseño y registrar la desviación en la spec o el plan.
- **Nota**: el artboard del zip no incluye diálogo de confirmación ni estados de carga/error; salen del design system de otros módulos.

## R13. Vista previa de escala

- **Decisión**: la vista previa es solo del cliente: se calculan las etiquetas con `GET /api/tipo-grados` y los nombres de categoría de la competencia (o los valores por defecto), sin llamar al servidor. Aplicar la escala llama a `PATCH …/escala`.
- **Razón**: no guarda nada (spec US5-1) y no genera tráfico; el catálogo es pequeño y estable (caché larga).

## R14. Estrategia de pruebas

- **Pest (Feature)**: creación atómica (todo o nada); unicidad por `nombre_clave`; cada PUT con `version` correcta, desfasada (409) y tras un cambio de estado; escala (mismas filas, etiquetas, historial); categorías (4 o ninguna, conservación); activar con contenido incompleto (422) y completo; visibilidad por rol; ausencia de `DELETE` y de las rutas retiradas; Facade (firmas y claves nuevas); puestos con `competencia_grado_id` y clave posicional.
- **Pest (migración de datos)**: base con competencias completas, incompletas, con y sin puestos asignados; tras migrar, completas intactas, incompletas inactivas con historial, `puesto_competencias` correcto y tablas "registro" eliminadas.
- **Pest (Unit)**: reglas de completitud, de categorías y de etiquetas.
- **Vitest**: validación Zod, formulario con cambio de escala (rótulos), vista previa, pestañas con teclado, modales y avisos, mapeo de 422 y 409.
- **E2E (Playwright)**: crear una competencia completa, ver sus tres pestañas, editar comportamientos, previsualizar y aplicar escala, desactivar con confirmación y comprobar que el usuario no administrador no la ve.
