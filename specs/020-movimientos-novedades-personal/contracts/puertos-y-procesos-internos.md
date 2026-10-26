# Contratos internos: puertos, fachadas consumidas y proceso programado

**Feature**: 020-movimientos-novedades-personal

## Consumido (sin cambios)

| Contrato | Uso |
|---|---|
| `PoliticasFacade::aplicablePara(tenantId, tipo, instante)` | mostrar las condiciones antes de presentar (sin reservar versión) |
| `PoliticasFacade::seleccionarParaPresentar(tenantId, tipo, instante)` | fijar versión y copia de condiciones al presentar, **dentro de la transacción de la actuación y tras el bloqueo de la ficha** |
| `PosicionesAsignablesPort::verificar / listar` | condición estructural del destino y búsqueda de destinos |
| `VinculoLaboralRepositoryInterface` (`vinculosDeFicha`, `asignacionesDePosicionDesde`, `ejecutarBajoBloqueo`) | vínculo vigente, ocupación y bloqueo por ficha |
| `AccesosDeCuentaPort::deCuenta(userId)` | ficha propia del Colaborador |
| `DocumentoRepositoryInterface::porId / listar` | existencia, estado y accesibilidad del respaldo |
| `TransaccionPort`, `RelojPort` | unidad de trabajo e instante único por operación |

## Nuevo o extendido

### `EvaluacionDelDestinoPort` (nuevo, `Application/Ports`)

```php
/**
 * @return array{estado: 'Valida'|'NoDisponible'|'NoVerificable', contexto: array<string, mixed>|null}
 */
public function consultar(int $tenantId, int $colaboradorId, int $posicionDestinoId, DateTimeImmutable $instante): array;
```

`contexto` (cuando existe) lleva: `colaborador_id`, `puesto_id`, `requisitos` (lista no vacía), `evaluada_en` (ISO 8601), `resultado` (texto), `vigente_hasta` (opcional). El adaptador registrado hoy es `EvaluacionDelDestinoNoInstalada` → `NoDisponible`. Cualquier excepción de un adaptador futuro se traduce a `NoVerificable`. `VerificadorDeEvaluacion` (dominio) exige identidad del colaborador y del puesto destino, requisitos, momento, resultado y que no esté vencida; no compara contra umbrales ni exige ausencia de brechas.

### `ContextoDeTenantPort::deTenant(int $tenantId): ?ContextoDeTenant` (extensión)

Resuelve nombre y zona horaria por tenant para el proceso programado, que no tiene cuenta. Lo implementa `SharedContextoDeTenant` leyendo el contrato público de Shared (`SharedFacade`).

### `ActuacionRepositoryInterface` (nuevo, `Domain/Repositories`)

Persistencia de actuaciones, detalles, documentos y transiciones. Sin método de eliminación. Operaciones: presentar (inserción atómica de común + detalle + documentos + transición), `porId(tenantId, id)`, `porOperacion`, listados y resumen con filtros, `cruces(tenantId, colaboradorId, inicio, fin, excluirId)`, `registrarTransicion` (cambio de estado con `version` optimista + inserción append-only), `movimientosPorAplicar(tenantId, fechaLimite)`, `registrarIncidencia`, `aplicarMovimiento` (cierre y apertura de asignaciones en la misma transacción). Todo método recibe `tenantId` y lo filtra.

### Historial Laboral

`TipoDeHecho` amplía con `Ascenso` y `Traslado`. `ConsultarTrayectoria` y `EloquentLectorLaboral::trayectoria` agregan un hecho por cada asignación con `actuacion_id` (fecha efectiva = `fecha_inicio`, referencia `actuacion_id` y `politica_numero`), sin alterar los hechos de ingreso ni reingreso.

## Proceso programado

`php artisan movimientos:aplicar-programados` — programado en `routes/console.php` cada cinco minutos con `withoutOverlapping()`. Para cada tenant con movimientos `Aprobada`/`Programada` cuya fecha efectiva ya llegó (zona horaria del tenant, vía `deTenant`), invoca `AplicarMovimiento` con identidad de sistema. Es idempotente (estado bajo bloqueo + índice único `asignaciones_una_por_actuacion`), no reintenta incidencias y no comparte contexto entre tenants. Salida: conteos de aplicados e incidencias; nunca imprime datos personales.
