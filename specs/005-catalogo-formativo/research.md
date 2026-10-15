# Research: Catálogo formativo de competencias

No quedan `NEEDS CLARIFICATION`. Estas son las decisiones que resuelven los vacíos entre el insumo, la spec y el repositorio.

## D-01 · El módulo `Desarrollo` no existe todavía

- **Decision**: crear `backend/app/Modules/Desarrollo` con las cuatro capas, su `DesarrolloServiceProvider` (registrado en
  `bootstrap/providers.php`) y su `Routes/api.php`, siguiendo el mismo esqueleto de `Estructura`. Frontend en
  `frontend/src/modules/desarrollo/catalogo-formativo/`.
- **Rationale**: el insumo lo indica y la constitución exige un módulo por dominio. Reutilizar `Estructura` rompería el límite
  "Estructura es dueña del diccionario".
- **Alternatives**: poner el código en `Estructura` (descartada: mezcla responsabilidades); crear un módulo `Formacion`
  (descartada: el insumo fija `Desarrollo`).

## D-02 · Cursos y micro-contenidos aún no existen

- **Decision**: crear tablas **mínimas** `cursos` (`id`, `nombre`, `tipo`, `duracion_minutos`, `formador`, `estado`
  Disponible|Archivado, `url_destino` nullable) y `microcontenidos` (mismas columnas útiles), con factories y siembra
  demostrativa. Las features dueñas las amplían con migraciones propias y no las recrean.
- **Rationale**: las claves foráneas, el estado archivado y los contadores necesitan registros reales para probarse (mismo
  criterio que `posiciones` en 004). El estado `Archivado` es el que excluye un recurso del colaborador y de la cobertura.
- **Alternatives**: contador simulado (no demuestra duplicados ni archivados); esperar a esas features (bloquea la entrega).

## D-03 · Nivel actual y brechas: módulos inexistentes

- **Decision**: dos puertos en `Application/Ports` — `NivelesDeColaboradorInterface::nivelesDe(int $usuarioId): array`
  (`competencia_id => 'A'|'B'|'C'|'D'`) y `BrechasActivasInterface::brechasDe(int $usuarioId): array`
  (`[{competencia_id, nivel_requerido}]`). Adaptadores provisionales `…SinDatos` devuelven arreglos vacíos. Cuando existan
  Gestión de Personal y DNF, un adaptador nuevo llama a sus Facades y cambia el binding.
- **Rationale**: FR-039/FR-041. Mantiene los casos de uso estables y permite probar con adaptadores falsos en las pruebas.
- **Alternatives**: llamar directamente a Facades inexistentes (no compila); tablas locales con niveles (duplicaría la fuente).

## D-04 · Listar competencias activas e inactivas

- **Decision**: añadir a `EstructuraFacade` el método `getTodasLasCompetencias(): array` (activas e inactivas, mismos campos de
  `getCompetencia`, sin grados cuando no hacen falta). `Desarrollo` combina ese arreglo con su configuración local, filtra
  (tipo, estado, nombre sin distinguir mayúsculas ni tildes, área, con/sin cursos) y pagina en memoria.
- **Rationale**: el filtro por estado es exclusivo del Administrador (FR-022) y la Facade solo ofrecía activas o por id.
- **Alternatives**: pedir por ids (no se conocen); `JOIN` con tablas de Estructura (viola la constitución).

## D-05 · Cobertura solo con recursos disponibles

- **Decision**: una competencia activa está cubierta si tiene ≥ 1 curso `Disponible` o ≥ 1 micro-contenido `Disponible`
  vinculado. `CalculadorDeCobertura` (Domain, puro) recibe, por competencia activa, las cantidades de recursos disponibles y
  devuelve `{activas_totales, activas_cubiertas, porcentaje}` (0 si no hay activas; redondeo a entero por proximidad).
  Los contadores de la matriz y de las tarjetas usan la misma definición.
- **Rationale**: spec Q1 (FR-017, FR-020). El detalle del Administrador sí lista los archivados con su marca para poder quitarlos.
- **Alternatives**: contar cualquier vínculo (cobertura engañosa); alerta aparte (añade UI sin valor claro).

## D-06 · Áreas formativas: crear y renombrar, sin eliminar

- **Decision**: `areas_formativas` con `nombre` y `nombre_clave` único (minúsculas, sin espacios en los extremos, espacios
  internos colapsados). Endpoints `POST /areas` y `PATCH /areas/{areaId}`; sin `DELETE`. Cada renombrado inserta una fila de
  historial `tipo_cambio = AreaFormativa`, `accion = Renombrado`, con `valor_anterior` y `valor_nuevo` y `competencia_id` nulo.
- **Rationale**: spec Q2 y Q3 (FR-008, FR-009, FR-037). Al no eliminarse, ninguna competencia queda con un área inexistente.
- **Alternatives**: eliminar con reasignación (descartada en Q2); desactivar áreas (fuera de alcance).

## D-07 · Guardado atómico con bloqueo optimista

