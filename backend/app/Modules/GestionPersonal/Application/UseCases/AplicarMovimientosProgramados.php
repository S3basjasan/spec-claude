<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\Ports\ContextoDeTenantPort;
use App\Modules\GestionPersonal\Domain\Repositories\ActuacionRepositoryInterface;
use App\Modules\GestionPersonal\Domain\ValueObjects\EstadoDeActuacion;
use Throwable;

/**
 * Proceso controlado de aplicación automática: para cada tenant con movimientos aprobados, aplica los cuya fecha
 * efectiva ya llegó en la zona horaria del tenant, con identidad de sistema y la misma verificación que el reintento
 * manual. Es idempotente, no reintenta incidencias y no comparte contexto entre tenants ni entre movimientos.
 */
final class AplicarMovimientosProgramados
{
    private const LOTE = 200;

    public function __construct(
        private readonly ActuacionRepositoryInterface $actuaciones,
        private readonly ContextoDeTenantPort $contextos,
        private readonly AplicarMovimiento $aplicar,
    ) {}

    /** @return array{aplicados: int, incidencias: int, omitidos: int} */
    public function ejecutar(): array
    {
        $cuenta = ['aplicados' => 0, 'incidencias' => 0, 'omitidos' => 0];

        foreach ($this->actuaciones->tenantsConMovimientosPorAplicar() as $tenantId) {
            $contexto = $this->contextos->deTenant($tenantId);
            if ($contexto === null) {
                continue;
            }

            foreach ($this->actuaciones->movimientosPorAplicar($tenantId, $contexto->hoy(), self::LOTE) as $id) {
                try {
                    $resultado = $this->aplicar->comoSistema($contexto, $id);
                } catch (Throwable $error) {
                    report($error);
                    $cuenta['omitidos']++;

                    continue;
                }

                match (true) {
                    $resultado->actuacion->estado === EstadoDeActuacion::Aplicada && $resultado->nueva => $cuenta['aplicados']++,
                    $resultado->actuacion->estado === EstadoDeActuacion::IncidenciaDeAplicacion => $cuenta['incidencias']++,
                    default => $cuenta['omitidos']++,
                };
            }
        }

        return $cuenta;
    }
}
