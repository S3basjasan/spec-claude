<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\PosicionDetalleDTO;
use App\Modules\Estructura\Application\Ports\NombresDeUsuariosInterface;
use App\Modules\Estructura\Domain\Exceptions\PosicionInvalida;
use App\Modules\Estructura\Domain\Exceptions\PosicionModificada;
use App\Modules\Estructura\Domain\Exceptions\PosicionNoEncontrada;
use App\Modules\Estructura\Domain\Repositories\PosicionRepositoryInterface;
use App\Modules\Estructura\Domain\ValueObjects\DatosPosicion;

final class ActualizarPosicion
{
    public function __construct(
        private readonly PosicionRepositoryInterface $posiciones,
        private readonly NombresDeUsuariosInterface $usuarios,
    ) {}

    /**
     * Reemplaza unidad, supervisor y modalidad. El puesto, el código, el ocupante y el estado no cambian. Sin cambios
     * reales la posición queda igual (misma versión e historial).
     *
     * @throws PosicionNoEncontrada
     * @throws PosicionModificada
     * @throws PosicionInvalida
     */
    public function ejecutar(int $id, int $versionEsperada, DatosPosicion $datos, ?int $actorId = null): PosicionDetalleDTO
    {
        return PosicionDetalleDTO::para(
            $this->posiciones->actualizar($id, $versionEsperada, $datos, $actorId),
            $this->usuarios,
            true,
        );
    }
}
