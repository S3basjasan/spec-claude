<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\Ports\AccesosDeCuentaPort;
use App\Modules\GestionPersonal\Domain\Exceptions\CuentaSinFicha;
use App\Modules\GestionPersonal\Domain\Exceptions\SinPermisoDeDocumentos;
use App\Modules\GestionPersonal\Domain\Repositories\ColaboradorRepositoryInterface;

/**
 * Ficha propia para consultar documentos: asociación válida en el mismo tenant y permiso documental.
 * No exige el permiso de consulta de ficha ni el de exportación (son independientes).
 */
final class ResolverFichaDocumentalPropia
{
    public function __construct(
        private readonly AccesosDeCuentaPort $accesos,
        private readonly ColaboradorRepositoryInterface $colaboradores,
    ) {}

    /**
     * @throws CuentaSinFicha
     * @throws SinPermisoDeDocumentos
     */
    public function ejecutar(ContextoDeTenant $contexto, int $userId): int
    {
        $acceso = $this->accesos->deCuenta($userId);

        if ($acceso === null || $acceso->tenantId !== $contexto->tenantId) {
            throw new CuentaSinFicha;
        }
        if (! $acceso->puedeConsultarDocumentos) {
            throw new SinPermisoDeDocumentos;
        }

        $this->colaboradores->porId($contexto->tenantId, $acceso->colaboradorId) ?? throw new CuentaSinFicha;

        return $acceso->colaboradorId;
    }
}
