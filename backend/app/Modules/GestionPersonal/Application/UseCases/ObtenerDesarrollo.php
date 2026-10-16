<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\DTO\DesarrolloDTO;
use App\Modules\GestionPersonal\Application\Services\ArmadorDeDesarrollo;
use App\Modules\GestionPersonal\Domain\Exceptions\ColaboradorNoEncontrado;
use App\Modules\GestionPersonal\Domain\Repositories\ColaboradorRepositoryInterface;

final class ObtenerDesarrollo
{
    public function __construct(
        private readonly ColaboradorRepositoryInterface $colaboradores,
        private readonly ArmadorDeDesarrollo $armador,
    ) {}

    /** @throws ColaboradorNoEncontrado Inexistente o de otro tenant: la misma respuesta. */
    public function ejecutar(ContextoDeTenant $contexto, int $colaboradorId): DesarrolloDTO
    {
        $this->colaboradores->porId($contexto->tenantId, $colaboradorId) ?? throw new ColaboradorNoEncontrado;

        return $this->armador->armar($colaboradorId, $contexto, true);
    }
}
