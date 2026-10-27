# Plan de implementación · GP-05 Documentos del colaborador

**Fecha:** 15-10-2026. **Especificación:** GP-05 v1.0. **Estado:** propuesta técnica. **Reglas:** GP05-RN01–RN10 y RC aplicables. **Aceptación:** GP05-CA01–GP05-CA08. **Base:** [base técnica incluida al final](#base-técnica-compartida).

## 1. Contexto y resumen

Implementar carga, lectura, descarga, corrección de visibilidad y archivo de PDF privados. El administrador gestiona; el colaborador solo consulta documentos propios visibles, disponibles y con permiso documental. Dependencias: GP-01, GP-04, identidad y almacenamiento privado. No hay eliminación definitiva, restauración, edición de título/categoría o contenido, OCR, firma, versiones o análisis de IA.

Stack/capas según la base. StoragePort mantiene independiente al dominio del bucket concreto; el adaptador implementa escritura, lectura de stream y eliminación exclusivamente para compensar objetos no publicados. El usuario no recibe una operación de eliminación.

## 2. Constitution Check y decisiones técnicas

Sin nueva auditoría, sin directorios jerárquicos configurables y sin permisos de carga del colaborador. Constitución real y configuración del bucket pendientes de contraste. No asumir que la subida y el commit PostgreSQL comparten una transacción: se utiliza objeto privado por intento y publicación transaccional de metadatos, con compensación.

La validez PDF exige más que extensión/MIME. Antes de habilitar la feature se debe probar un adaptador de validación estructural con archivos representativos. Reutilizar el validador del proyecto si lo hay; en ausencia de uno, investigar e integrar un parser mantenido compatible con PHP instalado. Esta elección se resuelve como investigación técnica de Fase 0, no como permiso para aceptar cualquier archivo con prefijo %PDF. No inventar restricciones de negocio adicionales como rechazo universal de documentos cifrados; si un formato no puede comprobarse, distinguir PDF_NO_VERIFICABLE de contenido confirmado inválido y revisar la capacidad del adaptador antes de dar la feature por lista.

## 3. Modelo de datos

Tabla propuesta documentos_colaborador:

| Campos | Regla |
|---|---|
| id | bigint identity PK |
| colaborador_id, categoria_id | FK internas restrict, NOT NULL |
| titulo | varchar(200) NOT NULL, no vacío |
| object_key | varchar(512) NOT NULL UNIQUE; clave interna opaca y privada |
| mime_type | varchar(64) NOT NULL; application/pdf para confirmados |
| size_bytes | bigint NOT NULL, CHECK > 0 AND <= 10000000 |
| sha256 | char(64) NOT NULL; integridad y huella de operación |
| visible_colaborador | boolean NOT NULL DEFAULT false |
| estado | varchar(10) NOT NULL DEFAULT 'Disponible'; CHECK Disponible/Archivado |
| version | integer NOT NULL DEFAULT 1 |
| created_by, created_at, updated_by, updated_at | Según la base |
| archived_by, archived_at | Nullable; completos al archivar |
| operacion_id, operacion_hash | uuid UNIQUE y char(64); carga idempotente |

No es necesario persistir nombre original del archivo para cumplir la feature. Descargar con un nombre seguro Documento_{id}.pdf; Content-Disposition no usa texto sin sanear del cliente. El título sigue siendo una etiqueta y puede repetirse. Sin columna tipo_documento, carpetas, revision o deleted_at.

Índice (colaborador_id,estado,created_at DESC,id DESC) y categoria_id. Un CHECK exige que Archivado tenga archived_by/at, y Disponible no; el tipo de referencia archived_by se adapta al actor del sistema. No se almacena un estado Subiendo como si el documento ya pudiera consultarse: un objeto no publicado no se incluye en las consultas de negocio.

## 4. Endpoints y permisos

| Operación | Ruta propuesta | Contrato |
|---|---|---|
| Cargar | POST /api/colaboradores/{colaborador}/documentos | multipart: titulo, categoria_id, archivo, visible_colaborador; Idempotency-Key; 201/200 repetición válida. |
| Listar | GET /api/colaboradores/{colaborador}/documentos | q de título, categoria_id, estado Disponible/Archivado; por defecto Disponible; admin. |
| Propios | GET /api/mi-ficha/documentos | Solo visibles y Disponible, permiso documental; no admitir estado=Archivado. |
| Consulta/descarga | GET /api/colaboradores/{colaborador}/documentos/{documento}/contenido | Admin; vista inline o attachment con parámetro descarga=1. Equivalente /api/mi-ficha/documentos/{documento}/contenido. |
| Visibilidad | PATCH /api/colaboradores/{colaborador}/documentos/{documento}/visibilidad | visible_colaborador y version; solo Disponible, admin; 200/409. |
| Archivar | PATCH /api/colaboradores/{colaborador}/documentos/{documento}/archivo | version; admin; cambia estado y metadatos; no restaura. |

Un documento ajeno a la ficha del path devuelve 404. La consulta propia revalida asociación de cuenta, permiso, ficha, visibilidad y estado antes de abrir stream. Los endpoints no aceptan object_key ni devuelven credenciales/URL pública. El administrador puede descargar archivados desde su vista autorizada.

422 para tamaño/formato/categoría/título inválidos; 409 para versión/clave de operación o una modificación incompatible por archivo; 503 para almacenamiento o validación técnica no disponibles. No enviar bytes si una dependencia falló.

## 5. Flujo de carga seguro y compensación

1. Resolver actor, tenant, ficha y capacidad; comprobar Idempotency-Key. Limitar el request de infraestructura por encima de 10 MB para multipart, manteniendo el límite exacto del archivo en 10 000 000 bytes. Una respuesta 413 del proxy debe presentarse como tamaño excedido, no como éxito.
2. Recibir el archivo en temporal privado; contar bytes reales y calcular SHA-256 durante lectura. No confiar en Content-Length ni MIME del navegador. Validar contenido y estructura con el adaptador. No renderizar páginas ni ejecutar JavaScript/acciones del PDF al validar.
3. Si existe operación terminada, comprobar actor, ficha y hash; devolver metadatos actuales sin subir otra copia. Si está archivado, no reactivarlo.
4. Escribir un objeto privado por intento con clave interna personal/{tenantInterno}/colaboradores/{id}/{uuidAleatorio}.pdf. Nunca utilizar el nombre del cliente y nunca sobrescribir el objeto de otro intento, aun con la misma clave de operación.
5. Abrir una transacción corta; volver a autorizar, comprobar ficha y bloquear la categoría. Exigir que siga Activa. Insertar metadatos completos y la unicidad de operación; confirmar.
6. Después del commit el documento es Disponible. Si falla la transacción, eliminar únicamente el objeto de este intento no publicado. Si falla esa limpieza, registrar una tarea técnica de limpieza sin exponer el objeto; no informar que la carga se completó.
7. Si dos intentos compiten, el ganador conserva su propio object_key; el perdedor verifica el resultado idempotente en una transacción nueva y limpia solo su clave. Nunca borra la clave del ganador. Una respuesta perdida se recupera repitiendo la misma operación.

Un proceso periódico de compensación revisa objetos huérfanos de más de 24 horas en el prefijo técnico, verifica que no estén referenciados por documentos de ese tenant y elimina solo esos objetos. No es una política de borrado de documentos archivados. La clave privada y validación de referencia impiden confundir un huérfano con un documento publicado.

## 6. Consulta, archivo y concurrencia

Transmitir contenido mediante endpoint autenticado del backend, sin URL firmada reutilizable por el colaborador después de revocar permisos. Cabeceras: Content-Type application/pdf, X-Content-Type-Options nosniff y Cache-Control private, no-store. Si se usa vista previa, un visor aislado y actualizado consume ese endpoint; ofrecer descarga si no hay visor.

Archivar y cambiar visibilidad son UPDATE versionados y autorizados. Cada nueva solicitud de contenido lee el estado vigente y el permiso vigente antes de iniciar la entrega; una revocación no recupera bytes ya entregados ni copias descargadas. No afirmar una revocación retroactiva imposible.

El nombre de categoría se consulta aunque esté inactiva. Desactivarla no cambia el permiso documental. Ante una lectura fallida del bucket responder error, no un archivo de cero bytes ni otro documento como sustituto.

## 7. Frontend

Pestaña Documentos: título, categoría, tamaño, fecha y acciones permitidas. Formulario Subir con visibilidad desmarcada; límite PDF/10 MB visible. Mantener título/categoría si falla, e indicar si debe seleccionarse de nuevo el archivo. No ofrecer tipo de documento ni carpetas.

El administrador puede buscar por título, filtrar categoría y alternar archivados. Subir no está disponible si no hay categorías activas; se ofrece ir al catálogo. Corrección de visibilidad y Archivo muestran feedback; archivo requiere confirmación. El colaborador ve solo la lista autorizada, sin controles de administración.

Durante una carga se deshabilita doble envío y se conserva Idempotency-Key hasta resolver. Al terminar, invalidar consultas de documentos. No actualizar optimistamente visibilidad/archivo antes del servidor cuando ello podría mostrar un acceso inexistente.

## 8. Verificación y criterios de salida

| Escenario | Prueba principal |
|---|---|
| GP05-CA01 | PDF válido publicado solo tras bucket+commit; privado por defecto y metadatos correctos. |
| GP05-CA02 | Límite exacto, un byte más, archivo vacío, MIME/extension falsos y PDF corrupto. |
| GP05-CA03 | Documento privado conocido por ID no entrega bytes al colaborador. |
| GP05-CA04 | Propiedad y permiso documental independientes de consulta de ficha. |
| GP05-CA05 | Archivar retira acceso propio y conserva lectura del administrador. |
| GP05-CA06 | Desactivación concurrente de categoría rechaza la carga que no logró publicar antes. |
| GP05-CA07 | Fallos de subida, commit y limpieza no producen falso éxito ni documento parcial. |
| GP05-CA08 | Retirar visibilidad impide una nueva apertura, sin prometer borrar descargas previas. |

Agregar aislamiento por prefijo/tenant, versión obsoleta, dos cargas con misma operación, misma clave con archivo diferente y compensación que no elimina el objeto ganador. Pruebas de aplicación con StoragePort falso; prueba de contrato sobre almacenamiento privado representativo y validador PDF real. Seeders usan PDFs sintéticos pequeños sin datos personales.

Fase 0: elegir/verificar validador estructural y disco privado, límites del proxy y mecanismo de limpieza. Fase 1: tabla/endpoints, publicación y compensación. Fase 2: UI y ocho CA. Salida: ningún documento confirmado sin bytes válidos, ninguna consulta fuera de alcance, y recuperación de fallos demostrada. El rollback de la aplicación no borra documentos ni objetos publicados.

---

# Base técnica compartida

**Versión:** 1.0 · 15 de octubre de 2026. **Carácter:** diseño propuesto para las especificaciones funcionales v1.0. No describe código inspeccionado ni migraciones ejecutadas.

## 1. Evidencia y precedencia

Entradas revisadas: Especificaciones_Gestion_Personal.docx (23 páginas, siete GP y 46 escenarios); Markdown pegado.md (plan del catálogo de puestos); Diagrama_C4_Gestión_Personal.txt. El paquete incluye una copia de referencia del texto funcional previamente generado, no una nueva aprobación de requisitos.

Precedencia: decisiones de negocio de las especificaciones > límites arquitectónicos del C4 > patrones útiles del ejemplo > decisiones técnicas de estos planes. El ejemplo sirve de estructura y detalle; no autoriza copiar sus tablas de auditoría, sus permisos de otros módulos ni su afirmación de que el modelo ya existe.

No se han suministrado para esta revisión el repositorio de aplicación, su constitution.md, composer.lock, package-lock ni un esquema vigente y completo de Personal. Las rutas y tablas de los planes son propuestas. Antes de crear algo, contrastar con el repositorio y reutilizar equivalentes. No se presenta un Constitution Check como aprobado sin leer la constitución real.

## 2. Contexto técnico

| Aspecto | Decisión de planificación |
|---|---|
| Backend | PHP/Laravel, monolito modular. C4 histórico: PHP 8.2 / Laravel 11. Usar las versiones fijadas por el repositorio y comprobar compatibilidad antes de añadir paquetes; no ejecutar una actualización de framework como parte de estas features. |
| Frontend | React SPA; reutilizar router, cliente HTTP, componentes y tokens existentes. No se presupone TypeScript, biblioteca de formularios o herramienta de estado sin comprobar el manifiesto. |
| Persistencia | PostgreSQL, esquema por tenant; sintaxis temporal compatible con PostgreSQL 15 del C4. |
| Documentos | Disco privado vía adaptador Flysystem/S3 o el almacenamiento equivalente configurado. |
| Capas | Presentación HTTP/fachada → aplicación → dominio y puertos → adaptadores de persistencia, estructura y almacenamiento. Eloquent permanece en infraestructura. |
| Pruebas | Suite PHP y React existente. Si no existe suite, PHPUnit para backend y React Testing Library con el runner compatible del proyecto; escoger las versiones al leer manifiestos. Integración temporal sobre PostgreSQL real. |
| Volumen y latencia | No se inventan SLA ni cifras de usuarios. Paginación de 20 por defecto, hasta 100 por petición; evitar consultas por fila. Medir con datos representativos antes de fijar umbrales. |

Rutas propuestas de código: backend/app/Modules/Personal/{Domain,Application,Infrastructure,Presentation}; frontend/src/modules/personal; pruebas en las convenciones existentes. Son ubicaciones de diseño, no carpetas verificadas. Las relaciones Eloquent directas son válidas entre datos propiedad de Personal; las consultas a Estructura e Identidad se realizan por contratos, no importando sus modelos ni escribiendo sus tablas.

## 3. Constitución y puertas de integración

| Puerta | Estado del diseño | Comprobación necesaria en el repositorio |
|---|---|---|
| Alcance, dos roles y sin finanzas/IA | Cubierto por las siete GP | Revisar correspondencia con spec.md extraído. |
| Aislamiento por tenant | Diseñado | Verificar middleware, conexión y pruebas de aislamiento. |
| Separación modular y C4 | Diseñada | Verificar interfaces y registro en el contenedor existente. |
| Modelo y migraciones | Propuestos | Comparar con DBML/migraciones reales; no crear duplicados ni eliminar tablas para ajustarlas al plan. |
| Constitution Check oficial | No verificable con las entradas actuales | Leer constitution.md real antes de implementar; registrar y resolver contradicciones. |
| Dependencias y capacidades | No ejecutadas | Confirmar PostgreSQL/extensiones, bucket, renderizador PDF y suite. |

Estas comprobaciones técnicas no requieren volver a preguntar preferencias de interfaz. Si revelan una contradicción de negocio o de integridad, bloquear solamente la parte dependiente y registrar la decisión necesaria. No reinterpretar requisitos silenciosamente.

## 4. Tenant, identidad y permisos

TenantContext es inmutable por petición y procede de autenticación y registro autorizado de tenants. Ningún body, query string o Idempotency-Key selecciona el esquema. Resolver un nombre de esquema únicamente desde dicho registro y citarlo con la API del driver; nunca interpolar un valor del cliente en SQL.

La infraestructura fija el esquema en la conexión del tenant y lo limpia al devolver conexiones al pool. Si se utiliza search_path, incluir solamente el esquema validado y pg_catalog; calificar los objetos de extensión cuando corresponda. Una tabla ausente no debe hacer que la consulta caiga sobre tablas homónimas de otro esquema. Pruebas obligatorias con dos tenants que compartan los mismos IDs numéricos. Cache, claves de archivos, trabajos y locks siempre incorporan el identificador interno del tenant.

IdentityAccessPort entrega actor autenticado, pertenencia al tenant, referencia de su ficha y permisos actuales. La cuenta no se almacena como ficha. No se agrega una tabla usuario, ni un colaborador.usuario_id duplicado, ni un mecanismo de login en estos planes. Los created_by/updated_by son referencias opacas al actor del sistema existente, no FK nuevas a auditoría. Si los identificadores reales son numéricos se conservan; su representación definitiva se adapta al contrato de identidad antes de migrar.

Permisos propuestos: personal.fichas.crear, editar, listar, consultar; personal.ingresos.registrar; personal.desarrollo.editar; personal.categorias.gestionar; personal.documentos.gestionar; personal.ficha_propia.consultar; personal.documentos_propios.consultar; personal.ficha_propia.exportar; personal.fichas.exportar. El administrador tiene todas las capacidades de su tenant. El colaborador recibe solo los tres permisos propios que el administrador habilite, inicialmente ninguno. Se usa el mecanismo actual de asignación; no se implementa un editor de roles nuevo.

Las rutas /api/mi-ficha/... resuelven la ficha desde el actor, sin aceptar colaborador_id alternativo. La exportación propia verifica su permiso independiente y no exige añadir permiso de consulta por pantalla. La consulta documental tampoco queda habilitada por consultar ficha.

## 5. Convenciones de API y consistencia

- Prefijos propuestos: /api/colaboradores, /api/categorias-documentales y /api/mi-ficha. Reutilizar el prefijo/versionado real si difiere; actualizar todos los contratos juntos.
- IDs de recursos: bigint en el modelo propuesto; JSON los entrega como cadenas decimales para evitar pérdida de precisión en JavaScript. Parámetros restringidos a dígitos; declarar rutas estáticas antes de {colaborador}.
- Fechas laborales: YYYY-MM-DD. Instantes: ISO 8601 UTC. TenantClock obtiene la fecha local usando una zona IANA existente en configuración; si falta, se rechazan operaciones dependientes y se informa un error de configuración. No asumir que todos los tenants están en Ecuador.
- Respuestas: {data: ...}; listados agregan meta con page, per_page y total. Errores: {error: {code, message, fields?}}. Reutilizar el envoltorio real si existe manteniendo semántica.
- HTTP: 401 sin autenticación; 403 sin capacidad; 404 recurso inexistente o fuera del alcance de la ficha/tenant; 422 datos o regla de negocio inválida; 409 versión obsoleta o clave de operación reutilizada con otro contenido; 503 dependencia necesaria no disponible. No filtrar detalles internos ni SQL.
- Cada registro editable tiene version integer NOT NULL DEFAULT 1. La petición de actualización incluye version. UPDATE ... WHERE id=? AND version=? incrementa version; cero filas afectadas produce 409 y no reintento automático de la escritura con datos antiguos. Recargar exige intervención visible del usuario.
- POST de creación manual, ingreso y documento exige Idempotency-Key UUID generado al abrir una operación nueva; se conserva durante sus reintentos. En la fila creada se guardan operacion_id, operacion_hash y actor de origen. La unicidad de la operación es por esquema. Una coincidencia solo se reproduce tras verificar actor, autorización, destino y hash; otra carga con esa clave devuelve 409. No crear una tabla genérica de idempotencia si la infraestructura no la tiene.
- Hash de operación: SHA-256 sobre datos validados normalizados en orden estable, referencia de destino y actor; para documentos incluye SHA-256 de los bytes. No registrar estos datos en logs. La unicidad por identificación sigue siendo obligatoria aunque cambie la clave de operación.
- Los cambios del estado laboral se calculan para una fecha; no se mantiene un estado textual que dependa de cron. Al cruzar medianoche, las nuevas lecturas reflejan el ingreso o salida efectiva. El frontend actualiza los datos al abrir o refrescar la ficha.

## 6. Propiedad de datos y migraciones

| Propietario | Datos propuestos |
|---|---|
| GP-01 | colaboradores; secuencia del código por esquema |
| GP-02 | vinculos_laborales, asignaciones_laborales |
| GP-03 | formaciones_academicas, experiencias_anteriores; columnas de movilidad en colaboradores |
| GP-04 | categorias_documentales |
| GP-05 | documentos_colaborador |
| GP-06 | Ninguna tabla nueva; lectura y renderizado |
| GP-07 | incorporaciones_seleccion; reutiliza la creación de GP-01 |

En un esquema nuevo son ocho tablas de negocio. En un esquema existente puede haber equivalentes: producir un diff de migraciones y ampliar lo necesario, conservando IDs e historia. Sin tenant_demo, sin tabla IA y sin nueva tabla de auditoría. FK internas restrictOnDelete; sin cascade delete y sin SoftDeletes en fichas. No agregar particionado mensual o motor de políticas para estas operaciones si no es necesario para las reglas del incremento.

Las migraciones se ejecutan por el mecanismo de provisioning de tenants, con registro de versión por esquema. Ensayar en dos esquemas y en una copia con datos. El rollback de aplicación no elimina datos; una migración inversa destructiva solo se utiliza en un entorno vacío. No reescribir datos productivos ni consolidar personas automáticamente para resolver índices únicos.

## 7. Contratos compartidos y dependencias

PersonalFacade expone operaciones tipadas para ficha e incorporación. EstructuraReadPort entrega posición, puesto, unidad y jefatura; ofrece consulta por lote y búsqueda de posiciones por nombre de puesto. Para ingreso se exige un método de validación/bloqueo en la misma transacción PostgreSQL del monolito; Estructura conserva la propiedad de su SQL.

LaboralReadPort entrega situación, ingreso programado y trayectoria a fecha. Su adaptador base devuelve ausencia de hechos solo cuando GP-02 no está instalado por configuración explícita. Un fallo de un módulo instalado produce no disponible; nunca se interpreta como Sin vínculo. Lo mismo se aplica a DesarrolloReadPort antes y después de instalar GP-03.

No se inventa un historial completo de movimientos u offboarding: definir adaptadores a sus hechos existentes. Si todavía no están implementados, declarar capacidad pendiente; GP-02 puede demostrar ingresos y asignaciones que sí son de su propiedad. El PDF incluye las secciones y hechos disponibles sin fabricar eventos.

## 8. Referencias técnicas consultadas

Consultadas el 15-10-2026, como soporte de mecanismos; las decisiones de dominio provienen de las especificaciones.

- Plan template de Spec Kit: https://github.com/github/spec-kit/blob/main/templates/plan-template.md
- Transacciones Laravel: https://laravel.com/docs/11.x/database#database-transactions
- Locks del query builder: https://laravel.com/docs/11.x/queries#pessimistic-locking
- Archivos privados y fallos de escritura: https://laravel.com/docs/11.x/filesystem
- Rangos y exclusiones PostgreSQL 15: https://www.postgresql.org/docs/15/rangetypes.html#RANGETYPES-CONSTRAINT
- Restricciones y límites de CHECK: https://www.postgresql.org/docs/15/ddl-constraints.html
- Aislamiento de lectura PostgreSQL: https://www.postgresql.org/docs/15/transaction-iso.html
- Renderizado PHP propuesto: https://github.com/dompdf/dompdf

Cada plan incorpora por referencia esta base. Al adoptar la estructura oficial de Spec Kit, extraer la investigación a research.md, el modelo a data-model.md y contratos a contracts/ sin mantener versiones contradictorias de la misma decisión. tasks.md se deriva después: este paquete no afirma que se haya ejecutado /speckit.plan ni sustituye su comprobación de constitución.
