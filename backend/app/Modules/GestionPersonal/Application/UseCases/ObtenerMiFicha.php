<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\DTO\FichaDetalleDTO;
use App\Modules\GestionPersonal\Application\Ports\AccesosDeCuentaPort;
use App\Modules\GestionPersonal\Application\Services\ArmadorDeFichaDetalle;
use App\Modules\GestionPersonal\Domain\Exceptions\CuentaSinFicha;
use App\Modules\GestionPersonal\Domain\Exceptions\SinPermisoDeFicha;
use App\Modules\GestionPersonal\Domain\Repositories\ColaboradorRepositoryInterface;

final class ObtenerMiFicha
{
    public function __construct(
        private readonly ColaboradorRepositoryInterface $colaboradores,
        private readonly AccesosDeCuentaPort $accesos,
        private readonly ArmadorDeFichaDetalle $armador,
    ) {}

    /**
     * @throws CuentaSinFicha
     * @throws SinPermisoDeFicha
     */
    public function ejecutar(ContextoDeTenant $contexto, int $userId): FichaDetalleDTO
    {
        $acceso = $this->accesos->deCuenta($userId);

        if ($acceso === null || $acceso->tenantId !== $contexto->tenantId) {
            throw new CuentaSinFicha;
        }

        if (! $acceso->puedeConsultarFicha) {
            throw new SinPermisoDeFicha;
        }

        $colaborador = $this->colaboradores->porId($contexto->tenantId, $acceso->colaboradorId) ?? throw new CuentaSinFicha;

        return new FichaDetalleDTO($this->armador->armar($colaborador, $contexto, true, $acceso));
    }
}
