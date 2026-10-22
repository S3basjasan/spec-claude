<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Domain\Exceptions\MicroConProgreso;
use App\Modules\Desarrollo\Domain\Exceptions\MicroNoEncontrado;
use App\Modules\Desarrollo\Domain\Repositories\MicrocontenidoRepositoryInterface;

/** Elimina un micro-contenido solo si ningún colaborador lo ha iniciado. */
final class EliminarMicrocontenido
{
    public function __construct(private readonly MicrocontenidoRepositoryInterface $repositorio) {}

    public function ejecutar(int $id, int $actorId): void
    {
        $micro = $this->repositorio->obtener($id) ?? throw new MicroNoEncontrado;

        if ($micro->tieneProgreso) {
            throw new MicroConProgreso;
        }

        $this->repositorio->eliminar($id, $actorId);
    }
}
