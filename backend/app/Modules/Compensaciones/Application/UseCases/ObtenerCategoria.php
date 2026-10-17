<?php

namespace App\Modules\Compensaciones\Application\UseCases;

use App\Modules\Compensaciones\Application\DTOs\CategoriaDTO;
use App\Modules\Compensaciones\Application\Services\ArmadorDeCategoriaDTO;
use App\Modules\Compensaciones\Domain\Exceptions\CategoriaNoEncontrada;
use App\Modules\Compensaciones\Domain\Repositories\CategoriaRepository;

final class ObtenerCategoria
{
    public function __construct(
        private readonly CategoriaRepository $categorias,
        private readonly ArmadorDeCategoriaDTO $armador,
    ) {}

    /**
     * Devuelve también las categorías inactivas: los registros históricos las referencian.
     *
     * @throws CategoriaNoEncontrada
     */
    public function ejecutar(int $id): CategoriaDTO
    {
        $categoria = $this->categorias->porId($id) ?? throw new CategoriaNoEncontrada;

        return $this->armador->uno($categoria);
    }
}
