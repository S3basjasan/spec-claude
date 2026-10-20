---

description: "Tareas de la feature 007-organigrama"
---

# Tasks: Organigrama de posiciones

**Input**: Documentos de diseño en `/specs/007-organigrama/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/organigrama-api.md. Requiere 000-login a 006-posiciones ya implementadas (posiciones con `posicion_jefe_id`, ocupante, estado, modalidad, unidad y puesto; `NombresDeUsuariosInterface`; `ArbolDeSupervision`; `UnidadRepositoryInterface::todas()`; `Modal`, `SearchField`, `SelectField`, `Badge`, `EmptyState`, `ErrorState` y `Skeleton` de `shared/ui`).

**Tests**: Incluidos. La constitución exige Pest, Vitest + Testing Library y un E2E real del flujo principal.

**Organization**: Tareas agrupadas por historia de usuario (US1–US5 de spec.md). La feature es de **solo lectura**: no hay migraciones, seeders ni auditoría. El endpoint y la lógica del árbol son compartidos, así que viven en la fase fundacional.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Se puede ejecutar en paralelo (archivos distintos, sin dependencias pendientes)
- **[Story]**: Historia de usuario a la que pertenece
- Rutas relativas a la raíz del repositorio

## Path Conventions

- Backend: `backend/app/Modules/Estructura/…` (Domain, Application, Infrastructure, Presentation)
- Frontend: `frontend/src/modules/estructura/organigrama/…` (reutiliza `frontend/src/shared/ui/…`)
- Pruebas backend: `backend/tests/{Unit,Feature}/Estructura/Organigrama/`
- Pruebas de componentes: `*.dom.test.tsx` junto al código (con `// @vitest-environment jsdom`); lógica pura `*.test.ts`; E2E en `frontend/e2e/organigrama.spec.ts`
- Regla de módulos: `organigrama` **no importa nada** de `frontend/src/modules/estructura/{competencias,comportamientos,preguntas,puestos,unidades,posiciones}`; el rol llega solo por `frontend/src/modules/auth/login`. El enlace «Ver listado de posiciones» es una ruta (`/posiciones`), no un import.
- Valores: `estado` de la posición ∈ `Ocupada` | `Vacante` (las `Suprimida` nunca salen del servidor); `modalidad` ∈ `Nombramiento` | `Contrato` | `Encargo` | `Subrogacion` (la interfaz muestra «Subrogación»).
- Colores con tokens de `DESIGN.md` (`--warning`, `--warningsoft`, `--accent`, `--soft`, `--border`), no con los hex del insumo.
- Lecciones: escribir archivos con acentos con las herramientas Write/Edit (no heredocs con comillas complejas); en E2E localizar con `getByRole(…, { name, exact: true })`; los modales de `shared/ui/Modal` ya manejan foco y Escape; el servidor de desarrollo (`artisan serve`) es de un solo hilo.
- Donde difieren, **prevalecen plan.md y spec.md** sobre el insumo `plan-organigrama.md` (puerto de nombres en lugar de la relación a `users`, tokens en lugar de hex, `Modal` para el panel estrecho).

---

## Phase 1: Setup (línea base, requisito previo y diseño)

**Purpose**: Backend cargando, línea base registrada y diseño actualizado antes de tocar el frontend

