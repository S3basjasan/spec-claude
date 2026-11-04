<?php

namespace App\Modules\Compensaciones\Application\Services;

use App\Modules\Compensaciones\Application\DTOs\DatosCategoriaDTO;
use App\Modules\Compensaciones\Application\Ports\UnidadesPort;
use App\Modules\Compensaciones\Domain\Exceptions\DatosDeCategoriaInvalidos;
use App\Modules\Compensaciones\Domain\ValueObjects\UnidadesAsignadas;

/**
 * Convierte lo que pide el Gestor (lista de unidades o «Todas las unidades») en unidades asignadas válidas:
 * existentes y activas en el momento de guardar.
 */
final class ResolutorDeUnidades
{
    public function __construct(private readonly UnidadesPort $unidades) {}

    /**
     * @throws DatosDeCategoriaInvalidos
     */
    public function resolver(DatosCategoriaDTO $datos): UnidadesAsignadas
    {
        if ($datos->todasLasUnidades) {
            return new UnidadesAsignadas(array_map(fn (array $u) => $u['id'], $this->unidades->activas()));
        }

        $asignadas = new UnidadesAsignadas($datos->unidadesIds ?? []);
        $encontradas = $this->unidades->porIds($asignadas->ids());

        foreach ($asignadas->ids() as $id) {
            if (($encontradas[$id]['estado'] ?? null) !== 'Activo') {
                throw new DatosDeCategoriaInvalidos('unidades_ids', 'La unidad seleccionada ya no está activa.');
            }
        }

        return $asignadas;
    }
}
