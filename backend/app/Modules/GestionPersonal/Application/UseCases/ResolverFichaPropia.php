<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\Ports\AccesosDeCuentaPort;
use App\Modules\GestionPersonal\Domain\Exceptions\CuentaSinFicha;
use App\Modules\GestionPersonal\Domain\Exceptions\SinPermisoDeFicha;

/**
 * Resuelve la ficha propia de una cuenta: asociación válida en el mismo tenant y permiso de consulta de ficha.
 */
final class ResolverFichaPropia
{
    public function __construct(private readonly AccesosDeCuentaPort $accesos) {}

    /**
     * @throws CuentaSinFicha
     * @throws SinPermisoDeFicha
     */
    public function ejecutar(ContextoDeTenant $contexto, int $userId): int
    {
        $acceso = $this->accesos->deCuenta($userId);

        if ($acceso === null || $acceso->tenantId !== $contexto->tenantId) {
            throw new CuentaSinFicha;
        }
        if (! $acceso->puedeConsultarFicha) {
            throw new SinPermisoDeFicha;
        }

        return $acceso->colaboradorId;
    }
}