- [X] T001 **Requisito previo ajeno a la feature (research D-14)**: reconciliar `EstructuraService::getPosicion` y `getPosicionesPorIds` en `backend/app/Modules/Estructura/Application/Services/EstructuraService.php`, declarados dos veces (la versión de 006 y el adelanto de gestión de personal), de modo que `php -l` pase. **Regla por defecto, a confirmar antes de implementar**: prevalece el contrato de 006 (`getPosicion` devuelve el arreglo con puesto, unidad, supervisor y `ocupante_id`); el adelanto de gestión de personal se renombra (por ejemplo `getPosicionDeGestion` y `getPosicionesDeGestionPorIds`) y se actualizan sus consumidores. Hasta resolverlo no carga el módulo `Estructura`
- [ ] T002 Registrar la línea base (sin cambios de archivos), **después de T001** para que el error fatal no contamine la medición: ejecutar `vendor/bin/pest` y `vendor/bin/pint --test` en `backend/`, y `npm run lint`, `npx tsc -b` y `npx vitest run` en `frontend/`, y anotar los fallos existentes (las pruebas obsoletas de 005 y las de 004 que chocan con 006 pueden fallar y no son de esta feature)
- [ ] T003 [P] Actualizar `design/Organigrama.dc.html` (tablero existente, modificado en tu copia) según los «cambios que el tablero necesita»: marcador de búsqueda «Código, puesto, unidad u ocupante», árbol recursivo (cinco niveles), botones de rama «− Contraer» / «+ N posiciones», leyenda con «Las posiciones suprimidas no se muestran.», resumen sin y con filtros, estados de carga, vacío y error, panel «Detalle de la posición» con enlaces (y su versión superpuesta en 320 px), área del árbol enfocable y ayuda «Arrastra para desplazarte…»; colores con los tokens (Vacante con `--warning`/`--warningsoft`)
- [X] T004 [P] Modificar `design/DESIGN.md` **antes** de cualquier tarea de frontend (constitución III) y revisar `design/canvas.json`: nuevo patrón «Organigrama» en §5 (árbol de tarjetas, botón de rama, zoom, área desplazable, panel de detalle con enlaces, resaltado/atenuación al buscar y filtrar) y las diferencias con el tablero en §9

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Endpoint de lectura y lógica pura del árbol que necesitan todas las historias

**⚠️ CRITICAL**: Ninguna historia puede empezar hasta completar esta fase

### Backend

- [X] T005 [P] Crear `backend/app/Modules/Estructura/Domain/Exceptions/OrganigramaInconsistente.php` (`RuntimeException` con los ids afectados y el motivo: `sin_raiz`, `varias_raices`, `inalcanzables` o `ciclo`; mensaje genérico «No se pudo generar el organigrama.»)
- [X] T006 [P] Crear `backend/app/Modules/Estructura/Domain/Services/ComprobadorDeArbol.php` (puro, sin Laravel, research D-05): a partir de los `NodoDePosicion` vigentes comprueba exactamente una raíz (sin supervisor), que todas las posiciones desciendan de ella y que no haya ciclos; lanza `OrganigramaInconsistente`; con una lista vacía no falla
- [X] T007 Modificar `Domain/Repositories/PosicionRepositoryInterface.php` y `Infrastructure/Persistence/Eloquent/Repositories/EloquentPosicionRepository.php` (`backend/app/Modules/Estructura/`): añadir `vigentes(): array<Posicion>` con `estado in ('Ocupada','Vacante')`, orden por id (equivale al código numérico), con puesto (descripción), unidad (tipo y estado), supervisor y ocupante en una sola consulta (research D-03 y D-07)
- [X] T008 [P] Crear `Application/DTO/{OrganigramaDTO,NodoDeOrganigramaDTO}.php`: nodo con `id`, `codigo`, `estado`, `modalidad`, `posicion_jefe_id`, `puesto {id,codigo,nombre,descripcion}`, `unidad {id,codigo,nombre,tipo,estado}` y `ocupante {id,nombre}|null`; el organigrama con las listas `posiciones` y `unidades` (`id`, `codigo`, `nombre`, `nivel_arbol`, `unidad_padre_id`)
- [X] T009 Crear `Application/UseCases/ObtenerOrganigrama.php`: lee `vigentes()`, comprueba el árbol con `ComprobadorDeArbol`, traduce los `user_id` de los ocupantes con `NombresDeUsuariosInterface::nombres()` (una llamada, research D-04) y añade las unidades **Activas** de `UnidadRepositoryInterface::todas()` en orden de árbol; sin posiciones vigentes devuelve las listas vacías
- [X] T010 Crear `Presentation/Resources/OrganigramaResource.php`, `Presentation/Controllers/OrganigramaController.php` (traduce `OrganigramaInconsistente` a 500 `{"message":"No se pudo generar el organigrama."}` y registra los ids en el log) y añadir la ruta `GET /api/organigrama` con `auth:sanctum` (sin `admin`) en `Routes/api.php`, sin ninguna ruta de escritura

