<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\DTO\AreaDTO;
use App\Modules\Desarrollo\Domain\Repositories\CatalogoFormativoRepositoryInterface;

final class ListarAreas
{
    public function __construct(private readonly CatalogoFormativoRepositoryInterface $repositorio) {}

    /**
     * @return array<int, AreaDTO>
     */
    public function ejecutar(): array
    {
        return array_map(fn (array $a) => new AreaDTO($a['id'], $a['nombre']), $this->repositorio->areas());
    }
}
