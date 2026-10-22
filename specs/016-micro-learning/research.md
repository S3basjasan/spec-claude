# Research: Micro-learning

Todas las decisiones salen de la spec, de la clarificación del 2026-10-16, del insumo `plan-microlearning.md` y de la lectura del
código existente de `Desarrollo`. No quedan `NEEDS CLARIFICATION`.

## R-01 · Ampliar la tabla `microcontenidos` en lugar de crear otra

- **Decisión**: migraciones nuevas sobre la tabla de la feature 005: renombrar `nombre`→`titulo` y `url_destino`→`url`; quitar
  `formador`; añadir `codigo` (único, nulo hasta asignarse), `miniatura_url`, `created_by` y `updated_by`; cambiar el valor por
  defecto de `estado` a `Borrador`; convertir datos existentes (`Disponible`→`Publicado`, `Lectura`→`Articulo`,
  `Infografia`→`PDF`, `Audio`→`Video`) y asignar `codigo` a las filas existentes a partir de su id.
- **Justificación**: `competencia_microcontenido`, el Catálogo y sus pruebas ya apuntan a esos ids; la 005 dejó el comentario
  «Micro-Learning la amplía con migraciones propias; no recrearla». Laravel 13 renombra y cambia columnas en SQLite de forma nativa.
- **Alternativas**: tabla nueva con migración de vínculos (duplica el dato, rompe FR-011); mantener `nombre`/`url_destino` y añadir
  alias (confunde el modelo y contradice el insumo).

## R-02 · Código secuencial MICRO-NNNN

- **Decisión**: se inserta el contenido y, dentro de la misma transacción, se asigna `codigo = 'MICRO-' . str_pad(id, 4, '0')`.
  Como SQLite usa `AUTOINCREMENT` en `id()`, un id eliminado no se reutiliza, de modo que el código nunca se repite. Índice único
  sobre `codigo`.
- **Justificación**: dos altas concurrentes no pueden producir el mismo código (lo garantiza el id); no hay tabla de contadores.
- **Alternativas**: `max(codigo)+1` (carrera entre transacciones); tabla de secuencias (complejidad innecesaria).

## R-03 · Qué cuenta como «disponible» para el Catálogo

- **Decisión**: para micro-contenidos, *disponible* = `Publicado`. El repositorio del Catálogo filtra y mapea por tipo de recurso
  (`Publicado` → `EstadoRecurso::Disponible`; `Borrador` y `Archivado` → `Archivado` a efectos del Catálogo, aunque el Catálogo
  solo ofrece los disponibles). El `RecursoFormativo` de un micro-contenido lleva `formador = null` y
  `url_destino = '/micro-learning/{id}'`.
- **Justificación**: Catálogo FR-017/FR-020 cuentan solo recursos disponibles; la spec 016 FR-031 dice que el Catálogo muestra solo
  los Publicados. Evita cambiar el contrato del Catálogo.
- **Alternativas**: añadir `Borrador` al enum compartido (afecta cursos y pruebas existentes).

## R-04 · Intentos de quiz y retroalimentación inmediata

- **Decisión**: `POST /intentos` crea o devuelve el intento `EnCurso` del progreso del colaborador (idempotente); las respuestas se
  envían una a una y el servidor contesta si fue correcta y cuál era la opción correcta. Al responder la última pregunta el intento
  pasa a `Finalizado`, se calcula el puntaje y el progreso pasa a `Completado` si no lo estaba. Un `EnCurso` se **descarta**
  (borrado explícito de sus respuestas y del intento) cuando el colaborador inicia un intento «Reintentar», o cuando el
  Administrador cambia las preguntas. Solo los `Finalizado` bloquean la edición de preguntas, puntúan y cuentan.
- **Justificación**: cumple la aclaración de la spec (los incompletos no se guardan ni bloquean) sin enviar las respuestas correctas
  al cliente.
- **Alternativas**: evaluar todo en el cliente (expone las correctas, viola el insumo); guardar los incompletos y bloquear
  (descartado en la clarificación).

## R-05 · Progreso, tenant y reglas de completado

- **Decisión**: un progreso por `(microcontenido_id, colaborador_id)` con `tenant_id`. Se crea al abrir el contenido
  (`PUT /progreso` o `POST /intentos`). Video/PDF/Artículo se completan solo con `POST /completar`; el Quiz, al finalizar un
  intento. Completar un contenido ya completado devuelve 422. Archivar conserva el progreso; solo se puede iniciar o avanzar un
  contenido **Publicado**.
- **Justificación**: coincide con spec 3.4 y 3.3. El `tenant_id` permite a Reportes consultar por tenant sin cruzar con Personal.
- **Alternativas**: derivar el tenant en cada consulta desde Personal (acopla Reportes y es más lento).

## R-06 · Cálculo de «Asignado»

- **Decisión**: dominio puro `CalculadorDeAsignacion`: un contenido está Asignado si es `Publicado` y (a) está vinculado a una
  competencia con brecha activa del colaborador y no está completado, o (b) existe un progreso `EnProgreso`. Las brechas llegan por
  `BrechasActivasInterface::brechasDe(usuarioId)` (ya existente, alimentado por la DNF). Sin ficha o sin DNF, la parte (a) queda
  vacía y (b) sigue funcionando (degradación parcial).