### Frontend

- [X] T011 [P] Crear `frontend/src/modules/estructura/organigrama/api.ts`: tipos propios (`EstadoPosicion` = `Ocupada` | `Vacante`, `NodoDePosicion`, `UnidadDelFiltro`, `Organigrama`) y `obtenerOrganigrama()` sobre el cliente `http` compartido (`GET /api/organigrama`)
- [X] T012 [P] Crear `frontend/src/modules/estructura/organigrama/text.ts` con todos los textos en español (título, subtítulo, «Ver listado de posiciones», resumen con singular/plural y con filtros, leyenda, «Las posiciones suprimidas no se muestran.», «Código, puesto, unidad u ocupante», «Todas las unidades», «Expandir todo», «Contraer todo», «Limpiar filtros», «− Contraer», «+ N posiciones», zoom, ayuda de desplazamiento, panel, «Sin ocupante», «Máxima autoridad», «Ninguna posición le reporta.», estados cargando / vacío / error)
- [X] T013 [P] Crear `frontend/src/modules/estructura/organigrama/nav.tsx` (ítem «Organigrama» con `IconSitemap`) y registrar la ruta `/organigrama` y el ítem en `frontend/src/App.tsx`
- [X] T014 [P] Crear `frontend/src/modules/estructura/organigrama/arbol.ts` (lógica pura, sin React): `construirArbol(posiciones)` (raíz = sin supervisor; hijos ordenados por la parte numérica del código), `descendientes(nodo)`, `unidadesDependientes(unidades, unidadId)`, `coincidencias(posiciones, unidades, busqueda, unidadId)` (texto sin tildes ni mayúsculas **y** unidad cuando ambos están activos, clarificación 1), `ramasForzadas(coincidencias)` (ancestros), `ramasContraibles` y `textoDeRama(n)`; `descendientes` y los contadores se calculan una sola vez por carga (memoizados), para soportar 2000 posiciones («+ 1 posición» / «+ N posiciones»)
- [X] T015 Crear `frontend/src/modules/estructura/organigrama/hooks/useOrganigrama.ts` (consulta `["organigrama"]`, siempre nueva al montar, sin refetch al enfocar la ventana, sin reintentos agresivos) y `hooks/useListadoParams.ts` (`q` y `unidad` en `useSearchParams`)
- [ ] T016 [P] Crear `frontend/src/test/organigramaFixtures.ts` (`posicion()`, `organigrama()` con una máxima autoridad, cinco niveles, Ocupadas y Vacantes, dos ramas, un ocupante de nombre largo, y listas vacía e inconsistente)

### Pruebas de la base

- [ ] T017 [P] Crear `backend/tests/Unit/Estructura/Organigrama/ComprobadorDeArbolTest.php`: una raíz y todas descienden (pasa); lista vacía (pasa); dos raíces; posición inalcanzable; ciclo; una sola posición
- [ ] T018 [P] Crear `backend/tests/Feature/Estructura/Organigrama/ObtenerOrganigramaTest.php`: 401 sin token; 200 igual para Administrador y Usuario; solo Ocupadas y Vacantes (ninguna Suprimida); orden por código (`POS-0009` antes de `POS-0010`); una sola con `posicion_jefe_id` nulo; `ocupante` con nombre o `null`; `descripcion` del puesto y `tipo` y `estado` de la unidad; `unidades` solo Activas en orden de árbol; sin posiciones vigentes 200 con lista vacía; datos inválidos (dos raíces) 500 con el mensaje genérico y sin ids en la respuesta; `POST`, `PUT`, `PATCH` y `DELETE` no existen; volumen de 2000 posiciones en menos de 5 s (SC-001)
- [ ] T019 [P] Crear `backend/tests/Unit/Estructura/Organigrama/ObtenerOrganigramaCasoDeUsoTest.php` con dobles en memoria del repositorio y del puerto de nombres (reutilizar los de 006 si existen) Si no existe (el de 006, T021, no llegó a crearse), crear `backend/tests/Support/InMemoryPosicionRepository.php` con `vigentes()` y un doble del puerto de nombres
- [ ] T020 [P] Crear `frontend/src/modules/estructura/organigrama/arbol.test.ts`: construcción (cinco niveles, orden numérico de hermanas), descendientes y texto de rama, dependientes de una unidad, coincidencias por código, puesto, unidad y ocupante sin tildes, **texto y unidad a la vez coinciden solo si cumplen ambos**, ramas forzadas y ramas contraíbles

