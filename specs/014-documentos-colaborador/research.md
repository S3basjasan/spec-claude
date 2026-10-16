# Research: Gestión de documentos del colaborador

Sin marcadores `NEEDS CLARIFICATION`. Fija las decisiones donde el insumo técnico y el repositorio no coinciden o dejan una elección abierta.

## Divergencias con el insumo

| Insumo | Realidad del repositorio / constitución | Decisión |
|---|---|---|
| PostgreSQL con esquema por tenant | SQLite; aislamiento por columna `tenant_id` | Columna `tenant_id`; la clave de objeto lleva el `tenant_id` como prefijo |
| `Modules/Personal`, `modules/personal` | Módulo real `GestionPersonal` / `gestionPersonal` | Ubicación equivalente existente |
| Capacidades `personal.documentos.gestionar` y `personal.documentos_propios.consultar` | No hay permisos por capacidad; solo tokens `admin`/`user` y `colaborador_cuentas.puede_consultar_documentos` | `admin` para gestión; `puede_consultar_documentos` para consulta propia |
| Adaptador Flysystem/S3 «o el disco privado ya configurado» | `league/flysystem-aws-s3-v3` no está instalado y añadirlo exige aprobación | Disco privado configurable (`local` por defecto); S3 queda como cambio de configuración tras aprobar la dependencia |
| «Validador estructural compatible con el entorno» | No hay librería de PDF instalada; `fileinfo` está disponible | Validador estructural propio sin dependencias nuevas (D3) |
| `archived_by` «se adapta al actor del sistema» | El actor es el id de usuario | `archived_by` unsignedBigInteger del usuario; sin actor de sistema en este incremento |
| `uuid` para `operacion_id`, `size_bytes` bigint, CHECKs | SQLite sin tipo uuid; los CHECK se emulan | `char(36)`, `unsignedBigInteger`, y triggers SQLite como en GP-03 y 013 |
| 409 «por una modificación incompatible por archivo» | — | Cambiar visibilidad o archivar un documento ya archivado ⇒ 409 |
| La consulta propia usa la ficha de la asociación | `ResolverFichaPropia` exige además `puede_consultar_ficha` | Resolutor propio que exige solo el permiso documental (D5) |
| IDs «como cadenas decimales» | Los contratos del módulo entregan enteros | Enteros, como GP-01..03 y 013 |
| Mi ficha como única vista propia | El permiso documental es independiente del de ficha | Página `/mis-documentos` además de la pestaña (D8) |

## D1. Publicación en dos fases con compensación

- **Decisión**: (1) autorizar, comprobar `Idempotency-Key` y repetición; (2) recibir en temporal privado, contar bytes reales, calcular SHA-256 y validar; (3) escribir un objeto privado por intento con clave `personal/{tenant_id}/colaboradores/{colaborador_id}/{uuid-aleatorio}.pdf`; (4) transacción corta: volver a autorizar, comprobar ficha, `CatalogoDeCategoriasPort::confirmarActiva` (bloquea la fila de la categoría, ya implementado en la feature 013) e insertar metadatos con la unicidad de operación; (5) tras el commit el documento es Disponible. Si la transacción falla se elimina únicamente el objeto de ese intento; si esa limpieza falla se registra en el log técnico (sin la clave en la respuesta) y el barrido periódico la resuelve. No hay estado «Subiendo»: sin fila publicada no existe documento.
- **Rationale**: el almacenamiento no participa de la transacción de base de datos; escribir primero el objeto y luego la fila hace que el único estado visible sea completo, y los restos son objetos sin referencia, recuperables.
- **Alternativas**: fila «Subiendo» y luego promoción (exige excluirla de cada consulta de negocio, riesgo de fuga); escribir la fila antes que el archivo (documento descargable sin contenido).

## D2. Almacenamiento privado

- **Decisión**: `AlmacenDeDocumentosPort` (`guardar`, `leerComoFlujo`, `existe`, `eliminar`, `listar(prefijo)` con fecha de modificación) con un adaptador sobre el disco de Laravel `hrms.gestion_personal.documentos.disco` (por defecto `local`, `storage/app/private`, sin enlace público). Las claves nunca contienen el nombre del cliente y no se reutilizan entre intentos. S3 se habilitaría cambiando el disco una vez aprobada `league/flysystem-aws-s3-v3`.
- **Rationale**: cumple «privado» y «sin URL permanente» sin dependencias nuevas; el puerto permite sustituir el disco o usar un doble en pruebas.
- **Alternativas**: instalar el adaptador S3 ahora (requiere aprobación); disco `public` (prohibido: URL permanente).

## D3. Validación estructural de PDF

- **Decisión**: `ValidadorDePdfPort::validar(rutaTemporal)` ⇒ `válido` | `inválido(motivo)`; lanza `AlmacenamientoNoDisponible`/error técnico si no puede leer el archivo (⇒ 503, «no verificable», distinto de «inválido»). El adaptador `ValidadorDePdfEstructural` comprueba, leyendo bytes sin interpretarlos ni ejecutarlos: MIME real `application/pdf` por contenido (`fileinfo`), cabecera `%PDF-1.x`/`2.x` en los primeros 1024 bytes, marcador `%%EOF` en los últimos 1024, `startxref` con desplazamiento numérico dentro del tamaño, un objeto `/Catalog` (o `/Root` en el trailer) y al menos un par `obj…endobj`. No renderiza páginas, no interpreta JavaScript ni acciones. Un PDF cifrado con estructura válida se acepta (no se lee su contenido).
- **Rationale**: «no solo extensión o MIME»; sin librería de PDF disponible; rechaza imágenes, texto y ejecutables renombrados y PDFs truncados.
- **Límite conocido**: no es un análisis de seguridad completo ni antivirus (fuera de alcance); un PDF malicioso estructuralmente válido se acepta y se entrega con `nosniff` y sin ejecutarse en el servidor.
- **Alternativas**: librería de PDF (dependencia nueva); solo `fileinfo` (insuficiente: acepta PDF truncados).

