<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\DTO\AreaDTO;
use App\Modules\Desarrollo\Domain\Exceptions\NombreDeAreaDuplicado;
use App\Modules\Desarrollo\Domain\Repositories\CatalogoFormativoRepositoryInterface;

final class CrearArea
{
    public function __construct(private readonly CatalogoFormativoRepositoryInterface $repositorio) {}

    /**
     * @throws NombreDeAreaDuplicado
     */
    public function ejecutar(string $nombre, ?int $actorId): AreaDTO
    {
        $area = $this->repositorio->crearArea($nombre, $actorId);

        return new AreaDTO($area['id'], $area['nombre']);
    }
}
