<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\PuestoDTO;
use App\Modules\Estructura\Application\Ports\OcupacionDePosicionesPort;
use App\Modules\Estructura\Domain\Exceptions\PuestoConPosicionesEnUso;
use App\Modules\Estructura\Domain\Exceptions\PuestoNoEncontrado;
use App\Modules\Estructura\Domain\Repositories\PosicionRepositoryInterface;
use App\Modules\Estructura\Domain\Repositories\PuestoRepositoryInterface;
use App\Modules\Estructura\Domain\ValueObjects\EstadoPuesto;

final class CambiarEstadoPuesto
{
    public function __construct(
        private readonly PuestoRepositoryInterface $puestos,
        private readonly ?PosicionRepositoryInterface $posiciones = null,
        private readonly ?OcupacionDePosicionesPort $ocupacion = null,
    ) {}

    /**
     * Activa o desactiva un puesto. Activar no tiene restricciones; desactivar solo procede si el puesto no
     * tiene posiciones Ocupadas ni Vacantes ni posiciones con una asignación laboral vigente o futura (GP-02). Si ya
     * está en ese estado no cambia nada.
     *
     * @param  string  $estado  `Activo` o `Inactivo`.
     *
     * @throws PuestoNoEncontrado
     * @throws PuestoConPosicionesEnUso
     */
    public function ejecutar(int $id, string $estado, ?int $actorId = null): PuestoDTO
    {
        $nuevo = EstadoPuesto::from($estado);

        if ($nuevo === EstadoPuesto::Inactivo) {
            $this->rechazarSiHayAsignaciones($id);
        }

        return PuestoDTO::desde($this->puestos->cambiarEstado($id, $nuevo, $actorId));
    }

    /**
     * Una posición Suprimida con una asignación vigente o futura sigue ocupada: no puede desactivarse su puesto.
     * (Las no suprimidas ya bloquean por su estado guardado.)
     *
     * @throws PuestoConPosicionesEnUso
     */
    private function rechazarSiHayAsignaciones(int $id): void
    {
        if ($this->posiciones === null || $this->ocupacion === null || ! ($this->puestos->obtener($id)?->activo() ?? false)) {
            return;
        }

        $ocupadas = $this->ocupacion->conAsignacionVigenteOFutura(
            $this->posiciones->suprimidasDelPuesto($id),
            now()->toDateString(),
        );

        if ($ocupadas !== []) {
            throw new PuestoConPosicionesEnUso($this->posiciones->contarEnUsoDelPuesto($id) + count($ocupadas), true);
        }
    }
}
