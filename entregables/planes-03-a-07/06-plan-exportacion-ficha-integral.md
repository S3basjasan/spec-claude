# Plan de implementación · GP-06 Exportación de ficha integral

**Fecha:** 15-10-2026. **Especificación:** GP-06 v1.0. **Estado:** propuesta técnica. **Reglas:** GP06-RN01–RN06 y RC aplicables. **Aceptación:** GP06-CA01–GP06-CA06. **Base:** [base técnica incluida al final](#base-técnica-compartida).

## 1. Contexto y resumen

Generar un PDF individual con una lectura coherente de la información guardada. El administrador exporta fichas de su tenant; el colaborador solo la propia con su permiso de exportación. El permiso de consulta en pantalla no se exige como permiso adicional si la exportación está habilitada; tampoco la exportación concede consulta documental.

PHP/Laravel, React y PostgreSQL según la base. Elegir el renderizador existente si cumple seguridad y formato; si no existe, propuesta técnica: dompdf/dompdf en versión compatible con composer.lock y fijada por el gestor. La biblioteca se encapsula en PdfRendererPort. No instalar una versión supuesta ni añadir un servicio externo de conversión que reciba datos personales.

## 2. Constitution Check y límites

Diseño sin tablas nuevas, sin IA, sin finanzas, sin exportación masiva y sin guardar automáticamente el PDF como documento. Pendiente comprobar constitución, dependencias y convención de almacenamiento temporal del repositorio. GP-01 es imprescindible; los lectores de GP-02 y GP-03 y de trayectoria deben declarar sus capacidades de forma explícita según la base.

No incorporar archivos adjuntos ni direcciones privadas a ellos. No depender de GP-05 para renderizar. No agregar colas o notificaciones de exportación como funcionalidades de usuario no especificadas; la primera implementación es síncrona con preparación completa antes de entregar.

## 3. Modelo de datos y contrato de lectura

No hay tabla exportaciones ni historial PDF. Definir PersonalExportSnapshotDTO con una lista cerrada de campos:

- Identificación: código, tipo/número, nombres y apellidos.
- Complementarios: nacimiento, nacionalidad, género, estado civil, hijos, dirección, teléfono, correo y contacto de emergencia.
- Laboral: fecha de referencia, estado derivado, vínculo vigente o ingreso programado, tipo de contrato, posición, puesto y unidad.
- Desarrollo: formación, experiencia anterior y movilidad, con fechas y observaciones registradas.
- Trayectoria: hechos disponibles con fecha efectiva y referencias/descripciones autorizadas.
- Metadatos de salida: instante de lectura, zona del tenant e instante de generación. No volcar metadatos internos, hashes, claves de bucket, usuarios técnicos o datos de otras fichas.

Reutilizar DTO de lectura o mapeadores de las GP responsables, con adaptadores por lote; no serializar modelos ORM completos ni consultar tablas de otros módulos desde el renderizador. El DTO transforma ausencias en las etiquetas funcionales únicamente cuando la consulta confirma que el dato no está registrado. Un error de dependencia no es un dato ausente.

## 4. Coherencia de lectura y permisos

Flujo propuesto:

1. Autenticar, resolver tenant y referencia de ficha; autorizar exportación y propiedad. No recibir el contenido de la ficha desde el navegador.
2. Abrir una transacción de lectura REPEATABLE READ READ ONLY en la conexión del tenant antes de la primera consulta de datos de exportación. Los lectores de Personal y Estructura usan esa misma conexión/contexto de snapshot para que una generación no mezcle versiones de lecturas separadas.
3. Materializar el DTO completo y comprobar capacidades y fallos. Cerrar la transacción antes de renderizar para no mantener una transacción abierta durante un trabajo costoso.
4. Renderizar sobre el DTO inmutable y un temporal privado. La instantánea es la versión guardada tomada al comenzar la generación; cambios posteriores no se mezclan en el documento. Se identifican instante de lectura y generación para evitar atribuirle una actualidad que no tiene.
5. Comprobar que el render terminó y produjo un PDF no vacío. Volver a consultar permisos y asociación de identidad en contexto fresco, fuera del snapshot anterior, justo antes de iniciar la entrega. Si se revocaron, eliminar el temporal y devolver denegación.
6. Entregar el archivo terminado y eliminar el temporal al finalizar/abortar la respuesta. No marcar como correcta una generación fallida antes de los encabezados.

Si un lector instalado accede a otra conexión o a una fuente externa que no participa en el snapshot, documentar y resolver su contrato de versión antes de declarar coherencia global. No presentar varias llamadas sin coordinación como una instantánea transaccional. La Fase 0 debe confirmar esta propiedad en el monolito real.

La comprobación final garantiza que una autorización revocada antes de iniciar la entrega impide entregar. No puede recuperar archivos ya descargados ni bytes enviados antes de una revocación posterior.

## 5. Backend y API

| Operación | Ruta propuesta | Respuesta |
|---|---|---|
| Exportar ficha del tenant | GET /api/colaboradores/{colaborador}/exportacion.pdf | Administrador con capacidad; PDF attachment o error JSON antes de transmitir. |
| Exportar propia | GET /api/mi-ficha/exportacion.pdf | Resolver propietario desde identidad y permiso personal.ficha_propia.exportar; nunca aceptar otro ID por body/query. |

Content-Type application/pdf; Content-Disposition attachment con nombre seguro Ficha_EMP-###.pdf; Cache-Control private, no-store; X-Content-Type-Options nosniff. No URL pública permanente. Repetir GET crea otra representación de lectura, no nuevos hechos o documentos; no necesita Idempotency-Key ni fila persistente.

403 por permiso denegado; 404 por ficha fuera de alcance; 503 EXPORTACION_NO_DISPONIBLE si no se puede obtener un dato necesario o renderizar; el sistema no devuelve un PDF válido con un mensaje técnico de error dentro como sustituto del contenido solicitado.

Casos de uso propuestos: GenerarFichaPdf y ConsultarSnapshotFicha. El controlador solo decide alcance/respuesta; el ensamblador obtiene datos por puertos; el renderizador recibe exclusivamente el DTO. Si el proyecto ya tiene una fachada de exportaciones, adaptar la operación manteniendo estas garantías.

## 6. Plantilla y seguridad de renderizado

Plantilla servidor con título, código, secciones, numeración de páginas y fecha. Datos de usuario escapados como texto; no admitir HTML, CSS o URLs arbitrarias en observaciones. Bloquear evaluación PHP y carga de recursos remotos en el renderizador; restringir su raíz de archivos a plantillas, fuentes y assets permitidos. Las imágenes o fuentes se empaquetan localmente si se usan.

Utilizar fuente local con caracteres españoles. La plantilla debe envolver texto largo y paginar listas sin recortar registros. Evitar una única fila de tabla que contenga toda una observación extensa o toda una colección, porque algunos renderizadores no pueden dividirla bien entre páginas. Mostrar cada registro como bloque con saltos controlados.

Los límites de memoria/tiempo corresponden a la infraestructura real y deben medirse; no introducir un máximo de formación o experiencia para hacer caber el PDF. Si no puede generarse por un límite técnico, informar fallo y registrarlo sin truncar datos silenciosamente. El temporal permanece privado y no se incluye en respaldos de documentos del colaborador.

## 7. Frontend

Botón Exportar ficha visible para el administrador y para el colaborador según su permiso independiente. La acción solicita el PDF al backend usando el mecanismo de autenticación existente, muestra progreso y deshabilita doble clic mientras esa petición está en curso.

Si se utiliza fetch con blob, comprobar status y Content-Type antes de crear el enlace de descarga; no guardar una respuesta JSON de error con extensión .pdf. Liberar object URL tras descargar. Los cambios del formulario no guardados no viajan al exportador y no aparecen como persistidos.

Una falla muestra mensaje y permite reintento; no afirma que el archivo quedó agregado a Documentos. Al revocarse permiso y recibir 403 se actualiza el estado de la acción. No ofrecer CSV ni selector de múltiples personas.

## 8. Verificación, investigación y salida

| Escenario | Verificación |
|---|---|
| GP06-CA01 | PDF válido con contenido comparable con los datos guardados; no hay escrituras nuevas. |
| GP06-CA02 | Propia autorizada y rechazo a ID de otra ficha/tenant. |
| GP06-CA03 | Consulta sin exportación devuelve 403; exportación propia autorizada no se confunde con permiso documental. |
| GP06-CA04 | Ausentes etiquetados, movilidad No informada, sin bytes ni URLs de adjuntos. |
| GP06-CA05 | Cambios locales sin guardar no alteran la representación. |
| GP06-CA06 | Fallo del lector/renderizador y revocación antes de entrega no producen archivo parcial accesible. |

Prueba de integración para lectura coherente con cambios concurrentes y autorizaciones finales fuera del snapshot. Prueba real del renderizador con acentos, listas de varias páginas y texto largo; extraer texto y revisar páginas representativas visualmente. No basta con afirmar que la respuesta empieza por %PDF.

Fase 0: verificar biblioteca y snapshot de todos los lectores. Fase 1: DTO, lectura, plantilla y endpoints. Fase 2: seis CA, datos extensos, fallos y revisión visual. No hay migración de negocio ni tarea que borre PDFs históricos. El rollback retira el endpoint y conserva datos originales. Salida: contenido completo/legible, privacidad y entrega autorizada demostradas.

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