**Checkpoint**: El endpoint responde y la lógica del árbol está probada

---

## Phase 3: User Story 1 - Ver el organigrama de la organización (Priority: P1) 🎯 MVP

**Goal**: Árbol de arriba abajo con tarjetas, leyenda, resumen y estados de la vista, igual para ambos roles.

**Independent Test**: Con posiciones vigentes en varios niveles y alguna Suprimida, entrar como Usuario y como Administrador; el árbol, las tarjetas, la leyenda y el resumen son iguales, no hay Suprimidas y las hermanas están ordenadas por código.

### Tests for User Story 1

- [ ] T021 [P] [US1] Crear `frontend/src/modules/estructura/organigrama/pages/OrganigramaPage.dom.test.tsx`: estados cargando, vacío («Aún no hay posiciones vigentes para mostrar el organigrama.») y error con «Reintentar»; título, subtítulo y enlace «Ver listado de posiciones» a `/posiciones`; resumen «N posiciones vigentes · N ocupadas · N vacantes» con singular; leyenda y «Las posiciones suprimidas no se muestran.»; mismo contenido para `admin` y `user`; sin botones de crear, editar ni suprimir
- [ ] T022 [P] [US1] Crear `components/TarjetaDePosicion.dom.test.tsx` y `components/OrganigramaNodo.dom.test.tsx`: tarjeta con unidad, código, puesto, ocupante («Sin ocupante» atenuado), badge de estado con texto; Vacante con borde discontinuo y fondo; hermanas por código; recursión de cinco niveles y una sola tarjeta sin botón de rama

### Implementation for User Story 1

- [X] T023 [P] [US1] Crear `components/TarjetaDePosicion.tsx`: botón con unidad (mayúsculas, color del acento), código, puesto, ocupante y `Badge` (Ocupada normal, Vacante `warning`); Vacante con borde discontinuo `--warning` y fondo `--warningsoft`; `aria-pressed` para la selección
- [X] T024 [US1] Crear `components/OrganigramaNodo.tsx`: componente recursivo con listas anidadas `ul/li` y líneas de conexión en CSS (`--border`), sin límite de profundidad (research D-08); en esta historia sin botón de rama
- [X] T025 [P] [US1] Crear `components/Resumen.tsx` (región `aria-live`: «N posiciones vigentes · N ocupadas · N vacantes», singular «vacante») y `components/Leyenda.tsx` (muestras Ocupada y Vacante y el aviso de las Suprimidas)
- [X] T026 [US1] Crear `pages/OrganigramaPage.tsx` según `design/Organigrama.dc.html` (encabezado, enlace al listado, resumen, leyenda, árbol y estados cargando / vacío / error con `Skeleton`, `EmptyState` y `ErrorState` de `shared/ui`)
- [ ] T027 [US1] Crear el E2E base en `frontend/e2e/organigrama.spec.ts`: el Usuario y el Administrador abren `/organigrama` y ven el mismo árbol, ninguna Suprimida, la Vacante con su estilo, la leyenda y el resumen; el enlace al listado lleva a `/posiciones`; `POST` a `/api/organigrama` responde 404/405

**Checkpoint**: MVP visual del organigrama

---

## Phase 4: User Story 2 - Consultar el detalle de una posición (Priority: P1)

**Goal**: Panel de detalle con navegación al supervisor y a las subordinadas.

