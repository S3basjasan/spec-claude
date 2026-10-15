<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\UnidadDetalleDTO;
use App\Modules\Estructura\Application\Ports\NombresDeUsuariosInterface;
use App\Modules\Estructura\Domain\Exceptions\UnidadInvalida;
use App\Modules\Estructura\Domain\Repositories\UnidadRepositoryInterface;
use App\Modules\Estructura\Domain\ValueObjects\DatosUnidad;

final class CrearUnidad
{
    public function __construct(
        private readonly UnidadRepositoryInterface $unidades,
        private readonly NombresDeUsuariosInterface $usuarios,
    ) {}

    /**
     * Crea la unidad `Activa` bajo una unidad padre, con el tipo del nivel siguiente y la fecha del día. Operación de
     * administrador: devuelve el detalle con auditoría.
     *
     * @throws UnidadInvalida
     */
    public function ejecutar(DatosUnidad $datos, ?int $actorId = null): UnidadDetalleDTO
    {
        $creada = $this->unidades->crear(new DatosUnidad(trim($datos->nombre), $datos->unidadPadreId), $actorId);

        return UnidadDetalleDTO::paraAdministrador($creada, $this->usuarios);
    }
}
