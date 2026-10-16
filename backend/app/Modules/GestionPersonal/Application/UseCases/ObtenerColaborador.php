<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\DTO\FichaDetalleDTO;
use App\Modules\GestionPersonal\Application\Services\ArmadorDeFichaDetalle;
use App\Modules\GestionPersonal\Domain\Exceptions\ColaboradorNoEncontrado;
use App\Modules\GestionPersonal\Domain\Repositories\ColaboradorRepositoryInterface;

final class ObtenerColaborador
{
    public function __construct(
        private readonly ColaboradorRepositoryInterface $colaboradores,
        private readonly ArmadorDeFichaDetalle $armador,
    ) {}

    /** @throws ColaboradorNoEncontrado Inexistente o de otro tenant: la misma respuesta. */
    public function ejecutar(ContextoDeTenant $contexto, int $id): FichaDetalleDTO
    {
        $colaborador = $this->colaboradores->porId($contexto->tenantId, $id) ?? throw new ColaboradorNoEncontrado;

        return new FichaDetalleDTO($this->armador->armar($colaborador, $contexto));
    }
}