**Independent Test**: Seleccionar una posición con supervisor y subordinadas, comprobar el panel y navegar a ambas; una sola selección a la vez; el panel se cierra.

### Tests for User Story 2

- [ ] T028 [P] [US2] Crear `components/PanelDeDetalle.dom.test.tsx`: código, puesto, badge, modalidad con tilde; ocupante o «Sin ocupante»; supervisor como enlace (código, puesto y ocupante) o «Máxima autoridad»; «Supervisa a (N)» con enlaces y «Ninguna posición le reporta.»; puesto y unidad (nombre, tipo y estado); sin auditoría; región «Detalle de la posición»; «Cerrar detalle»
- [ ] T029 [P] [US2] Ampliar `pages/OrganigramaPage.dom.test.tsx`: clic en una tarjeta abre el panel y la resalta; una sola seleccionada; el enlace del supervisor y de una subordinada cambian la selección y expanden la rama; por debajo de 1024 px (probar 1023 y 1024) el panel va dentro de un diálogo con foco atrapado y Escape lo cierra devolviendo el foco

### Implementation for User Story 2

- [X] T030 [US2] Crear `components/PanelDeDetalle.tsx`: `aside` con nombre «Detalle de la posición» y botón «Cerrar detalle»; secciones Ocupante, Supervisor (botón enlace), Supervisa a (N) (botones), Puesto y Unidad organizacional; todo sale de la respuesta ya cargada (research D-11)
- [X] T031 [US2] Crear `hooks/useEstadoDelArbol.ts` (selección: una sola posición, cerrar) y `hooks/useEsPantallaEstrecha.ts` (`matchMedia("(max-width: 1023px)")`, umbral de 1024 px); integrar en `OrganigramaPage.tsx`: `aside` junto al árbol desde 1024 px y `Modal` de `shared/ui` a todo el ancho por debajo; al seleccionar desde el panel se abren los ancestros (ramas forzadas, sin tocar las contraídas por la persona) y el árbol se desplaza hasta la tarjeta (`scrollIntoView` centrado, sin animación si la persona prefiere menos movimiento)
- [ ] T032 [US2] Añadir al E2E el detalle: clic en una tarjeta, comprobar los datos, navegar al supervisor y a una subordinada, cerrar con «Cerrar detalle» y con Escape en 320 px

---

## Phase 5: User Story 3 - Buscar y filtrar por unidad sin perder el contexto (Priority: P2)

**Goal**: Búsqueda y filtro de unidad que resaltan, atenúan y expanden ramas sin recortar el árbol.

**Independent Test**: Buscar por código, puesto, unidad y ocupante y filtrar por una unidad con dependientes; resaltado, atenuación, ramas abiertas, resumen y «Limpiar filtros».

### Tests for User Story 3

- [ ] T033 [P] [US3] Crear `components/BarraDeHerramientas.dom.test.tsx`: etiqueta «Buscar» y marcador «Código, puesto, unidad u ocupante»; selector con «Todas las unidades» y las unidades con sangría; «Limpiar filtros» solo con búsqueda o unidad
- [ ] T034 [P] [US3] Ampliar `pages/OrganigramaPage.dom.test.tsx`: coincidencias resaltadas y el resto con atenuación; búsqueda más unidad coinciden solo si cumplen ambas; ramas con coincidencias abiertas aunque estuvieran contraídas; resumen «N posiciones coinciden de M vigentes» (singular) y «0 posiciones coinciden…» con todas atenuadas; limpiar devuelve las ramas y la selección a como estaban; `q` y `unidad` en la URL

### Implementation for User Story 3

