<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\PuestoDTO;
use App\Modules\Estructura\Domain\Exceptions\PuestoNoEncontrado;
use App\Modules\Estructura\Domain\Repositories\PuestoRepositoryInterface;

final class ObtenerPuesto
{
    public function __construct(private readonly PuestoRepositoryInterface $puestos) {}

    /**
     * Cualquier persona autenticada puede consultar un puesto, también si está inactivo.
     *
     * @throws PuestoNoEncontrado
     */
    public function ejecutar(int $id): PuestoDTO
    {
        $puesto = $this->puestos->obtener($id) ?? throw new PuestoNoEncontrado;

        return PuestoDTO::desde($puesto);
    }
}
