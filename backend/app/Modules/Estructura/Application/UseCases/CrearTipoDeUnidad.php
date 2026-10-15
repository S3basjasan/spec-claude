<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\CadenaDeTiposDTO;
use App\Modules\Estructura\Domain\Exceptions\CadenaInvalida;
use App\Modules\Estructura\Domain\Repositories\TipoUnidadRepositoryInterface;

final class CrearTipoDeUnidad
{
    public function __construct(private readonly TipoUnidadRepositoryInterface $tipos) {}

    /**
     * Agrega un tipo al final de la cadena (nivel = último + 1).
     *
     * @return array{id: int, nombre: string, nivel: int, es_raiz: bool, cantidad_unidades: int}
     *
     * @throws CadenaInvalida
     */
    public function ejecutar(string $nombre, ?int $actorId = null): array
    {
        return CadenaDeTiposDTO::tipo($this->tipos->crear($nombre, $actorId));
    }
}