- [X] T035 [US3] Crear `components/BarraDeHerramientas.tsx`: `SearchField` («Buscar», máximo 50 caracteres, retardo de 300 ms al aplicar a la URL), `SelectField` de unidad con sangría por `nivel_arbol`, y «Limpiar filtros» visible solo con búsqueda o unidad
- [X] T036 [US3] En `pages/OrganigramaPage.tsx`, `OrganigramaNodo.tsx` y `TarjetaDePosicion.tsx`: calcular las coincidencias con `arbol.ts` (texto **y** unidad), resaltar con anillo, atenuar (opacidad 0,35) solo mientras hay filtros, abrir las ramas forzadas, mostrar el resumen con filtros y llevar a la vista la primera coincidencia de la búsqueda (por código); no tocar la selección ni las ramas contraídas por la persona
- [ ] T037 [US3] Añadir al E2E: buscar por código, puesto, unidad y ocupante sin tildes; filtrar por una unidad con dependientes; búsqueda más unidad; «0 posiciones coinciden»; limpiar

---

## Phase 6: User Story 4 - Recorrer el árbol: ramas, zoom y desplazamiento (Priority: P2)

**Goal**: Expandir y contraer ramas, acercar y alejar, y desplazar el árbol.

**Independent Test**: Contraer y expandir ramas, «Expandir todo» y «Contraer todo», zoom de 50 % a 150 %, desplazamiento con puntero y teclado.

### Tests for User Story 4

- [ ] T038 [P] [US4] Crear `components/OrganigramaNodo.ramas.dom.test.tsx`: todo expandido al entrar; «− Contraer» y «+ N posiciones» / «+ 1 posición» (directas e indirectas); «Expandir todo» y «Contraer todo» (solo queda la raíz); con filtros, el botón de las ramas con coincidencias está deshabilitado y «Contraer todo» solo contrae las demás (clarificación 4); contraer una rama con la selección mantiene el panel abierto y, al expandir, la tarjeta vuelve resaltada (clarificación 2)
- [ ] T039 [P] [US4] Crear `components/ControlesDeZoom.dom.test.tsx` y `hooks/useArrastre.test.tsx`: pasos de 10 % entre 50 % y 150 %, extremos sin avanzar, el porcentaje restablece 100 %; el arrastre mueve el desplazamiento y se ignora sobre botones, enlaces y campos

### Implementation for User Story 4

- [X] T040 [US4] En `components/OrganigramaNodo.tsx` y `hooks/useEstadoDelArbol.ts`: botón de rama bajo cada tarjeta con subordinadas («− Contraer» / «+ N posiciones»), `aria-expanded` y nombre accesible con el código («Contraer POS-0201», «Expandir POS-0201»); conjunto de ids contraídos por la persona (vacío = todo expandido); visibles = no contraídas o forzadas; deshabilitado en las ramas con coincidencias mientras hay filtros
- [X] T041 [US4] Añadir «Expandir todo» y «Contraer todo» a `components/BarraDeHerramientas.tsx` y a la página (con filtros, «Contraer todo» contrae solo las ramas sin coincidencias)
- [X] T042 [P] [US4] Crear `components/ControlesDeZoom.tsx` (Alejar y Acercar con `IconZoomOut` e `IconZoomIn` —añadirlos a `frontend/src/shared/ui/icons.tsx` si no existen— y el porcentaje, en texto, que restablece 100 %, todos con `aria-label`; 0,5–1,5 en pasos de 0,1) y `components/AreaDelArbol.tsx` (zoom con `transform: scale()` sobre un contenedor ajustado al tamaño escalado, research D-10; alto de 640 px con desplazamiento; ayuda «Arrastra para desplazarte. Haz clic en una tarjeta para ver su detalle.»)
- [X] T043 [P] [US4] Crear `hooks/useArrastre.ts`: pointer events (`pointerdown`, `pointermove`, `pointerup`, `pointerleave`) que mueven `scrollLeft` y `scrollTop`; no se inicia sobre un botón, enlace, campo o selector
- [ ] T044 [US4] Añadir al E2E: contraer y expandir una rama, «Expandir todo» y «Contraer todo», zoom y restablecer, arrastre del árbol y contraer una rama con la selección

---

## Phase 7: User Story 5 - Teclado y lectores de pantalla (Priority: P3)

**Goal**: Toda la interacción accesible con teclado y con nombres y estados correctos.

**Independent Test**: Recorrer el organigrama solo con el teclado; comprobar nombres accesibles, estados y 320 px sin desplazamiento horizontal de la página.

