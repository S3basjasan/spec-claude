# Plan de implementación · GP-03 Información de desarrollo profesional

**Fecha:** 15-10-2026. **Especificación:** GP-03 v1.0. **Estado:** propuesta técnica. **Reglas:** GP03-RN01–RN07 y RC aplicables. **Aceptación:** GP03-CA01–GP03-CA06. **Base:** [base técnica incluida al final](#base-técnica-compartida).

## 1. Contexto y resumen

Mantener formación académica, experiencia anterior y disponibilidad para movilidad como datos de desarrollo de una ficha existente. El administrador agrega/corrige; el colaborador consulta lo propio con permiso de ficha. No requiere GP-02, no crea vínculos y no calcula adecuación.

PHP/Laravel, React y PostgreSQL por tenant según la base. Usar agregados pequeños: cada fila académica o de experiencia se guarda de forma independiente; movilidad es un bloque del colaborador. Evitar reemplazar todas las colecciones como en el ejemplo de funciones de puestos: aquí no se ha aprobado eliminar registros y un reemplazo podría borrar información inadvertidamente.

## 2. Constitution Check y dependencias

Diseñado sin roles adicionales, finanzas, idiomas, borrado o IA; no crea un historial paralelo. GP-01 es la única dependencia de negocio. Identidad y TenantContext según la base. Constitution Check real y esquema vigente pendientes de revisión en el repositorio.

No acoplar la feature al módulo de Formación para gestionar cursos ni a un catálogo de competencias. Una formación académica es un dato de la ficha, no una inscripción en una capacitación.

## 3. Modelo de datos

| Tabla / bloque | Campos y restricciones |
|---|---|
| formaciones_academicas | id bigint PK; colaborador_id FK restrict; nivel varchar(100), titulo_programa varchar(200), institucion varchar(200), estado varchar(12) NOT NULL; fecha_inicio/fecha_finalizacion date nullable; observaciones varchar(2000) nullable; version default 1; metadatos de actor/instantes. |
| experiencias_anteriores | id bigint PK; colaborador_id FK restrict; organizacion y cargo varchar(200) NOT NULL; fecha_inicio date NOT NULL; fecha_finalizacion date nullable; funciones/observaciones varchar(2000) nullable; version default 1; metadatos de actor/instantes. |
| colaboradores, ampliación de GP-01 | movilidad_estado varchar(14) NOT NULL DEFAULT 'NoInformada'; movilidad_observaciones varchar(2000) nullable; movilidad_actualizada_at timestamptz nullable. Usar version del colaborador, sin tabla adicional. |

Estados almacenados de formación: EnCurso, Completada, Incompleta; se traducen para presentación. Movilidad: Disponible, NoDisponible, NoInformada. CHECK para esos valores y fechas: finalización null o inicio null o finalización >= inicio; en experiencia el inicio nunca es null. Validar contenido no vacío en los textos obligatorios. No imponer un catálogo de niveles que no haya sido acordado: nivel es texto de hasta 100.

No hay UNIQUE sobre título o experiencia: dos registros parecidos no prueban duplicidad real. Los índices son (colaborador_id,id) en formación y (colaborador_id,fecha_inicio DESC,id DESC) en experiencia. Sin cascade delete ni endpoints DELETE.

La ampliación asigna NoInformada a fichas existentes, sin inventar observaciones o fecha de declaración. Ante una escritura efectiva de movilidad se actualiza movilidad_actualizada_at con la hora del servidor. Repetir exactamente el mismo valor no fabrica una nueva declaración; se puede responder 200 sin incrementar versión si no cambió el bloque.

## 4. Backend y API

| Operación | Ruta propuesta | Entrada y resultado |
|---|---|---|
| Consultar desarrollo | GET /api/colaboradores/{colaborador}/desarrollo | Admin; listas y bloque de movilidad con versiones. Ruta propia: /api/mi-ficha/desarrollo. |
| Agregar formación | POST /api/colaboradores/{colaborador}/formaciones | nivel, titulo_programa, institucion, estado; fechas/observaciones opcionales; 201. |
| Corregir formación | PATCH /api/colaboradores/{colaborador}/formaciones/{formacion} | Campos editables y version; 200/409. |
| Agregar experiencia | POST /api/colaboradores/{colaborador}/experiencias | organizacion, cargo, fecha_inicio; finalización/funciones/observaciones opcionales; 201. |
| Corregir experiencia | PATCH /api/colaboradores/{colaborador}/experiencias/{experiencia} | Campos editables y version; 200/409. |
| Guardar movilidad | PATCH /api/colaboradores/{colaborador}/movilidad | estado, observaciones y version del colaborador; 200/409. |

POST de filas no se reintenta automáticamente por la aplicación: no hay una identidad natural que permita deduplicar registros académicos parecidos. Deshabilitar doble envío y, si se pierde la respuesta, recargar la colección antes de ofrecer una creación nueva. Si el repositorio posee idempotencia estándar, reutilizarla sin agregar una tabla nueva. La suite debe comprobar que la UI no dispara dos peticiones por un clic.

Validar todas las fechas como fechas reales y sin conversión implícita desde timestamps. Solo fecha de nacimiento en GP-01 tiene la regla de no futura; no agregar aquí restricciones sobre fechas futuras o experiencia «actual» no definidas. Si finalización falta, devolver null y no un booleano trabajo_actual inferido.

Casos de uso: Agregar/CorregirFormacion, Agregar/CorregirExperiencia y ActualizarMovilidad; lector de desarrollo por lote para GP-06. Los IDs de filas siempre se resuelven dentro de la ficha del path y del esquema autenticado. Si una fila existe pero pertenece a otra ficha, devolver 404; no moverla a la ficha solicitada.

## 5. Concurrencia y transacciones

Cada operación valida y escribe su bloque completo en una transacción corta. En PATCH reconstruir el estado final con campos actuales y cambios; comprobar fechas y obligaciones antes de UPDATE versionado. Una fila académica inválida no afecta filas de experiencia ni movilidad.

Movilidad actualiza colaboradores mediante WHERE version e incrementa la versión común, de modo que una edición simultánea de datos personales detecte el conflicto. Las ediciones de una formación usan la versión de esa formación, no bloquean toda la ficha por defecto y no sobrescriben otras colecciones.

Los formularios no publican cambios optimistas como persistidos. Un error de permisos, validación o persistencia deja la versión anterior. Los logs registran código y contexto técnico, no el contenido académico o de observaciones.

## 6. Frontend

Pestaña Desarrollo con tres bloques: Formación académica, Experiencia anterior y Disponibilidad para movilidad. Formularios y listas reutilizan componentes del proyecto. Los estados se muestran con etiquetas legibles y sin confundir No informada con No disponible.

Al agregar una fila, los campos obligatorios se exigen dentro de ella; las demás listas pueden permanecer vacías. Guardar/cancelar actúa sobre esa fila. Editar conserva su ID; no usar una sustitución total de arreglos. Sin acción de eliminar registros guardados. La experiencia se ordena por inicio descendente y luego ID; una finalización vacía se etiqueta No registrado.

La movilidad muestra estado, observaciones y fecha de actualización si existe. No mostrar botones para aplicar traslados, predicciones de adecuación ni insignias inferidas de estudios. El colaborador recibe solo lectura y ausencia de controles de edición; la API aplica igualmente las restricciones.

## 7. Estrategia de pruebas y trazabilidad

| Escenario funcional | Verificación técnica |
|---|---|
| GP03-CA01 | Colecciones vacías, valor inicial NoInformada y sin cálculo derivado. |
| GP03-CA02 | Formación válida sin fechas; estado permitido; campos mínimos por fila. |
| GP03-CA03 | Fecha final anterior al inicio produce 422 y no modifica otras filas; también probar PATCH parcial. |
| GP03-CA04 | Experiencia sin finalización conserva null y no etiqueta empleo vigente. |
| GP03-CA05 | Guardar Disponible actualiza fecha, sin crear vínculo/asignación. |
| GP03-CA06 | Solo lectura propia, denegación de escritura y de IDs ajenos. |

Agregar prueba de versiones concurrentes de fila y de movilidad, datos de dos tenants y registro que intenta cambiar colaborador_id. Fixtures: estudios en los tres estados, experiencia superpuesta permitida, fechas opcionales y las tres opciones de movilidad. No fijar un máximo arbitrario de filas de colección copiado del catálogo de puestos.

## 8. Fases y criterio de salida

Fase 0: confirmar tablas equivalentes y mecanismo de versión. Fase 1: ampliación aditiva, repositorios/casos de uso, endpoints y formularios. Fase 2: seis CA, permisos y concurrencia. El rollback de aplicación conserva datos; no borrar colecciones para retirar la pestaña. Salida: seis escenarios demostrables sin depender de adecuación, documentos o movimientos.

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
