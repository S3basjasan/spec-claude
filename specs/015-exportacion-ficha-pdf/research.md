# Research: Exportación individual de ficha integral

Sin marcadores `NEEDS CLARIFICATION`. Fija las decisiones donde el insumo técnico y el repositorio no coinciden o dejan una elección abierta.

## Divergencias con el insumo

| Insumo | Realidad del repositorio / constitución | Decisión |
|---|---|---|
| PostgreSQL con esquema por tenant y transacción `REPEATABLE READ READ ONLY` | SQLite; aislamiento por columna `tenant_id` | Transacción de lectura sobre la conexión por defecto; aislamiento explícito solo en motores que lo admiten (D3) |
| `Modules/Personal`, `modules/personal` | Módulo real `GestionPersonal` / `gestionPersonal` | Ubicación equivalente existente |
| Capacidades `personal.fichas.exportar` y `personal.ficha_propia.exportar` | No hay permisos por capacidad; solo `admin`/`user` y `colaborador_cuentas.puede_exportar` | `admin` para el Administrador; `puede_exportar` para la propia |
| «Reutilizar un generador PDF existente; si no lo hay, dompdf» | No hay ningún generador instalado | dompdf aprobado por el usuario (2026-10-16); instalación como primera tarea (D1) |
| Renderizar en un temporal privado y eliminarlo | El PDF de una ficha es pequeño y se puede producir en memoria | PDF en memoria sin temporal (D5) |
| `PersonalExportSnapshotDTO` | Nombres en español | `SnapshotDeFichaDTO` |
| Reutilizar «lectores de las GP» | `ConsultarSituacionLaboral` y `ConsultarTrayectoria` degradan una caída de Estructura a datos sin nombre | Exigir disponibilidad: cualquier caída es 503, no sección vacía (D4) |
| 409 por versión o clave de operación | La exportación no escribe ni usa clave | No aplica |
| IDs como cadenas decimales | Los contratos del módulo entregan enteros | Enteros, como GP-01..03 |
| Extraer texto del PDF en las pruebas | No hay librería de lectura de PDF instalada | Verificar la plantilla intermedia, el número de páginas y la estructura del PDF; la extracción de texto requeriría otra dependencia de desarrollo (D7) |
| Vista propia solo en Mi ficha | El permiso de exportación es independiente del de ficha | Página `/exportar-mi-ficha` además del botón en Mi ficha (D8) |

## D1. Generador de PDF

- **Decisión**: proponer `dompdf/dompdf` (^3.1), encapsulado en `RenderizadorDePdfDompdf` tras `RenderizadorDePdfPort`. No está preaprobada en la constitución (IV), pero **el usuario la aprobó explícitamente el 2026-10-16**; su instalación es la primera tarea. Verificado con `composer require dompdf/dompdf --dry-run`: resuelve `v3.1.6` más `php-font-lib`, `php-svg-lib`, `masterminds/html5` y `sabberworm/php-css-parser`, sin tocar `composer.json` ni `composer.lock`. Las extensiones `dom` y `mbstring` están activas; `gd` no, y no se necesita porque el PDF no lleva imágenes (si se añadiera un logotipo habría que habilitarla). La compatibilidad real con PHP 8.5 se comprueba con la prueba de renderizado, no solo con la resolución de Composer.
- **Opciones de seguridad**: `isRemoteEnabled=false`, `isPhpEnabled=false`, JavaScript desactivado, `chroot` limitado a la carpeta de plantillas y fuentes del módulo, sin cargar CSS ni recursos externos; todo texto de usuario se escapa como texto plano (la plantilla Blade usa `{{ }}`, nunca `{!! !!}`).
- **Alternativas**: generador propio de PDF (viable para texto en secciones y listas con paginación manual; más código y más riesgo de errores de maquetación con acentos y texto largo; solo si la prueba con PHP 8.5 revelara una incompatibilidad de dompdf); TCPDF/mPDF (también dependencias nuevas, más pesadas); servicio externo (prohibido: recibiría datos personales).

## D2. Alternativa sin dependencia

- **Decisión de contingencia** (solo si dompdf resultara incompatible con PHP 8.5 en la prueba de renderizado): `RenderizadorDePdfPropio` implementa `RenderizadorDePdfPort`: dispone el texto en bloques por registro con fuente estándar PDF, envuelve líneas por ancho medido, cambia de página sin partir un registro por la mitad y numera páginas. Se acepta una maquetación más sobria. Los acentos del español se resuelven con codificación WinAnsi.
- **Rationale**: el puerto aísla la decisión; el resto del plan (instantánea, autorización, entrega, frontend) no cambia.

## D3. Lectura coherente

- **Contexto**: la ficha, los vínculos, el desarrollo y el lector de Estructura (que usa la fachada de Estructura sobre el mismo ORM) leen la conexión por defecto; no hay lectores externos.
- **Decisión**: `ContextoDeLecturaPort::enSnapshot(callable)` ejecuta toda la reunión de datos dentro de una transacción de lectura sobre la conexión por defecto (`DB::transaction` en SQLite, donde una transacción de lectura da una vista estable; en motores que lo admiten se fija además `REPEATABLE READ READ ONLY`). La transacción se cierra al devolver el DTO, antes de renderizar. Una prueba comprueba, con el registro de consultas, que todas las lecturas del armador se hicieron en esa conexión y dentro de la transacción; si un lector futuro usara otra conexión o fuente, la prueba falla y debe resolverse su contrato de versión antes de declarar coherencia.
- **Límite honesto**: la instantánea es la versión guardada al comenzar la lectura; no es una garantía contra cambios posteriores ni afirma actualidad. El PDF muestra el instante de lectura y el de generación.
- **Alternativas**: lecturas sin coordinación (pueden mezclar versiones); bloquear escrituras durante la generación (innecesario y dañino).

