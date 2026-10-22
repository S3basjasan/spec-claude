# Research: Organigrama de posiciones

Decisiones del plan. Donde el insumo `plan-organigrama.md` choca con el repositorio real, prevalece lo que aquí se decide.

## D-01 Endpoint único de lectura, sin permisos nuevos

- **Decision**: `GET /api/organigrama` con `auth:sanctum` solamente, sin parámetros ni rutas de escritura. Va en `Estructura/Routes/api.php`, junto a las de posiciones.
- **Rationale**: Todos los autenticados ven lo mismo (FR-001) y no existen tablas de roles ni permisos; es el mismo criterio que listar posiciones. El insumo coincide.
- **Alternatives**: Permiso `organigrama.ver` (sin efecto práctico); `/api/estructura/organigrama` (contradice el contrato).

## D-02 Dos listas planas, el árbol en el cliente

- **Decision**: La respuesta trae `posiciones` (vigentes, por código) y `unidades` (activas, en orden de árbol, con `unidad_padre_id` y `nivel_arbol`). El árbol, la búsqueda, el filtro, las ramas y los contadores se calculan en el cliente.
- **Rationale**: Permite buscar, filtrar y contar posiciones ocultas sin más peticiones (supuesto 2 del spec) y el panel se abre sin otra petición. Es la decisión del insumo y encaja con hasta 2000 posiciones.
- **Alternatives**: Árbol anidado en el servidor (respuesta más grande y rígida para el filtro); carga por ramas (descartada en el spec para este tamaño).

## D-03 Solo vigentes, excluidas en la consulta

- **Decision**: `PosicionRepositoryInterface::vigentes()` consulta `estado in (Ocupada, Vacante)`. Las Suprimidas nunca salen del servidor.
- **Rationale**: FR-003: ningún usuario las ve; filtrar en el cliente las expondría en la respuesta.
- **Alternatives**: Filtrar en el cliente (inseguro y contra el spec).

## D-04 Nombres de ocupantes por el puerto, no por Eloquent

- **Decision**: El caso de uso reúne los `user_id` de las posiciones y los traduce con `NombresDeUsuariosInterface::nombres()` (una llamada), igual que 006. No se usa la relación `Posicion → User` del insumo.
- **Rationale**: La constitución prohíbe que un módulo lea las tablas de otro; el puerto ya existe.
- **Alternatives**: `with('ocupante')` (viola la regla de módulos).

## D-05 Comprobación del árbol en el servidor

- **Decision**: Servicio de dominio puro `ComprobadorDeArbol` sobre los `NodoDePosicion` vigentes: exactamente una raíz (sin supervisor), todas las posiciones alcanzables desde ella y sin ciclos. Si falla, `OrganigramaInconsistente` (con los ids afectados) → el controlador registra el detalle en el log y responde 500 con un mensaje genérico. Sin posiciones vigentes responde 200 con listas vacías.
- **Rationale**: FR-006 pide estado de error si los datos no forman un único árbol, sin corregirlos; las reglas las garantiza 006, pero el organigrama no debe dibujar un árbol roto en silencio. `ArbolDeSupervision` de 006 no detecta raíces múltiples ni inalcanzables, y cambiarlo mezcla responsabilidades.
- **Alternatives**: Solo en el cliente (duplica lógica); reutilizar y ampliar `ArbolDeSupervision` (acopla 006 con 007).

## D-06 Datos de la respuesta

- **Decision**: Cada posición incluye `id`, `codigo`, `estado`, `modalidad`, `posicion_jefe_id`, `puesto {id, codigo, nombre, descripcion}`, `unidad {id, codigo, nombre, tipo, estado}` y `ocupante {id, nombre}|null`. Cada unidad: `id`, `codigo`, `nombre`, `nivel_arbol`, `unidad_padre_id`. No se incluye auditoría ni correo.
- **Rationale**: Lo que dibujan las tarjetas y el panel; sin datos sensibles.

## D-07 Consulta eficiente

- **Decision**: Una consulta de posiciones vigentes con `join` de puesto, unidad y tipo (la misma forma que el repositorio de 006), una lectura de unidades (`UnidadRepositoryInterface::todas()` filtrada a activas) y una llamada al puerto de nombres.
- **Rationale**: Sin consultas por fila (rendimiento SC-001).

## D-08 Árbol recursivo en listas anidadas

- **Decision**: `OrganigramaNodo` recursivo con `ul/li` y líneas de conexión en CSS (como en el tablero), sin límite de profundidad.
- **Rationale**: La cadena de unidades tiene cinco niveles y una unidad puede encadenar varias posiciones; el tablero dibuja cuatro niveles con bloques repetidos.
- **Alternatives**: SVG con layout calculado (más código y peor accesibilidad); librería de diagramas (dependencia nueva, requiere aprobación).

## D-09 Estado de ramas, selección y filtros

