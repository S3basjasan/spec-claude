<?php

namespace App\Modules\GestionPersonal\Application\Services;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\DTO\Solicitante;
use App\Modules\GestionPersonal\Application\Ports\AccesosDeCuentaPort;
use App\Modules\GestionPersonal\Domain\Exceptions\ColaboradorNoEncontrado;
use App\Modules\GestionPersonal\Domain\Exceptions\CuentaSinFicha;
use App\Modules\GestionPersonal\Domain\Repositories\ColaboradorRepositoryInterface;

/**
 * Resuelve la identidad operativa a partir de la cuenta autenticada: el tenant nunca viene del cliente. El
 * Colaborador solo opera sobre su ficha asociada; el Administrador sobre cualquier ficha de su tenant.
 */
final class ResolvedorDeSolicitante
{
    public function __construct(
        private readonly AccesosDeCuentaPort $accesos,
        private readonly ColaboradorRepositoryInterface $colaboradores,
    ) {}

    /**
     * @throws ColaboradorNoEncontrado La ficha no existe en el tenant.
     */
    public function administrador(ContextoDeTenant $contexto, int $userId, int $colaboradorId): Solicitante
    {
        $this->colaboradores->porId($contexto->tenantId, $colaboradorId) ?? throw new ColaboradorNoEncontrado;

        $propia = $this->accesos->deCuenta($userId);
        $esLaMisma = $propia !== null && $propia->tenantId === $contexto->tenantId && $propia->colaboradorId === $colaboradorId;

        return new Solicitante($userId, $colaboradorId, false, ! $esLaMisma, true);
    }

    /**
     * @throws CuentaSinFicha
     */
    public function colaborador(ContextoDeTenant $contexto, int $userId): Solicitante
    {
        $acceso = $this->accesos->deCuenta($userId);

        if ($acceso === null || $acceso->tenantId !== $contexto->tenantId) {
            throw new CuentaSinFicha;
        }
        $this->colaboradores->porId($contexto->tenantId, $acceso->colaboradorId) ?? throw new CuentaSinFicha;

        return new Solicitante($userId, $acceso->colaboradorId, true, false, $acceso->puedeConsultarDocumentos);
    }
}
