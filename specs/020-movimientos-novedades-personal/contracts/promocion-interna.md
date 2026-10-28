# Contratos propuestos: promoción interna fundamentada

Revisión 2026-10-28, pendiente de implementación. Complementa [api-movimientos.md](api-movimientos.md) y concreta FR-020, FR-024 y FR-045–FR-052 de [spec.md](../spec.md). Los contratos mínimos de la entrega original no bastan para habilitar esta revisión.

## Propietarios y dependencias

Desarrollo y Formación produce la adecuación y acredita su evidencia; Estructura publica perfiles, criterios y posiciones; Personal tramita la propuesta, conserva la decisión y ejecuta asignaciones; la ficha 005 consulta resultados. No se implementa un evaluador nuevo en Personal.

`DesarrolloFacade::ultimaAdecuacion(tenantId, colaboradorId, puestoObjetivoId)` existe en `origin/develop` como punto de integración, pero su resumen no acredita por sí solo toda la evidencia siguiente. Integrar esa funcionalidad, revisar sus proveedores reales y ampliar su contrato público antes de reemplazar `EvaluacionDelDestinoNoInstalada`. Los proveedores SinDatos o exclusivos de pruebas no acreditan integración.

La fuente de niveles individuales debe acordarse con Desarrollo: los contratos antiguos esperan que GP los suministre, mientras 012 excluye esa captura. No resolver la contradicción asignando artificialmente niveles al registrar un movimiento. Conocimientos con grado desactivados se declaran pendientes cuando el perfil exige esa dimensión.

## Evidencia del puesto destino

Evolucionar `EvaluacionDelDestinoPort` sin fórmulas nuevas. La consulta se realiza en servidor con tenant, actor/audiencia autorizados, colaborador, posición y puesto destino e instante confiables; nunca se reciben resultados evaluativos desde el navegador.

| Campo lógico | Garantía requerida |
|---|---|
| Disponibilidad | Disponible, ausente, temporalmente no disponible o no verificable, con causa segura. Ninguna ausencia equivale a evaluación favorable. |
| Identidad | Identificador del diagnóstico, fuente, evaluaciones de origen, tenant y colaborador coincidentes. |
| Referencia | Puesto objetivo, versión o huella del perfil y de sus criterios; escalas y método identificables. |
| Tiempo | Fecha de evaluación y consulta; comprobación explícita de vigencia y, si corresponde, vencimiento. |
| Dimensiones | Conocimientos, experiencia y competencias, cada una Evaluada, Pendiente o NoAplicable. |
| Cobertura | Requisitos exigidos, evaluados y pendientes, identificados; no deducir el total a partir de una lista de brechas. |
| Resultados | Requerido, observado, unidad o escala, resultado y referencia de evidencia por requisito; desconocidos como null. |
| No aplicabilidad | Confirmación del perfil propietario y motivo; no es una opción libre que permita dispensar evaluación. |
| Diagnóstico | Brechas y preparación con el significado del proveedor; no es una aprobación administrativa. |

Una evaluación completa puede tener brechas. La suficiencia exige evaluar todos los requisitos obligatorios o acreditar que no aplican. Mostrar al Administrador los datos comunicables; no duplicar archivos ni acceder a tablas de Desarrollo. Los permisos del destino vuelven a verificarse al abrir la evaluación.

La aprobación conserva una instantánea de evidencia y sus referencias. La consulta histórica utiliza esa instantánea; el detalle distingue claramente evidencia de la decisión y diagnóstico actual. Los porcentajes o clasificaciones ya calculados por Desarrollo pueden presentarse identificados, pero no constituyen umbrales de aprobación. Esto sustituye la prohibición absoluta de «porcentajes» del contrato mínimo anterior: lo prohibido es inventarlos o convertirlos en decisión automática.

## Revalidación anterior a la aplicación

Proponer `POST /api/movimientos-personal/{id}/revalidacion`: administrador del tenant, `Idempotency-Key`, `version` y `justificacion` obligatorias. La evidencia se obtiene de Desarrollo y se verifica en servidor; no se aceptan niveles, resultados o perfiles suministrados por el cliente.

Solo para movimientos Aprobada, Programada o IncidenciaDeAplicacion todavía no aplicados. Requiere causa de revalidación, evidencia actual suficiente del mismo colaborador y destino, condiciones actuales verificadas y justificación administrativa. Conserva la política de la actuación y el registro de aprobación original. No cambia origen, destino o fecha efectiva ni aplica el movimiento.

Registra una transición adicional de auditoría `Revalidada`, manteniendo el estado y aumentando la versión. Su evidencia se conserva en el detalle de esa transición; la aplicación utiliza la última revalidación válida, o la aprobación si no hay revalidación. Una revalidación que después pierde vigencia no habilita ejecutar. Los reintentos de aplicación siguen siendo explícitos o programados según el estado; la revalidación no elimina una incidencia por sí sola.

Respuestas con las convenciones existentes: 200 detalle autorizado; 422 VALIDACION o EVALUACION_NO_VERIFICABLE; 409 SOLICITUD_MODIFICADA, ESTADO_NO_RESOLUBLE o CLAVE_DE_OPERACION_REUTILIZADA; 401/403/404 según autorización uniforme. Una repetición idéntica devuelve la operación registrada sin duplicarla. Una solicitud de otro tenant no revela su existencia.

## Coordinación de ocupación con Estructura

Las asignaciones laborales efectivas son la fuente de verdad. Estructura debe publicar ocupación coherente a través de su contrato, derivada de esas asignaciones o mantenida como proyección sincronizada en la misma transacción. No se aceptan dos estados independientes ni sincronización diferida presentada como éxito inmediato.

El plan técnico debe acordar la representación del colaborador, incluido el caso de una ficha sin cuenta. No imponer crear una cuenta para ocupar una posición. La cuenta opcional no sustituye la identidad laboral.

La operación atómica verifica tenant, vínculo, origen, destino, perfil/evidencia aplicables y ocupación temporal; cierra origen, abre destino y registra ocupación e historial conforme a la especificación 006. Si falla cualquier paso, revierte todos los cambios laborales y registra la incidencia aparte. No se escriben tablas ajenas directamente. Dos movimientos al mismo destino se serializan con las restricciones de integridad correspondientes.

El contrato común debe poder reutilizarse por ingresos y offboarding, sin incorporar esas implementaciones en esta revisión. Las pruebas de contrato deben cubrir su compatibilidad. No se abre una posición ni una convocatoria por aprobar un movimiento.

## Seguimiento y permisos

Publicar para 005 referencias autorizadas de actuación, diagnóstico de decisión, revalidaciones y asignación efectiva, sin consultas circulares ni acceso a tablas ajenas. La proyección propia conserva FR-037; los diagnósticos individuales visibles desde Desarrollo mantienen sus permisos independientes. La evolución y comparabilidad las declara Desarrollo; Personal no calcula un segundo avance ni cierra brechas.
