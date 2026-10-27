# Plan de implementación · GP-04 Catálogo de categorías documentales

**Fecha:** 15-10-2026. **Especificación:** GP-04 v1.0. **Estado:** propuesta técnica. **Reglas:** GP04-RN01–RN06 y RC aplicables. **Aceptación:** GP04-CA01–GP04-CA05. **Base:** [base técnica incluida al final](#base-técnica-compartida).

## 1. Contexto y resumen

Gestionar un catálogo plano por tenant con creación, consulta, corrección, desactivación y reactivación. Se aplica el patrón del plan de Puestos: estado explícito, validación de unicidad, endpoints claros y ausencia de DELETE. La regla de Puestos que bloquea desactivación por posiciones en uso no se copia: una categoría sí puede desactivarse con documentos asociados.

Stack y capas según la base. La feature puede implementarse y probarse sin documentos existentes; GP-05 consume después el catálogo. No se agrega «tipo de documento», jerarquía de carpetas ni plantillas de archivos.

## 2. Constitution Check

Diseño compatible con dos roles, esquema por tenant, conservación de asociaciones y sin nueva auditoría. La constitución y el esquema reales deben contrastarse antes de migrar. Solamente el administrador posee personal.categorias.gestionar. El colaborador puede ver la etiqueta de la categoría de un documento autorizado, sin acceder por eso a administrar el catálogo.

## 3. Modelo de datos

Tabla propuesta categorias_documentales:

| Campo | Tipo y regla |
|---|---|
| id | bigint identity PK |
| nombre | varchar(100) NOT NULL; texto no vacío recortado |
| nombre_clave | varchar(100) COLLATE "C" NOT NULL, UNIQUE |
| nombre_busqueda | varchar(100) NOT NULL, normalización insensible a tildes para búsqueda |
| estado | varchar(8) NOT NULL DEFAULT 'Activa'; CHECK Activa/Inactiva |
| version | integer NOT NULL DEFAULT 1 |
| created_by/updated_by y timestamps | Según la base; sin nueva tabla de historial |

nombre_clave aplica NFC, recorte exterior y minúsculas; conserva tildes, conforme a la unicidad funcional. nombre_busqueda permite consultas cómodas sin cambiar esa regla. La categoría inactiva también conserva unicidad del nombre. Los documentos referencian su ID y leen el nombre vigente; no se versiona el nombre.

Índice propuesto (estado,nombre_busqueda,id). FK documental desde GP-05 con restrictOnDelete. No soft delete. Sin tabla carpetas, tabla tipos o columnas padre_id. No se necesita codigo de categoría porque la especificación no lo solicita.

## 4. Backend y contratos HTTP

| Operación | Endpoint | Entrada y comportamiento |
|---|---|---|
| Crear | POST /api/categorias-documentales | nombre; 201, estado inicial Activa. |
| Listar | GET /api/categorias-documentales | q, estado=Activa/Inactiva/Todas, page, per_page; estado inicial Activa, orden nombre/id. |
| Consultar | GET /api/categorias-documentales/{categoria} | id, nombre, estado, version y metadatos permitidos. |
| Corregir | PATCH /api/categorias-documentales/{categoria} | nombre y version; 200 o 409. |
| Estado | PATCH /api/categorias-documentales/{categoria}/estado | estado y version; 200 o 409. |

Siempre autenticar y autorizar antes de devolver información. Nombre vacío, excedido o duplicado: 422 con campo nombre. Estado inválido: 422. Un estado ya solicitado puede devolver 200 sin cambios si coincide la versión; una versión obsoleta no se fuerza aunque el resultado visual parezca equivalente.

Casos de uso CrearCategoria, EditarCategoria y CambiarEstadoCategoria. CategoriaRepository implementado por Eloquent en infraestructura. No endpoints DELETE ni acción para eliminar una categoría sin documentos. Las reglas permiten una gestión uniforme y conservadora.

## 5. Transacciones y coordinación con GP-05

Crear/corregir valida y escribe en transacción; UNIQUE es la garantía ante dos creaciones concurrentes. Editar/cambiar estado usa version para impedir sobrescrituras. Cambiar nombre no vuelve a guardar cada documento asociado: la FK conserva identidad y la consulta recupera la etiqueta vigente.

Para una carga documental, GP-05 obtiene un lock de la fila de categoría dentro de la transacción que publica el documento y comprueba Activa. El cambio de estado adquiere ese mismo lock mediante UPDATE. Si desactivar se confirma primero, la carga se rechaza; si la carga se confirma primero, permanece asociada y luego se permite desactivar. No mantener el lock durante la transferencia del archivo al bucket.

El archivo previo a la publicación siempre es privado y temporalmente no visible para usuarios. Por eso una categoría desactivada durante una transferencia larga no produce un documento presentado como válido sin cumplir su estado.

No se consulta el número de documentos para bloquear la desactivación. No se hace una actualización masiva de visibilidad o archivo. Revalidar permisos y estado en el servidor, no confiar en el selector que estaba abierto.

## 6. Frontend

Pantalla Categorías documentales del administrador: listado con nombre, estado, búsqueda, filtro y paginación; Nueva categoría, Editar y Activar/Desactivar. Reutilizar componentes y tokens de diseño del proyecto; no presumir que la pantalla ya existe en el prototipo.

Crear/editar utiliza un único formulario. Desactivar abre confirmación: «La categoría dejará de estar disponible para nuevas cargas. Sus documentos se conservarán». Tras éxito refrescar catálogo y selectores abiertos; ante 409 se informa que los datos cambiaron y se permite recargar. No hay botón Eliminar ni navegación de carpetas anidadas.

El selector de GP-05 usa este catálogo con estado Activa, pero la lista documental puede seguir mostrando etiquetas inactivas ya asociadas. Esos dos contextos no comparten una regla de ocultar indiscriminadamente categorías inactivas.

## 7. Pruebas y datos

| Escenario | Prueba |
|---|---|
| GP04-CA01 | Crear nombre libre produce Activa y aparece en opciones de carga. |
| GP04-CA02 | «Contratos» y « contratos » colisionan en el mismo tenant; se permiten en tenants distintos. |
| GP04-CA03 | Desactivar una categoría utilizada no elimina ni archiva documentos, y deja de ofrecerse para carga. |
| GP04-CA04 | Reactivar permite seleccionar sin alterar documentos o sus permisos. |
| GP04-CA05 | Colaborador sin capacidad no puede crear, editar o cambiar estado por API. |

Verificar además dos creaciones concurrentes, versiones obsoletas, renombrado conserva ID, filtro de inactivas y coordinación de lock con GP-05. Fixtures sintéticas: Contratos, Certificados y una categoría Inactiva. Cuando GP-05 esté instalado, documentos de ambas visibilidades para demostrar conservación. Antes de instalarlo, probar la feature sin acceder a una tabla documental inexistente.

## 8. Fases, migración y salida

Fase 0: comprobar catálogo equivalente, permisos y constitución. Fase 1: migración aditiva, casos de uso/endpoints y UI. Fase 2: cinco CA y concurrencia; integración con GP-05 al estar disponible. Si existen nombres duplicados, reportar antes del índice sin renombrar automáticamente. Revertir aplicación conserva categorías y asociaciones. Salida: catálogo verificable por sí mismo y semántica de desactivación demostrada.

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