- **Decision**: `PUT /{competenciaId}` recibe `area_formativa_id`, `curso_ids`, `microcontenido_ids` y `version`. El repositorio,
  en **una transacción**: verifica la `version` (409 si cambió), calcula la diferencia con lo guardado, aplica altas y bajas de
  vínculos, cambia el área, incrementa `version`, actualiza `updated_by`/`updated_at` y escribe el historial. Sin cambios
  reales no escribe nada ni sube la versión (FR-038). La fila de `competencia_formativa` se crea en el primer guardado
  (`version` 0 se devuelve para una competencia nunca configurada).
- **Rationale**: FR-025, edge case de edición concurrente. Es el patrón de `version` ya usado en 004.
- **Alternatives**: endpoint por vínculo (no atómico); comparar `updated_at` (resolución de 1 s).

## D-08 · Nuevas asociaciones solo con competencias activas

- **Decision**: `ReglasDeConfiguracionFormativa` (Domain) compara lo guardado con lo solicitado; si la competencia está inactiva
  y la solicitud **agrega** algún curso o micro-contenido, lanza `CompetenciaInactiva` (422). Las **bajas** y el cambio de área
  se permiten. Los recursos agregados deben existir y estar `Disponible`; los ya vinculados que se archivaron pueden conservarse
  o quitarse.
- **Rationale**: FR-012, FR-014, FR-032. La interfaz lo impide, pero la autoridad es el backend (FR-006).
- **Alternatives**: bloquear toda edición de competencias inactivas (impide limpiar vínculos, contra FR-014).

## D-09 · Un solo rol por usuario

- **Decision**: la vista se elige por la capacidad del token (`admin` o `user`) y no hay ruta, botón ni parámetro para cambiarla.
  Se omiten los botones "Ver como…" de los mockups. `GET /cobertura`, `/historial`, `/recursos-disponibles` y las escrituras exigen
  `admin`.
- **Rationale**: spec Q4 (FR-005b). El rol ya es excluyente en el sistema (`Rol::Admin | Rol::User`).

## D-10 · Destino de "Iniciar"/"Ver"

- **Decision**: cada recurso expone `url_destino` (nullable) y `accion` (`Iniciar` | `Ver`; hoy siempre `Iniciar` para cursos
  y `Ver` para micro-contenidos, hasta que exista seguimiento de avance). Con `url_destino` nulo la interfaz muestra el botón
  deshabilitado con el aviso "Próximamente".
- **Rationale**: spec Q5 (FR-031). No se inventa una pantalla de curso fuera de alcance.

## D-11 · Recomendados

- **Decision**: `ListarRecomendados` cruza `BrechasActivasInterface` del usuario autenticado con las competencias activas que
  tengan ≥ 1 recurso disponible, y devuelve `{competencia_id, nombre, tipo, area, nivel_actual, nivel_requerido, cursos_disponibles}`.
  Sin brechas, sin DNF o con fallo del puerto: lista vacía (200). La interfaz oculta la sección.
- **Rationale**: FR-033/FR-034/FR-041. Se omite el "curso sugerido" del mockup (no hay regla de prioridad en la spec).

## D-12 · Consulta para la DNF ("Ver cursos sugeridos")

- **Decision**: `DesarrolloFacade::getOfertaPorCompetencia(int $competenciaId): array` devuelve cursos y micro-contenidos
  **disponibles** vinculados; la navegación de la DNF abre `/formacion?competencia=:id`. Se documenta en
  `contracts/desarrollo-puertos.md`.
- **Rationale**: FR-040 / sección 6 del insumo. La pantalla de la DNF no se implementa aquí.

## D-13 · Historial

- **Decision**: `competencia_formativa_historial` es solo de inserción (sin endpoints de edición ni borrado). Cada vínculo
  agregado o quitado genera una fila (`Curso`/`Microcontenido`, `Asociado`/`Quitado`, `recurso_id`, y el nombre del recurso como
  `valor_nuevo`/`valor_anterior` para que siga siendo legible aunque el recurso cambie); cada cambio de área, una fila
  (`AreaFormativa`, `Cambiado`, nombres anterior y nuevo). La consulta pagina por fecha descendente.
- **Rationale**: FR-035 a FR-038. Guardar el nombre del recurso evita depender de su módulo para leer el historial.

## D-14 · Diseño: qué se toma de los mockups y qué no

- **Decision**: los tableros son guía visual; se implementan sobre los componentes de `shared/ui` y los tokens de
  `design/DESIGN.md`. Las diferencias con la spec están enumeradas en `plan.md` ("Diferencias entre los mockups y la spec") y se
  trasladan a `DESIGN.md` §9 en la primera tarea de frontend. Los elementos nuevos (slide-over, indicador de cobertura, tarjeta
  con puntos, selector múltiple, historial) se documentan en `DESIGN.md` §4/§5 antes de implementar (Constitución III).
- **Alternatives**: copiar el HTML de los tableros (prohibido por `DESIGN.md` §1 y por la constitución).
