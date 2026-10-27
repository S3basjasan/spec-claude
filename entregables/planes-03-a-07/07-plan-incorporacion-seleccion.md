# Plan de implementación · GP-07 Incorporación desde Selección

**Fecha:** 15-10-2026. **Especificación:** GP-07 v1.0. **Estado:** propuesta técnica. **Reglas:** GP07-RN01–RN08 y RC aplicables. **Aceptación:** GP07-CA01–GP07-CA07. **Base:** [base técnica incluida al final](#base-técnica-compartida).

## 1. Contexto y resumen

Implementar la llamada interna de Selección a la Fachada de Personal que crea o identifica una ficha a partir de una contratación confirmada. Este incremento no registra automáticamente el vínculo: el administrador lo confirma mediante GP-02. No se reutiliza cuenta del candidato ni se transfieren credenciales, pruebas, evaluaciones, archivos o salarios.

Backend PHP/Laravel, misma base PostgreSQL y contexto de tenant del monolito. El C4 describe llamada interna PHP, por lo que no se añade una API pública ni un webhook externo. Un consumidor externo futuro requeriría otro contrato y autorización; no queda aprobado por este plan.

## 2. Constitution Check y responsabilidades

GP-01 provee el caso de creación, no se duplica su lógica. Selección sigue siendo dueña del estado de contratación. La integración no introduce el rol Candidato en Personal, un usuario administrador ficticio ni un panel nuevo de incidencias. El actor interno autorizado es una capacidad de servidor registrada por el contenedor, nunca un atributo confiado a un body de usuario.

Constitución, modelo real de Selección y mecanismo de unidad de trabajo pendientes de comprobación. El contrato de Selección debe poder acreditar confirmación y tenant en un punto consistente, y devolver únicamente campos permitidos. No confiar en un booleano confirmado enviado por cualquier llamador.

## 3. Datos y contrato interno propuesto

La firma conceptual PersonalFacade.incorporarDesdeSeleccion(ContratacionRef, IntegrationContext) devuelve IncorporacionResultDTO. IntegrationContext se construye por infraestructura con tenant y capacidad verificados, sin datos del navegador que suplanten permisos.

SeleccionReadPort obtiene una ContratacionConfirmadaDTO dentro del tenant: referencia estable, confirmación válida, identificación, nombres/apellidos y complementarios autorizados de GP-01. Cuando la confirmación pueda cambiar, el adaptador de Selección bloquea su registro bajo la misma conexión/transacción de la incorporación; el módulo conserva su SQL y sus modelos. Confirmación y tenant deben seguir siendo válidos al ejecutar efectos.

| Resultado | Contenido permitido |
|---|---|
| Incorporada | Referencia de contratación, ficha_id y código de la ficha nueva. |
| Vinculada a ficha existente | Referencia, ficha_id y código ya existentes, sin sobrescribir datos. |
| Rechazada | Código y motivo funcional; sin información ajena al tenant. |

Rechazos distinguen CONTRATACION_NO_CONFIRMADA, DATOS_INVALIDOS, DISCREPANCIA_IDENTIDAD, REFERENCIA_INCONSISTENTE y CONTEXTO_NO_AUTORIZADO. Fallos transitorios se propagan como integración no disponible y no como contratación incorporada. No se define HTTP para una llamada interna: el adaptador de Selección traduce el resultado a sus propios endpoints/UI.

## 4. Modelo de datos

Tabla propuesta incorporaciones_seleccion, dentro del esquema del tenant:

| Campo | Restricción |
|---|---|
| id | bigint identity PK |
| contratacion_ref | varchar(120) NOT NULL UNIQUE; referencia opaca estable del módulo de origen |
| colaborador_id | FK restrict a colaboradores NOT NULL |
| tipo_identificacion_origen | varchar(12) NOT NULL |
| identificacion_clave_origen | varchar(50) COLLATE "C" NOT NULL; mismo normalizador de GP-01 |
| resultado | varchar(20) NOT NULL; Incorporada o VinculadaExistente |
| created_at, created_by | Instante y actor/capacidad interna verificable |

No se guardan rechazos como incorporaciones exitosas ni una ficha_id null a medio completar. El tenant está delimitado por esquema; UNIQUE(contratacion_ref) significa unicidad por tenant. Si las referencias reales de Selección son mayores que 120 o tienen otro tipo estable, adaptar antes de migrar y actualizar el contrato; no truncarlas.

No se impone unicidad sobre colaborador_id: contrataciones diferentes pueden referirse a la misma persona. No FK a una tabla desconocida de Selección; la referencia se conserva mediante su contrato. Si el repositorio ya registra esta relación en una integración equivalente, reutilizar ese registro en vez de crear otra tabla.

No guardar nombres, evaluaciones o payload completo por duplicado. La identificación de origen basta para detectar reintentos con identidad cambiada; el resultado apunta a la ficha persistente. La conservación de los datos de Selección sigue siendo responsabilidad de Selección.

## 5. Algoritmo e idempotencia transaccional

1. Resolver IntegrationContext autorizado y abrir una unidad de trabajo en la conexión del tenant. Obtener y verificar la contratación confirmada mediante SeleccionReadPort, con el bloqueo necesario.
2. Buscar incorporaciones_seleccion por referencia. Si ya hay resultado, verificar que la identificación normalizada sea la misma. Con otra identidad, rechazar; con la misma, devolver el resultado y ficha anteriores sin volver a crear, comparar nuevos complementarios o sobrescribir. Revalidar acceso/tenant antes de devolver.
3. Si no hay resultado previo, validar mínimos y buscar colaborador por el índice de identificación de GP-01, bajo la misma transacción.
4. Si existe ficha, comparar nombres y apellidos con normalización de compatibilidad: minúsculas, eliminación de diacríticos y espacios repetidos. Esta normalización es distinta de la clave de identificación. Si son compatibles, vincular sin sobrescribir ningún campo; si difieren, rechazar como discrepancia y no crear otra persona.
5. Si no existe ficha, invocar el servicio de registro de GP-01 dentro de la misma unidad de trabajo, con mínimos y complementarios permitidos. Producir código y metadatos; no crear cuenta o vínculo. No invocar el controlador HTTP manual ni asignar al integrador el rol Administrador.
6. Insertar incorporación con referencia, identidad y resultado; confirmar junto con cualquier ficha nueva. Las operaciones anidadas no pueden hacer un commit independiente de la unidad de trabajo raíz.
7. Si compiten solicitudes, UNIQUE de referencia e identificación protege el resultado. Tras rollback por conflicto, reabrir y reevaluar de forma acotada, como máximo tres intentos técnicos. Recuperar ganador válido o devolver fallo transitorio, sin omitir discrepancias.

Un rechazo por datos o discrepancia hace rollback sin guardar una incorporación a medio estado. La misma referencia puede reintentarse tras corregir la fuente porque no se consumió como incorporación exitosa. Una referencia ya completada nunca cambia silenciosamente a otra persona.

La llamada manual de GP-01 y esta integración comparten el mismo índice de identificación. Si una ficha manual aparece mientras se incorpora, el reintento detecta la existente y aplica la comprobación de compatibilidad; no crea duplicado ni sobrescribe.

## 6. Seguridad e integración de módulos

Implementar el núcleo de registro reutilizable con dos entradas de aplicación autorizadas: RegistrarFichaManual verifica administrador; IncorporarDesdeSeleccion verifica capacidad interna y confirmación. Ambas utilizan el mismo dominio y repositorio. No permitir que un cliente elija la entrada interna cambiando un campo actor_tipo.

No registrar payload de candidato en logs. Guardar solo correlación técnica y códigos de resultado; el actor interno y referencia de contratación son metadatos del negocio, sin tablas de auditoría nuevas. Filtrar las claves transferibles: los campos desconocidos no se asignan masivamente a colaboradores.

La vinculación cuenta-ficha sigue perteneciendo a Identidad. Un resultado Incorporada no significa usuario habilitado ni empleado Activo. El estado de la ficha nueva sigue Sin vínculo vigente hasta GP-02.

## 7. Presentación en Selección y enlace con Personal

Adaptar el punto de contratación confirmada de Selección para invocar la fachada y mostrar código de ficha y acción Abrir ficha al administrador autorizado. No se crea un panel de sincronización propio ni un botón de forzar fusión.

Una discrepancia muestra referencia y motivo al administrador del tenant desde el flujo de origen. Este revisa la ficha o corrige los datos de Selección y vuelve a intentar. Los errores transitorios permiten reintentar la misma referencia; no generar referencias nuevas para evitar un conflicto.

Tras abrir la ficha, Registrar ingreso es la acción de GP-02 con sus propias validaciones. No marcar la contratación como ingreso aplicado por el mero éxito de la incorporación. El colaborador no inicia esta integración ni modifica los datos recibidos.

## 8. Verificación y trazabilidad

| Escenario | Prueba |
|---|---|
| GP07-CA01 | Contratación confirmada nueva crea ficha/código y resultado, sin cuenta ni vínculo. |
| GP07-CA02 | Identidad y nombres compatibles devuelven existente y conservan datos complementarios previos. |
| GP07-CA03 | Repetir referencia e identidad devuelve mismo resultado sin segunda ficha/registro. |
| GP07-CA04 | Reintento que cambia identificación se rechaza y conserva relación previa. |
| GP07-CA05 | No confirmada, datos mínimos inválidos o tenant/capacidad incorrectos no producen efectos. |
| GP07-CA06 | Nombres discrepantes no se fusionan ni sobrescriben; variantes de tildes/caso/espacios sí son compatibles. |
| GP07-CA07 | Caída entre ficha e incorporación hace rollback; carrera y reintento terminan en una sola relación válida. |

Agregar carrera entre alta manual e integración, dos contrataciones de la misma ficha, cambio concurrente de confirmación en Selección y referencia igual en dos tenants. Dobles del puerto de Selección verifican el contrato; una prueba con su adaptador real comprueba tenant/confirmación/locks cuando exista.

## 9. Fases, migración y criterio de salida

Fase 0: contrastar firma/ref de Selección, permisos internos y unidad de trabajo compartida. Fase 1: tabla o adaptación, fachada y algoritmo reutilizando GP-01. Fase 2: enlace del flujo de Selección y siete CA. No generar cuentas o vínculos como compensación si algo falla.

Migración aditiva por tenant; si ya existen relaciones de incorporación, importarlas únicamente desde correspondencias verificadas, no desde similitud de nombres. Revertir aplicación conserva la tabla y fichas. Salida: contrato intermodular aceptado, siete escenarios y concurrencia demostrados, sin duplicidad ni escalada de permisos.

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
