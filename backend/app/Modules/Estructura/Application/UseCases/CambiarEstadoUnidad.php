<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\UnidadDetalleDTO;
use App\Modules\Estructura\Application\Ports\NombresDeUsuariosInterface;
use App\Modules\Estructura\Application\Ports\OcupacionDePosicionesPort;
use App\Modules\Estructura\Domain\Exceptions\DesactivacionBloqueada;
use App\Modules\Estructura\Domain\Exceptions\UnidadInvalida;
use App\Modules\Estructura\Domain\Exceptions\UnidadNoEncontrada;
use App\Modules\Estructura\Domain\Exceptions\UnidadNoReactivable;
use App\Modules\Estructura\Domain\Repositories\PosicionRepositoryInterface;
use App\Modules\Estructura\Domain\Repositories\UnidadRepositoryInterface;
use App\Modules\Estructura\Domain\ValueObjects\EstadoUnidad;

final class CambiarEstadoUnidad
{
    public function __construct(
        private readonly UnidadRepositoryInterface $unidades,
        private readonly NombresDeUsuariosInterface $usuarios,
        private readonly ?PosicionRepositoryInterface $posiciones = null,
        private readonly ?OcupacionDePosicionesPort $ocupacion = null,
    ) {}

    /**
     * Desactiva en cascada (la unidad y sus dependientes activas; se rechaza la raíz o una rama con posiciones Ocupadas
     * o Vacantes, o con asignaciones laborales vigentes o futuras en posiciones Suprimidas) o reactiva una sola unidad
     * (con padre activa). Si ya está en ese estado devuelve la unidad sin cambios. Las unidades nunca se eliminan.
     *
     * @throws UnidadNoEncontrada
     * @throws UnidadInvalida
     * @throws DesactivacionBloqueada
     * @throws UnidadNoReactivable
     */
    public function ejecutar(int $id, EstadoUnidad $estado, ?int $actorId = null): UnidadDetalleDTO
    {
        if ($estado === EstadoUnidad::Inactiva) {
            $this->rechazarSiHayAsignaciones($id);
        }

        return UnidadDetalleDTO::paraAdministrador($this->unidades->cambiarEstado($id, $estado, $actorId), $this->usuarios);
    }

    /**
     * Una posición Suprimida con una asignación vigente o futura sigue ocupada: bloquea la desactivación de su rama.
     *
     * @throws DesactivacionBloqueada
     */
    private function rechazarSiHayAsignaciones(int $id): void
    {
        if ($this->posiciones === null || $this->ocupacion === null) {
            return;
        }

        $unidad = $this->unidades->obtener($id);
        if ($unidad === null || ! $unidad->estaActiva() || $unidad->esRaiz()) {
            return; // el repositorio resuelve inexistente, ya inactiva o raíz
        }

        $impacto = $this->unidades->impactoDeDesactivacion($id);
        $rama = [$unidad->id => $unidad];
        foreach ($impacto->dependientesActivas as $dependiente) {
            $rama[$dependiente->id] = $dependiente;
        }

        $hoy = now()->toDateString();
        $bloqueos = [];
        foreach ($this->posiciones->suprimidasDeUnidades(array_keys($rama)) as $unidadId => $posicionIds) {
            $ocupadas = $this->ocupacion->conAsignacionVigenteOFutura($posicionIds, $hoy);
            if ($ocupadas !== []) {
                $bloqueos[$unidadId] = [
                    'id' => $unidadId,
                    'codigo' => $rama[$unidadId]->codigo,
                    'nombre' => $rama[$unidadId]->nombre,
                    'cantidad' => count($ocupadas),
                ];
            }
        }

        if ($bloqueos === []) {
            return;
        }

        // Se suman a las unidades ya bloqueadas por su estado guardado, para informar el total de la rama.
        foreach ($impacto->unidadesConPosiciones as $existente) {
            if (isset($bloqueos[$existente['id']])) {
                $bloqueos[$existente['id']]['cantidad'] += $existente['cantidad'];
            } else {
                $bloqueos[$existente['id']] = $existente;
            }
        }

        throw new DesactivacionBloqueada(array_values($bloqueos), true);
    }
}