### Tests for User Story 5

- [ ] T045 [P] [US5] Crear `pages/OrganigramaPage.a11y.dom.test.tsx`: cada tarjeta, botón de rama, control de zoom y enlace del panel es un botón real con nombre; `aria-expanded` y «Contraer POS-0201» / «Expandir POS-0201»; el área del árbol es una región enfocable con nombre; el panel tiene el nombre «Detalle de la posición»; el estado se comunica con texto

### Implementation for User Story 5

- [X] T046 [US5] Revisar `components/AreaDelArbol.tsx`, `TarjetaDePosicion.tsx`, `PanelDeDetalle.tsx` y `ControlesDeZoom.tsx`: `tabindex="0"`, `role="region"` y nombre accesible en el área del árbol (desplazable con las flechas), orden de tabulación lógico, foco visible, el foco vuelve a la tarjeta que abrió el panel y las nuevas coincidencias o selección se anuncian sin robar el foco
- [ ] T047 [US5] Añadir al E2E el recorrido con teclado (Tab por tarjetas, ramas, zoom y panel; flechas para desplazar), 320, 1023, 1024 y 1440 px sin desplazamiento horizontal de la página, y los temas claro y oscuro

---

## Phase 8: Polish & Cross-Cutting Concerns

- [ ] T048 [P] Revisar seguridad: 401 sin token; ningún dato de auditoría, correo ni rol en la respuesta; el 500 no revela ids ni trazas; sin rutas de escritura; las Suprimidas se excluyen en la consulta y no en el cliente
- [ ] T049 [P] Auditar contraste y foco de las tarjetas (Ocupada y Vacante), el resaltado, la atenuación y el panel en ambos temas (pares ≥ 4.5:1) y reflejarlo en `frontend/e2e/organigrama.spec.ts`
- [X] T050 [P] Revisar que `design/Organigrama.dc.html` y `design/DESIGN.md` reflejan lo implementado (marcador de búsqueda, leyenda, botones de rama, panel con enlaces)
- [ ] T051 Medir el dibujo de 2000 posiciones (SC-001): prueba de Vitest con un fixture de 2000 posiciones vigentes en cinco niveles, o E2E con tiempo límite, que compruebe que el árbol queda interactivo en menos de 5 s y que contraer, expandir y buscar siguen siendo fluidos con zoom al 150 %
- [ ] T052 Ejecutar las puertas de calidad del backend: `vendor/bin/pint --test` y `vendor/bin/pest` en `backend/` (los únicos fallos admitidos son los registrados en T002)
- [ ] T053 Ejecutar las puertas de calidad del frontend: `npm run lint`, `npx tsc -b`, `npx vitest run`, `npm run build` y `./node_modules/.bin/playwright test` en `frontend/` (los únicos fallos admitidos son los registrados en T002)
- [ ] T054 Ejecutar la validación de `specs/007-organigrama/quickstart.md` de punta a punta

---

## Dependencies & Execution Order

- **Fase 1** primero; T002 bloquea todo el backend de `Estructura`; T003 y T004 antes de cualquier tarea de frontend (constitución III).
- **Fase 2** bloquea todas las historias. Dentro de ella: T005 y T006 → T009; T007 → T009; T008 → T009; T009 → T010; T011–T014 → T015; T014 → T020.
- **US1 y US2 (P1)** dependen solo de la fase 2 (US2 necesita las tarjetas de US1 para seleccionar); **US3 y US4 (P2)** dependen de US1 (US4 usa el estado de ramas y US3 las ramas forzadas, así que T036 y T040 comparten `useEstadoDelArbol.ts` y `OrganigramaNodo.tsx`: van en secuencia); **US5 (P3)** revisa lo anterior. Orden de ejecución dentro de las historias compartidas: T031, T040 y después T036 (T036 usa las ramas de T040).
- Dentro de cada historia: pruebas → implementación → E2E. Tareas que tocan el mismo archivo (`OrganigramaPage.tsx`, `OrganigramaNodo.tsx`, `useEstadoDelArbol.ts`, `BarraDeHerramientas.tsx`, `organigrama.spec.ts`) van en secuencia.

