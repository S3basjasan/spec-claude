<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\Ports\ContextoDeTenantPort;
use App\Modules\GestionPersonal\Domain\Repositories\ExpedienteRepositoryInterface;
use App\Modules\GestionPersonal\Domain\ValueObjects\EstadoDeSalida;
use Throwable;

/**
 * Proceso controlado de aplicación de salidas: para cada tenant con salidas programadas, aplica las cuya fecha efectiva
 * ya llegó en la zona horaria del tenant, con identidad de sistema y la misma verificación que el reintento manual.
 * Es idempotente, no reintenta incidencias y no comparte contexto entre tenants ni entre expedientes.
 */
final class AplicarSalidasProgramadas
{
    private const LOTE = 200;

    public function __construct(
        private readonly ExpedienteRepositoryInterface $expedientes,
        private readonly ContextoDeTenantPort $contextos,
        private readonly AplicarSalida $aplicar,
    ) {}

    /** @return array{aplicadas: int, incidencias: int, omitidas: int} */
    public function ejecutar(): array
    {
        $cuenta = ['aplicadas' => 0, 'incidencias' => 0, 'omitidas' => 0];

        foreach ($this->expedientes->tenantsConSalidasPorAplicar() as $tenantId) {
            $contexto = $this->contextos->deTenant($tenantId);
            if ($contexto === null) {
                continue;
            }

            foreach ($this->expedientes->salidasPorAplicar($tenantId, $contexto->hoy(), self::LOTE) as $id) {
                try {
                    $resultado = $this->aplicar->comoSistema($contexto, $id);
                } catch (Throwable $error) {
                    report($error);
                    $cuenta['omitidas']++;

                    continue;
                }

                match (true) {
                    $resultado->expediente->estado === EstadoDeSalida::Efectiva && $resultado->nueva => $cuenta['aplicadas']++,
                    $resultado->expediente->estado === EstadoDeSalida::IncidenciaDeAplicacion => $cuenta['incidencias']++,
                    default => $cuenta['omitidas']++,
                };
            }
        }

        return $cuenta;
    }
}
