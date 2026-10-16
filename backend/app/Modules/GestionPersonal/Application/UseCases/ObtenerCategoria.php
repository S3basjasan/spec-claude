<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Domain\Entities\CategoriaDocumental;
use App\Modules\GestionPersonal\Domain\Exceptions\CategoriaNoEncontrada;
use App\Modules\GestionPersonal\Domain\Repositories\CategoriaRepositoryInterface;

final class ObtenerCategoria
{
    public function __construct(private readonly CategoriaRepositoryInterface $categorias) {}

    /** @throws CategoriaNoEncontrada Inexistente o de otro tenant: la misma respuesta. */
    public function ejecutar(ContextoDeTenant $contexto, int $id): CategoriaDocumental
    {
        return $this->categorias->porId($contexto->tenantId, $id) ?? throw new CategoriaNoEncontrada;
    }
}
