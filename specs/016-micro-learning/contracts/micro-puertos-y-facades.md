# Puertos y Facades de la feature 016

## Facade propia: `DesarrolloFacade` (ampliación aditiva)

Presentación pública del módulo `Desarrollo`. Solo lectura; ningún módulo consumidor puede escribir progreso ni contenidos.

| Método | Consumidor | Contrato |
|---|---|---|
| `getMicrocontenidosPublicadosPorCompetencia(int $competenciaId): array` | Catálogo Formativo (005) y otros | `[{id, codigo, titulo, tipo, duracion_minutos, miniatura_url, url_destino}]` de micro-contenidos **Publicados** vinculados a la competencia; `url_destino = '/micro-learning/{id}'`. |
| `getResumenAprendizaje(int $tenantId, int $colaboradorId): array` | Reportes (6): «Mi progreso» | `{completados, en_progreso, horas_acumuladas, certificados}`; horas = suma de `duracion_minutos` de los completados ÷ 60, con un decimal. |
| `getCompletaciones(int $tenantId, array $filtros = []): array` | Reportes (6): indicadores | Filtros `colaborador_id`, `microcontenido_id`, `desde`, `hasta` (fecha de completado). Devuelve `[{microcontenido_id, codigo, titulo, tipo, duracion_minutos, colaborador_id, fecha_inicio, fecha_completado}]`. |

`getOfertaPorCompetencia` y `competenciaTieneOferta` (feature 005) siguen vigentes: pasan a contar los micro-contenidos
**Publicados**.

## Puertos nuevos de `Desarrollo` (Application/Ports)

| Puerto | Responsabilidad | Adaptador | Estado |
|---|---|---|---|
| `NombreDeColaboradorInterface` | `nombreDe(tenantId, colaboradorId): ?string` (nombres y apellidos para el certificado) | `NombreDeColaboradorDesdePersonal` → `PersonalFacade::getColaborador` | Definitivo |
| `CertificadoPdfInterface` | `renderizar(CertificadoDTO): string` (bytes del PDF) | `CertificadoPdfDompdf` (dompdf, sin PHP ni recursos remotos, datos escapados) | Definitivo |

Puertos ya existentes que se reutilizan sin cambios de firma: `CompetenciasInterface` (activas, por ids; vía `EstructuraFacade`),
`BrechasActivasInterface::brechasDe(usuarioId)` (alimentado por la DNF), `CuentaColaboradorInterface::cuentaDe(usuarioId)`
(`{tenant_id, colaborador_id}` o `null`) y `NombresDeUsuarioInterface` (nombres para la auditoría).

## Repositorios (Domain/Repositories)

- `MicrocontenidoRepositoryInterface`: alta y edición con preguntas, vínculos y código; listado filtrado; historial de estados;
  borrado explícito; conteo de completaciones; `tieneProgreso`, `tieneIntentosFinalizados`.
- `ProgresoMicroRepositoryInterface`: progreso por contenido y colaborador, intentos, respuestas, resumen y completaciones.

## Ajustes al módulo del Catálogo (mismo módulo `Desarrollo`)

- `EloquentCatalogoFormativoRepository`: lee `titulo` en lugar de `nombre` para micro-contenidos, considera *disponible* solo
  `Publicado`, entrega `formador = null` y `url_destino = '/micro-learning/{id}'`.
- Nuevo método `sincronizarCompetenciasDeMicrocontenido(int $microId, array $competenciaIds, ?int $actorId): void`: escribe los
  vínculos, registra `tipo_cambio = Microcontenido` (`Asociado`/`Quitado`) en `competencia_formativa_historial` y sube `version` de
  cada `competencia_formativa` afectada, todo dentro de la transacción del llamador.

## Cambios en otros módulos

Ninguno: no se modifican Estructura, Gestión de Personal, DNF ni Shared.