## D4. Un fallo no es un dato ausente

- **Hallazgo**: `ConsultarSituacionLaboral` ya indica `disponibilidad: no_disponible` cuando Estructura falla, pero `ConsultarTrayectoria` devuelve los hechos con `puesto`/`unidad` sin valor sin señalar el fallo.
- **Decisión**: el armador exige disponibilidad total. Se añade a `ConsultarTrayectoria` un método que devuelve también `disponibilidad` (`ejecutar` conserva su contrato para los endpoints y delega en él). Si cualquier fuente (ficha, situación, trayectoria, formación, experiencia, movilidad, Estructura) falla, se lanza `ExportacionNoDisponible` y no se renderiza. Las etiquetas «No registrado» / «No informada» se aplican solo cuando la lectura es exitosa y el dato es nulo; una sección sin registros se imprime como vacía solo si la lectura confirma que no hay ninguno.
- **Alternativas**: tolerar la caída de Estructura (contradice FR-016); duplicar la composición de trayectoria en el exportador (reglas inventadas y divergencia con la pantalla).

## D5. PDF en memoria y entrega

- **Decisión**: dompdf devuelve el PDF completo como cadena; se comprueba que no esté vacío, que empiece por `%PDF-` y que contenga `%%EOF` al final, y solo entonces se revalida el acceso y se responde con `response($bytes, 200, cabeceras)`. Cualquier fallo anterior produce un JSON de error con su código, nunca bytes. No hay archivo temporal, por lo que no hay nada que limpiar ni que excluir de los respaldos.
- **Cabeceras**: `Content-Type: application/pdf`, `Content-Disposition: attachment; filename="Ficha_EMP-###.pdf"` (código saneado a `[A-Za-z0-9_-]`), `Cache-Control: private, no-store`, `X-Content-Type-Options: nosniff`, `Content-Length` exacto.
- **Rationale**: elimina la clase de errores «archivo parcial» y «temporal huérfano»; una ficha, incluso extensa, cabe en memoria (se mide en la prueba de volumen).
- **Límite**: si una ficha extrema superara el límite de memoria de PHP, la generación falla (503, registrado) y nunca se trunca ni se omiten registros.

## D6. Autorización al solicitar y al entregar

- **Decisión**: el `AutorizadorDeExportacion` resuelve (1) al inicio: tenant de la cuenta, ficha del tenant (404 uniforme), y para el Colaborador asociación válida más `puedeExportar` sobre su propia ficha (sin exigir los otros permisos) y (2) justo antes de responder, con lecturas nuevas fuera de la transacción del snapshot: repite la asociación y el permiso; para el Administrador vuelve a resolver el contexto de tenant de la cuenta. Si el permiso o la asociación ya no valen, se descarta el PDF y se responde 403 sin bytes.
- **Límite honesto**: una revocación posterior al envío no recupera bytes entregados ni descargas.

## D7. Pruebas del PDF

- **Decisión**: (a) la plantilla se renderiza a HTML y se prueba su contenido (todas las secciones, etiquetas «No registrado» y «No informada», ingreso programado distinto de vigente, escape de HTML en observaciones); (b) se renderiza el PDF real con dompdf con acentos, observaciones largas y listas de varias páginas y se comprueba estructura (`%PDF-`, `%%EOF`, número de páginas > 1 según `/Type /Page`), tamaño y ausencia de excepciones; (c) se deja un script de revisión visual de las páginas representativas (convertir a imagen con una herramienta local del revisor) descrito en el quickstart. La extracción de texto del PDF automatizada requeriría una dependencia de desarrollo adicional (p. ej. `smalot/pdfparser`), que no se añade sin aprobación.
- **Rationale**: el insumo pide no basarse en que empiece por `%PDF`; la combinación de a) y b) más la revisión visual cubre contenido, paginación y estructura sin dependencias no aprobadas.

## D8. Vistas del Colaborador

- **Decisión**: además del botón en el encabezado de Mi ficha (visible con `capacidades.exportar`, ya filtrada por `puedeExportar`), la página `/exportar-mi-ficha` no depende de poder abrir Mi ficha; ambas usan `BotonExportarFicha`. La entrada de navegación «Exportar mi ficha» es del rol `user`; sin permiso o sin ficha la página lo explica.

## D9. Interruptor de reversión

- **Decisión**: `hrms.gestion_personal.exportacion_habilitada` (por defecto true); en false, ambas rutas responden 503 `EXPORTACION_DESHABILITADA` sin generar, como el resto de interruptores del módulo.

## Resultados de la implementación (2026-10-16)

- **dompdf**: v3.1.6 instalado y funcionando con PHP 8.5.5 (no fue necesaria la contingencia del generador propio). Fuente DejaVu Sans incluida con dompdf, con subconjunto de glifos; acentos y «ñ» verificados por extracción de texto (`pdftotext`, que las pruebas usan si está disponible y omiten si no).
- **Medición local** (Windows, PHP 8.5.5, `memory_limit` 128 M): ficha mínima ≈ 0,2 s y ≈ 52 MB de pico; ficha con 100 formaciones y 100 experiencias de ≈ 2,4 KB cada una (≈ 150 KB de PDF) ≈ 4,4 s y ≈ 75 MB de pico. No se truncan registros: si un entorno no alcanza, el fallo es 503 `EXPORTACION_NO_DISPONIBLE` y se informa en el registro.
- **CORS**: `config/cors.php` expone `Content-Disposition` para que el cliente lea el nombre seguro del PDF cuando la API está en otro origen.
- **Pruebas**: `phpunit.xml` fija `memory_limit=512M` porque la suite acumula memoria y cada render de dompdf necesita ≈ 50 MB.
