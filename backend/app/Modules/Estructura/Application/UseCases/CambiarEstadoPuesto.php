<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\PuestoDTO;
use App\Modules\Estructura\Domain\Exceptions\PuestoConPosicionesEnUso;
use App\Modules\Estructura\Domain\Exceptions\PuestoNoEncontrado;
use App\Modules\Estructura\Domain\Repositories\PuestoRepositoryInterface;
use App\Modules\Estructura\Domain\ValueObjects\EstadoPuesto;

final class CambiarEstadoPuesto
{
    public function __construct(private readonly PuestoRepositoryInterface $puestos) {}

    /**
     * Activa o desactiva un puesto. Activar no tiene restricciones; desactivar solo procede si el puesto no
     * tiene posiciones Ocupadas ni Vacantes. Si ya está en ese estado no cambia nada.
     *
     * @param  string  $estado  `Activo` o `Inactivo`.
     *
     * @throws PuestoNoEncontrado
     * @throws PuestoConPosicionesEnUso
     */
    public function ejecutar(int $id, string $estado, ?int $actorId = null): PuestoDTO
    {
        return PuestoDTO::desde($this->puestos->cambiarEstado($id, EstadoPuesto::from($estado), $actorId));
    }
}
