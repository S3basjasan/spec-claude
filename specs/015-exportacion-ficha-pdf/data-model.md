# Data Model: Exportación individual de ficha integral

**No hay tablas nuevas, columnas nuevas ni historial de exportaciones.** La funcionalidad solo lee. El único «modelo» es la instantánea inmutable que recibe el renderizador, con una lista cerrada de campos; no se serializan modelos de persistencia.

## `SnapshotDeFichaDTO` (inmutable, `GP/Application/DTO`)

| Sección | Campos | Origen (lectura existente) | Ausencia |
|---|---|---|---|
| Identificación | `codigo`, `tipo_identificacion`, `numero_identificacion`, `nombres`, `apellidos` | `ColaboradorRepositoryInterface::porId` | obligatorios; siempre presentes |
| Datos personales | `fecha_nacimiento`, `nacionalidad`, `genero`, `estado_civil`, `numero_hijos`, `direccion`, `telefono`, `correo_contacto`, `emergencia_nombre`, `emergencia_relacion`, `emergencia_telefono` | ficha guardada | «No registrado» cuando la lectura confirma `null` |
| Laboral | `fecha_de_referencia` (hoy del tenant), `estado`, `vinculo_vigente` o `proximo_ingreso` (`fecha_ingreso`, `tipo_contrato`, `posicion`, `puesto`, `unidad`) | `ConsultarSituacionLaboral` | sin vínculo vigente ⇒ estado «Sin vínculo vigente» y sin datos inventados; un ingreso futuro va como `proximo_ingreso`, nunca como vigente |
| Formación | lista de `nivel`, `titulo_programa`, `institucion`, `estado`, `fecha_inicio`, `fecha_finalizacion`, `observaciones` | `DesarrolloRepositoryInterface::formaciones` | lista vacía confirmada ⇒ sección «sin registros» |
| Experiencia anterior | lista de `organizacion`, `cargo`, `fecha_inicio`, `fecha_finalizacion`, `funciones`, `observaciones` | `DesarrolloRepositoryInterface::experiencias` | ídem |
| Movilidad | `estado`, `observaciones`, `actualizada_at` | `DesarrolloRepositoryInterface::movilidad` | `NoInformada` es el estado registrado, no un fallo |
| Trayectoria | lista de hechos `tipo`, `fecha_efectiva`, `programado`, `tipo_contrato`, `puesto`, `unidad` | `ConsultarTrayectoria` (con disponibilidad) | sin hechos confirmados ⇒ «sin registros» |
| Metadatos de salida | `leido_en` (instante de lectura), `generado_en`, `zona_horaria` del tenant, `nombre_de_archivo` (`Ficha_{codigo}.pdf`) | reloj del servidor y contexto del tenant | siempre presentes |

Queda fuera, de forma explícita: `id` internos, `version`, `created_by`/`updated_by`, claves de operación, hashes, `object_key`, cualquier dato de documentos, información financiera y todo porcentaje, grado o indicador calculado. Las etiquetas («No registrado», «No informada», etiquetas de estado y de tipo de contrato) las aplica `EtiquetasDeExportacion` (PHP puro) solo cuando la lectura fue exitosa.

## Reglas

1. El DTO se arma únicamente con lecturas exitosas; si cualquier fuente falla, no se construye (`ExportacionNoDisponible`).
2. El DTO se arma en una sola transacción de lectura (instantánea) y no cambia después.
3. El mismo alcance de datos personales y laborales que la pantalla: no se agregan campos que la pantalla no muestre.
4. Un ingreso futuro nunca ocupa el lugar del vínculo vigente; los vínculos anteriores solo aparecen en la trayectoria.
5. Ningún dato de la solicitud del navegador (cuerpo, consulta, formulario sin guardar) entra en el DTO.

## Estados

La exportación no tiene estados persistentes. Su ciclo en memoria es: solicitada → autorizada → leída (instantánea) → renderizada → revalidada → entregada, o abortada con un error en cualquiera de esos pasos sin producir bytes de PDF.
