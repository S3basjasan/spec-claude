<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\PosicionDetalleDTO;
use App\Modules\Estructura\Application\Ports\NombresDeUsuariosInterface;
use App\Modules\Estructura\Domain\Exceptions\PosicionNoEncontrada;
use App\Modules\Estructura\Domain\Exceptions\PosicionNoSuprimible;
use App\Modules\Estructura\Domain\Repositories\PosicionRepositoryInterface;

final class SuprimirPosicion
{
    public function __construct(
        private readonly PosicionRepositoryInterface $posiciones,
        private readonly NombresDeUsuariosInterface $usuarios,
    ) {}

    /**
     * Suprime la posición (definitivo) solo si está Vacante y no tiene subordinadas vigentes.
     *
     * @throws PosicionNoEncontrada
     * @throws PosicionNoSuprimible
     */
    public function ejecutar(int $id, ?int $actorId = null): PosicionDetalleDTO
    {
        return PosicionDetalleDTO::para($this->posiciones->suprimir($id, $actorId), $this->usuarios, true);
    }
}
