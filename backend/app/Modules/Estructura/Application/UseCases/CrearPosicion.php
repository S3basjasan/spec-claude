<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\PosicionDetalleDTO;
use App\Modules\Estructura\Application\Ports\NombresDeUsuariosInterface;
use App\Modules\Estructura\Domain\Exceptions\PosicionInvalida;
use App\Modules\Estructura\Domain\Repositories\PosicionRepositoryInterface;
use App\Modules\Estructura\Domain\ValueObjects\DatosPosicion;

final class CrearPosicion
{
    public function __construct(
        private readonly PosicionRepositoryInterface $posiciones,
        private readonly NombresDeUsuariosInterface $usuarios,
    ) {}

    /**
     * Crea la posición `Vacante`, sin ocupante, con código `POS-####`. Operación de administrador: devuelve el detalle
     * con auditoría.
     *
     * @throws PosicionInvalida
     */
    public function ejecutar(DatosPosicion $datos, ?int $actorId = null): PosicionDetalleDTO
    {
        return PosicionDetalleDTO::para($this->posiciones->crear($datos, $actorId), $this->usuarios, true);
    }
}
