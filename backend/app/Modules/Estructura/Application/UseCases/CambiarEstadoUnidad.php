<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\UnidadDetalleDTO;
use App\Modules\Estructura\Application\Ports\NombresDeUsuariosInterface;
use App\Modules\Estructura\Domain\Exceptions\DesactivacionBloqueada;
use App\Modules\Estructura\Domain\Exceptions\UnidadInvalida;
use App\Modules\Estructura\Domain\Exceptions\UnidadNoEncontrada;
use App\Modules\Estructura\Domain\Exceptions\UnidadNoReactivable;
use App\Modules\Estructura\Domain\Repositories\UnidadRepositoryInterface;
use App\Modules\Estructura\Domain\ValueObjects\EstadoUnidad;

final class CambiarEstadoUnidad
{
    public function __construct(
        private readonly UnidadRepositoryInterface $unidades,
        private readonly NombresDeUsuariosInterface $usuarios,
    ) {}

    /**
     * Desactiva en cascada (la unidad y sus dependientes activas; se rechaza la raíz o una rama con posiciones Ocupadas
     * o Vacantes) o reactiva una sola unidad (con padre activa). Si ya está en ese estado devuelve la unidad sin cambios.
     * Las unidades nunca se eliminan.
     *
     * @throws UnidadNoEncontrada
     * @throws UnidadInvalida
     * @throws DesactivacionBloqueada
     * @throws UnidadNoReactivable
     */
    public function ejecutar(int $id, EstadoUnidad $estado, ?int $actorId = null): UnidadDetalleDTO
    {
        return UnidadDetalleDTO::paraAdministrador($this->unidades->cambiarEstado($id, $estado, $actorId), $this->usuarios);
    }
}