## D4. Carga idempotente

- **Decisión**: `Idempotency-Key` UUID obligatorio (422 `CLAVE_DE_OPERACION_REQUERIDA` si falta); `operacion_id` único por `(tenant_id, operacion_id)` y `operacion_hash` = SHA-256 de `{ficha, actor, título normalizado, categoría, visibilidad, sha256 de los bytes}`. Misma clave y mismo hash ⇒ 200 con el documento existente, sin subir otra copia y sin reactivar uno archivado; misma clave con otro contenido o actor ⇒ 409 `CLAVE_DE_OPERACION_REUTILIZADA`. Dos intentos concurrentes: el ganador conserva su `object_key`; el perdedor, tras la violación de unicidad, relee el resultado en una transacción nueva y elimina solo su propio objeto.
- **Rationale**: mismo patrón que `RegistrarFicha` (GP-01); una respuesta perdida se recupera repitiendo la misma operación.
- **Alternativas**: deduplicar por contenido (impide cargar dos documentos iguales de forma explícita, contra la spec).

## D5. Acceso propio con permiso documental independiente

- **Decisión**: `ResolverFichaDocumentalPropia` usa `AccesosDeCuentaPort`: asociación válida en el mismo tenant (si no, `CuentaSinFicha`) y `puedeConsultarDocumentos` (si no, `SinPermisoDeDocumentos`); no exige `puedeConsultarFicha`. Listado y contenido propios consultan solo `colaborador_id` de la asociación, `visible_colaborador = true` y `estado = 'Disponible'`, y revalidan todo en cada petición. Documento privado, archivado, ajeno o de otro tenant ⇒ 404 idéntico, sin bytes.
- **Rationale**: FR-021/FR-022; el resolutor de GP-01 acopla ambos permisos y haría falsa la independencia.

## D6. Entrega de contenido

- **Decisión**: el endpoint `…/contenido` revalida acceso, abre el flujo del almacén y responde en streaming con `Content-Type: application/pdf`, `X-Content-Type-Options: nosniff`, `Cache-Control: private, no-store` y `Content-Disposition` con el nombre fijo `Documento_{id}.pdf` (`inline` por defecto; `attachment` con `descarga=1`). Si el almacén falla antes de enviar ⇒ 503 sin cuerpo parcial. No hay URL firmada, ni pública, ni `object_key` en ninguna respuesta.
- **Rationale**: «no entregar bytes si falla una dependencia» y revalidación en cada apertura.
- **Límite honesto**: una revocación posterior no recupera lo ya entregado ni copias descargadas.

## D7. Límite de tamaño y respuesta del proxy

- **Decisión**: el límite exacto es `max_bytes = 10 000 000` en el servidor (tamaño real del archivo recibido). `LimitarTamanoDeCarga` convierte en 413 `ARCHIVO_DEMASIADO_GRANDE` el caso en que PHP o el proxy descartan un cuerpo mayor que `post_max_size` (cuerpo vacío con `Content-Length` excesivo o `UPLOAD_ERR_INI_SIZE`/`UPLOAD_ERR_FORM_SIZE`); el frontend lo presenta como tamaño excedido. La configuración de PHP/proxy debe permitir al menos `upload_max_filesize ≥ 11M` y `post_max_size ≥ 12M` (el entorno actual trae 2M/8M); se documenta en el quickstart.
- **Rationale**: nunca se presenta un rechazo del proxy como éxito ni como error genérico.

## D8. Vistas del Colaborador

- **Decisión**: además de la pestaña «Documentos» en Mi ficha (`capacidades.documentos` ya filtra por el permiso documental para la vista propia), una página `/mis-documentos` que no depende de poder abrir Mi ficha; ambas montan `SeccionDocumentos` en modo solo lectura. La pestaña se monta solo al activarse.

## D9. Compensación periódica

- **Decisión**: comando `documentos:limpiar-huerfanos` (programado a diario en `routes/console.php`) que lista el prefijo `personal/` del almacén, ignora objetos de menos de 24 horas (configurable), comprueba que su clave no esté referenciada por `documentos_colaborador.object_key` y elimina solo los no referenciados. Nunca borra documentos publicados ni archivados. La programación requiere que el planificador de Laravel esté en ejecución.
- **Alternativas**: tabla de tareas de limpieza (prohibida: sin tabla nueva de auditoría; el barrido encuentra los restos por exclusión).

## D10. Visibilidad y archivo

- **Decisión**: `PATCH …/visibilidad` (`visible_colaborador`, `version`) y `PATCH …/archivo` (`version`) son `UPDATE … WHERE id, colaborador_id, tenant_id, version` que incrementan `version` y fijan `updated_by/updated_at` (y `archived_by/at` y `estado` al archivar). Solo documentos Disponibles: sobre uno archivado devuelven 409. Mismo valor con versión vigente ⇒ 200 sin escribir. La categoría inactiva no impide ninguna de las dos operaciones.

## D11. Datos de ejemplo

- **Decisión**: `PersonalSeeder` carga dos documentos sintéticos (PDF mínimo de unos cientos de bytes, sin datos personales) en el tenant 1: uno visible y uno archivado, idempotente; el permiso documental del colaborador de ejemplo se habilita solo en el entorno de demostración.