- **Decision**: El estado es del cliente: conjunto de ids contraídos por la persona (vacío = todo expandido), selección, zoom. Con búsqueda o filtro: se calculan coincidencias (texto **y** unidad cuando ambos están activos), las **ramas forzadas** (ancestros de coincidencias) se muestran abiertas aunque estén contraídas, sus botones se deshabilitan y «Contraer todo» solo contrae las ramas sin coincidencias. Al limpiar, el conjunto de contraídos no cambió. Al elegir una posición desde el panel se abren sus ancestros, sin tocar el conjunto. Contraer una rama con la selección mantiene el panel abierto.
- **Rationale**: Son las clarificaciones del spec (sesión 2026-10-16) y los puntos 4.3 y 3.3 del insumo.

## D-10 Zoom, arrastre y desplazamiento

- **Decision**: Zoom con `transform: scale(z)` (origen arriba a la izquierda) sobre un contenedor cuyo tamaño se ajusta al del árbol escalado, para que las barras de desplazamiento sigan siendo correctas; rango 0,5–1,5 en pasos de 0,1; el porcentaje restablece 100 %. Arrastre con pointer events que mueve `scrollLeft/scrollTop` y se ignora sobre botones, enlaces y campos. El área tiene `tabindex="0"`, `role="region"` y nombre accesible para desplazarla con las flechas. Desplazar a la primera coincidencia o a la selección con `scrollIntoView` (centrado, suave salvo `prefers-reduced-motion`).
- **Rationale**: Sin dependencias; la propiedad CSS `zoom` no es estándar en todos los navegadores.
- **Alternatives**: `zoom` CSS; librería de pan/zoom.

## D-11 Panel de detalle

- **Decision**: `aside` con nombre «Detalle de la posición» a la derecha del árbol desde 1024 px de ancho; por debajo de **1024 px** se muestra con `Modal` de `shared/ui` (capa superpuesta a todo el ancho, foco atrapado, Escape y «Cerrar detalle», foco devuelto a la tarjeta). Se alimenta de la misma respuesta; los enlaces del supervisor y de las subordinadas seleccionan otra posición. Sin auditoría.
- **Rationale**: Clarificación del spec y reutilización de un componente accesible ya existente.

## D-12 Colores y estilos con tokens

- **Decision**: Tarjeta Ocupada: borde sólido `--border` y fondo `--surface`; Vacante: borde discontinuo `--warning` y fondo `--warningsoft`; selección: anillo `--accent`; coincidencia: anillo `--warning`; no coincidente: opacidad 0,35. Badges: `Badge` con las variantes normal (Ocupada) y `warning` (Vacante). Líneas del árbol con `--border`. Los hex del insumo se sustituyen por tokens.
- **Rationale**: Constitución III (una sola representación de tokens); los temas claro y oscuro salen gratis.

## D-13 Interfaz y diseño

- **Decision**: Antes de tocar el frontend se actualiza `design/Organigrama.dc.html` (marcador «Código, puesto, unidad u ocupante», árbol recursivo, área enfocable, estados de carga, vacío y error, leyenda con el aviso de Suprimidas, resumen con filtros, botones de rama «− Contraer» / «+ N posiciones», panel con enlaces) y se documenta el patrón en `DESIGN.md` y `canvas.json`.
- **Rationale**: Constitución III y los «cambios que el tablero necesita» del insumo.

## D-14 Requisito previo: método duplicado en `EstructuraService`

- **Decision**: Antes de implementar hay que reconciliar `EstructuraService::getPosicion` y `getPosicionesPorIds`, declarados dos veces (la versión de 006 devuelve el detalle completo desde `PosicionRepositoryInterface`; la de gestión de personal es un adelanto con otro contrato). PHP no carga la clase. El organigrama no usa estos métodos (lee del repositorio), pero sin arreglarlo no arranca el módulo.
- **Regla por defecto (a confirmar antes de implementar)**: prevalece el contrato de 006; el adelanto de gestión de personal se renombra (`getPosicionDeGestion`, `getPosicionesDeGestionPorIds`) y se actualizan sus consumidores.
- **Rationale**: Es un conflicto entre dos features ya fusionadas; decidir cuál contrato prevalece es de sus dueños.
- **Alternatives**: Renombrar uno de los dos (cambia contratos de otra feature sin acuerdo).

## D-15 Pruebas

- **Decision**: Unit (`ComprobadorDeArbol`: una raíz, varias raíces, inalcanzables, ciclos; caso de uso con dobles en memoria); Feature (endpoint: 401, Suprimidas excluidas, ocupante, árbol inválido da 500, vacío da 200). Vitest para `arbol.ts` (construcción, descendientes, coincidencias con texto y unidad, ramas forzadas y contraíbles, textos de rama) y componentes (tarjeta, nodo, panel, controles); E2E con Playwright del flujo: abrir, buscar, filtrar, contraer/expandir, zoom y detalle con navegación.
- **Rationale**: Constitución IV; la lógica del árbol es donde viven los errores.