- **Justificación**: es la definición de la spec (FR-030) reutilizando el puerto de la 005.
- **Alternativas**: llamar a la Facade de DNF directamente desde el caso de uso (rompe la regla de puertos).

## R-07 · Certificado PDF bajo demanda

- **Decisión**: puerto `CertificadoPdfInterface` con adaptador `CertificadoPdfDompdf` propio de `Desarrollo`, con la misma política
  que el exportador de fichas (sin PHP, sin recursos remotos, datos escapados). El PDF contiene nombre del colaborador (puerto
  `NombreDeColaboradorInterface` → `PersonalFacade::getColaborador`), título, competencias, duración y fecha de finalización. No se
  guarda; se genera con el progreso `Completado`. La descarga usa `shared/api/pdf.ts`.
- **Justificación**: dompdf ya está instalado y aprobado; `Desarrollo` no puede reutilizar la clase interna de Gestión de Personal.
- **Alternativas**: generar el PDF en el frontend (datos sensibles en el cliente); almacenar el archivo (estado a mantener).

## R-08 · Vista previa del Administrador sin efectos

- **Decisión**: la vista previa es un modal de solo cliente que reutiliza los componentes del colaborador en modo `previa`, con
  los datos de `GET /api/microcontenidos/{id}` (que para el Administrador incluyen la opción correcta). No invoca endpoints de
  progreso ni intentos.
- **Justificación**: garantiza FR-038 sin condiciones especiales en el servidor.
- **Alternativas**: endpoints de «preview» en el backend (superficie extra y riesgo de registrar progreso).

## R-09 · Competencias del certificado y del historial

- **Decisión**: el certificado muestra las competencias vinculadas **al emitirlo** con su nombre vigente (incluidas las inactivas,
  vía `CompetenciasInterface::porIds`). El historial del Catálogo registra cada alta o baja de vínculos hecha desde el formulario
  de micro-learning (`tipo_cambio = Microcontenido`, acciones `Asociado`/`Quitado`) y se incrementa `version` de la configuración de
  cada competencia afectada.
- **Justificación**: resuelve la duda de bajo impacto que dejó la clarificación sin guardar un histórico del certificado; el
  `version` evita que un panel del Catálogo abierto sobrescriba el cambio.
- **Alternativas**: congelar las competencias al completar (requiere tabla adicional no pedida).

## R-10 · Eliminación y su efecto en el historial

- **Decisión**: eliminar solo si no existe ningún progreso del contenido; en la transacción se borran, en orden, respuestas y
  intentos (no existen), opciones, preguntas, historial de estados, vínculos (con anotación `Quitado` en el historial del
  Catálogo) y por último el contenido. Sin `cascade` en ninguna FK.
- **Justificación**: coincide con spec 3.3 y con el insumo (sin `cascade`).
- **Alternativas**: conservar el historial sin FK (deja registros huérfanos que nadie consulta).

## R-11 · Autorización y visibilidad

- **Decisión**: `auth:sanctum` en todo; `admin` en escritura, historial y estado. `GET /` y `GET /{id}` deciden por la capacidad
  del token: el Administrador ve todos los estados, la opción correcta y las completaciones; el colaborador, solo Publicados (o
  Archivados completados por él), sin la correcta ni el número de completaciones. Los endpoints del colaborador exigen cuenta con
  ficha (`CuentaColaboradorInterface`) y no admin; el Administrador recibe 403. Los ids ajenos devuelven 404.
- **Justificación**: Constitución I y spec FR-001/FR-002.
- **Alternativas**: rutas separadas por rol (duplica controladores).

## R-12 · Reproducción de video como marcador de posición

- **Decisión**: el cliente simula un reproductor (Reproducir/Pausar, avance de 1 s por segundo, barra de posición) y guarda la
  posición con `PUT /progreso` cada 10 s y al salir; no se hospeda ni se reproduce el recurso real. PDF y Artículo se abren por su
  URL en una pestaña nueva.
- **Justificación**: spec 4.7 y supuesto «marcador de posición»; los tableros muestran exactamente ese placeholder.
- **Alternativas**: incrustar un reproductor real (fuera de alcance, dependencias nuevas).

## R-13 · Tableros, tema y responsive

- **Decisión**: se siguen `MicroAdmin`, `MicroColaborador`, `MicroReproduccion` y `MicroQuiz` como referencia visual, sobre los
  tokens y componentes de `DESIGN.md`; los tres tableros del colaborador son estados de la misma pantalla (resumen, reproducción y
  quiz). Rejilla fluida de 1 a 4 columnas entre 320 y 1440 px y modales a ancho completo bajo 640 px.
- **Justificación**: constitución III; las diferencias con la spec quedan en el plan y en `DESIGN.md` §9.
- **Alternativas**: rediseñar en Claude Design (innecesario: los patrones cubren la pantalla).
