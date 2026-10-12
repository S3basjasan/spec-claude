<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\RegistroComportamientosDTO;
use App\Modules\Estructura\Domain\Exceptions\RegistroNoEncontrado;
use App\Modules\Estructura\Domain\Repositories\ComportamientoRepositoryInterface;

final class ObtenerComportamientos
{
    public function __construct(private readonly ComportamientoRepositoryInterface $registros) {}

    /** @throws RegistroNoEncontrado */
    public function ejecutar(int $competenciaId): RegistroComportamientosDTO
    {
        $registro = $this->registros->obtener($competenciaId) ?? throw new RegistroNoEncontrado;

        return RegistroComportamientosDTO::desde($registro);
    }
}
