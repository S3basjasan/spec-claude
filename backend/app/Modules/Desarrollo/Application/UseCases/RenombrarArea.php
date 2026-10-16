<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\DTO\AreaDTO;
use App\Modules\Desarrollo\Domain\Exceptions\AreaNoEncontrada;
use App\Modules\Desarrollo\Domain\Exceptions\NombreDeAreaDuplicado;
use App\Modules\Desarrollo\Domain\Repositories\CatalogoFormativoRepositoryInterface;

final class RenombrarArea
{
    public function __construct(private readonly CatalogoFormativoRepositoryInterface $repositorio) {}

    /**
     * @throws AreaNoEncontrada
     * @throws NombreDeAreaDuplicado
     */
    public function ejecutar(int $areaId, string $nombre, ?int $actorId): AreaDTO
    {
        $area = $this->repositorio->renombrarArea($areaId, $nombre, $actorId);

        return new AreaDTO($area['id'], $area['nombre']);
    }
}
