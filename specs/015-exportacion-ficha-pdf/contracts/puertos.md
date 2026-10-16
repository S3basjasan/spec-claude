# Contrato: puertos internos de la exportación

Ningún controlador ni caso de uso toca dompdf, `DB` ni plantillas; todo pasa por estos puertos de `GP/Application/Ports` con adaptadores en `GP/Infrastructure`.

## `ContextoDeLecturaPort`

| Método | Comportamiento |
|---|---|
| `enSnapshot(callable $lectura): mixed` | Ejecuta `$lectura` dentro de una transacción de lectura sobre la conexión por defecto, la cierra al terminar (también si lanza) y devuelve su resultado. En motores que lo admiten fija `REPEATABLE READ READ ONLY`; en SQLite usa una transacción de lectura (vista estable) |

Todos los lectores usados por el armador (ficha, vínculos, desarrollo, Estructura) leen la misma conexión; una prueba lo verifica con el registro de consultas.

## `RenderizadorDePdfPort`

| Método | Comportamiento |
|---|---|
| `renderizar(SnapshotDeFichaDTO $snapshot): string` | Devuelve el PDF completo en memoria; lanza `ExportacionNoDisponible` si falla, si el resultado está vacío, no empieza por `%PDF-` o no termina en `%%EOF` |

Reglas del adaptador dompdf (propuesto): sin recursos remotos, sin PHP ni JavaScript, raíz de archivos limitada a la carpeta de plantillas del módulo, fuente local con caracteres españoles, datos escapados como texto, sin imágenes. Un renderizador propio (contingencia) implementa el mismo contrato.

## `AutorizadorDeExportacion` (servicio de aplicación)

| Método | Comportamiento |
|---|---|
| `autorizarAdministrador(actorId, colaboradorId): ContextoDeTenant` | Contexto del tenant de la cuenta y ficha del tenant (`ColaboradorNoEncontrado` uniforme) |
| `autorizarPropia(userId): {contexto, colaboradorId}` | `AccesosDeCuentaPort`: asociación válida en el tenant (`CuentaSinFicha`) y `puedeExportar` (`SinPermisoDeExportacion`); no exige `puedeConsultarFicha` ni `puedeConsultarDocumentos` |
| `revalidar…` | Repite lo anterior con lecturas nuevas, fuera de la transacción del snapshot, justo antes de responder |

## `ArmadorDeSnapshotDeFicha`

Compone `SnapshotDeFichaDTO` solo con lecturas exitosas: `ColaboradorRepositoryInterface::porId`, `ConsultarSituacionLaboral`, `ConsultarTrayectoria` (con disponibilidad), `DesarrolloRepositoryInterface` (formaciones, experiencias, movilidad) y las etiquetas de `EtiquetasDeExportacion`. Cualquier fallo ⇒ `ExportacionNoDisponible`. No consulta documentos ni tablas ajenas.

## Efectos

Ninguno: no escribe en base de datos, no crea archivos, no agrega el PDF al expediente documental y no expone métodos nuevos en `PersonalFacade`.