### Parallel example: Fase 2

```text
T005, T006, T008 (dominio y DTO)  →  T007 → T009 → T010   ‖   T011, T012, T013, T014, T016 (frontend)  →  T015
T017, T018, T019, T020 (pruebas, archivos distintos, tras sus implementaciones)
```

## Implementation Strategy

1. **MVP**: Fases 1–2, US1 (ver) y US2 (detalle).
2. **Entrega incremental**: US3 (buscar y filtrar), US4 (recorrer) y US5 (teclado), y pulido.
3. Cada historia termina con su E2E antes de pasar a la siguiente.

### Notas

- La feature no escribe datos: no hay migraciones, seeders ni auditoría que mantener; usa las posiciones, puestos, unidades y usuarios sembrados por 004, 005 y 006.
- Los cinco puntos por confirmar de spec.md (atenuar en lugar de recortar, carga completa, solo estado actual, sin exportar y sin agrupar) se resuelven con el valor por defecto del documento; si negocio cambia alguno, hay que revisar US3, el contrato y `arbol.ts`.
- Los cambios de ocupante o de estado que haga gestión de personal se ven en la siguiente carga: no hay actualización automática ni botón «Actualizar» (fuera de alcance de esta feature).

## Estado de la implementación (2026-10-16)

Implementado **sin pruebas**, por indicación expresa del usuario ("no quiero que hagas pruebas"). Hechas (`[X]`): el requisito previo T001 (fusión con gestión de personal), `DESIGN.md` (T004) y todo el código de backend y frontend. Pendientes: T002 (línea base), T003 (el tablero `design/Organigrama.dc.html` es el del usuario y no se sobrescribió), las pruebas (T016 a T022, T027, T028, T029, T032 a T034, T037 a T039, T044, T045, T047 y T049), T048 a T051 (revisiones y medición) y las puertas de calidad T052 a T054.

**T001 — cómo se resolvió el choque con gestión de personal (GP-02)**
- `PosicionRepositoryInterface` y `EloquentPosicionRepository` reúnen los métodos de 006 y los de GP-02; el `obtener(): ?array` de GP-02 pasó a `obtenerParaGestion`.
- `EstructuraService`: el bloque de GP-02 se renombró `getPosicionDeGestion` y `getPosicionesDeGestionPorIds`; prevalece el contrato de 006 para `getPosicion` y `getPosicionesPorIds`. Se actualizaron `PosicionesAsignablesEstructura.php`, `EstructuraFacadeLector.php`, la Facade y `tests/Feature/Estructura/PosicionesAsignablesTest.php` (solo los nombres de las llamadas).
- `PosicionModel` recuperó `tenant_id` en el `$fillable`; `SharedService` recuperó su dependencia `UsuarioRepositoryInterface`; se quitó el enlace duplicado del repositorio en el provider.
- Nueva migración `2026_10_16_000005_scope_maxima_autoridad_by_tenant`: el índice único de la máxima autoridad pasa de global a **uno por tenant** (otro seeder crea una posición del tenant 2 sin supervisor).

**Desviaciones del plan**
- El organigrama se limita al **tenant de quien consulta** (`TenantDelUsuarioInterface` sobre `SharedFacade::getContextoDeTenant`) para no mezclar árboles de tenants; `PosicionRepositoryInterface::vigentes(int $tenantId)`.
- El zoom usa la propiedad CSS `zoom` (compatible con los navegadores actuales) en lugar de `transform: scale()`.
- Pendiente de decidir: 006 (listar, opciones, crear y editar posiciones) todavía **no filtra por tenant**.

Comprobaciones hechas: `pint`, `tsc -b` y `eslint` limpios en el código nuevo; `migrate:fresh --seed` correcto; `GET /api/organigrama` devuelve 200 con 8 posiciones (el tenant 2 queda fuera), 401 sin token y 405 en `POST`. No se probó la interfaz en el navegador.
