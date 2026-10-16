<?php

namespace App\Modules\GestionPersonal\Application\Services;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\Ports\AccesosDeCuentaPort;
use App\Modules\GestionPersonal\Domain\Exceptions\ColaboradorNoEncontrado;
use App\Modules\GestionPersonal\Domain\Exceptions\CuentaSinFicha;
use App\Modules\GestionPersonal\Domain\Exceptions\SinPermisoDeExportacion;
use App\Modules\GestionPersonal\Domain\Repositories\ColaboradorRepositoryInterface;

/**
 * Ficha propia exportable: asociación válida en el mismo tenant y permiso de exportación. No exige el permiso de
 * consulta de ficha ni el documental (son independientes). Cada llamada lee la asociación y el permiso de nuevo.
 */
final class AutorizadorDeExportacion
{
    public function __construct(
        private readonly AccesosDeCuentaPort $accesos,
        private readonly ColaboradorRepositoryInterface $colaboradores,
    ) {}

    /**
     * @throws CuentaSinFicha
     * @throws SinPermisoDeExportacion
     */
    public function fichaPropia(ContextoDeTenant $contexto, int $userId): int
    {
        $acceso = $this->accesos->deCuenta($userId);

        if ($acceso === null || $acceso->tenantId !== $contexto->tenantId) {
            throw new CuentaSinFicha;
        }
        if (! $acceso->puedeExportar) {
            throw new SinPermisoDeExportacion;
        }

        $this->colaboradores->porId($contexto->tenantId, $acceso->colaboradorId) ?? throw new CuentaSinFicha;

        return $acceso->colaboradorId;
    }

    /**
     * Administrador: la ficha debe seguir existiendo en el tenant (la capacidad administrativa la fija el middleware).
     *
     * @throws ColaboradorNoEncontrado
     */
    public function fichaDelTenant(ContextoDeTenant $contexto, int $colaboradorId): void
    {
        $this->colaboradores->porId($contexto->tenantId, $colaboradorId) ?? throw new ColaboradorNoEncontrado;
    }
}
